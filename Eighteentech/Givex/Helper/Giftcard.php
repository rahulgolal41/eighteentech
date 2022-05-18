<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

namespace Eighteentech\Givex\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\GiftCardAccount\Model\GiftcardaccountFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Eighteentech\Givex\Helper\GivexConstants;
use Eighteentech\Givex\Helper\Config;
use Eighteentech\Givex\Helper\GivexValidator;
use Eighteentech\Givex\Logger\Logger;

require_once(BP.'/app/code/Eighteentech/Givex/lib/phpxmlrpc/lib/xmlrpc.inc');

/**
 * Provides an interface for using GiveX gift cards, such as fetching balance, and pre- and post-auth actions.
 */
class Giftcard extends AbstractHelper
{

    /**
     * @var $giftCardsModel
     */
    protected $giftCardsModel;

    /**
     * @var $storeManager
     */
    private $storeManager;

    /**
     * @var $validator
     */
    private $validator = null;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

   /**
    * @var \Magento\Framework\Serialize\Serializer\Json
    */
    protected $serializer;

   /**
    * @var \Eighteentech\Givex\Logger\Logger
    */
    protected $logger;

    // Constants
    const TAG = 'GiftcardHelper: ';
    const LOG_DIRECTORY = '/GiveXGiftcardHelper'; // This is relative to the Magento log directory. E.g. ~/var/log
    const FAILOVER_TIMEOUT_SECONDS = 15;
    const DEFAULT_FRIENDLY_ERROR_MESSAGE = 'Internal server error.'; // Friendly error message for networking errors, etc shown to users.
    const MIN_ECERT_AMOUNT = 5.00; // If trying to create an ecert and it's for less than this, this amount will be used instead. // This value is float. I.e. 2 = 2 dollars.
        
    // Config
    private $config = [
        'primaryApiUrl' => null,
        'secondaryApiUrl' => null,
        'userId' => null,
        'password' => null,
        'referenceid' => null,
        'newRewardsPromoUserId' => null,
        'newRewardsPromoPassword' => null,
        'birthdayPromo2016UserId' => null,
        'birthdayPromo2016Password' => null,
    ];

    // State
    private $isAdminMode = false;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\GiftCardAccount\Model\GiftcardaccountFactory $giftCardsModel
     * @param \Eighteentech\Givex\Helper\Config $givexConfigHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     * @param \Eighteentech\Givex\Logger\Logger $logger
     */
    public function __construct(
        Context $context,
        GiftcardaccountFactory $giftCardsModel,
        Config $givexConfigHelper,
        StoreManagerInterface $storeManager,
        GivexValidator $validator,
        CartRepositoryInterface $quoteRepository,
        Json $serializer,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->giftCardsModel = $giftCardsModel;
        $this->_givexConfigHelper = $givexConfigHelper;
        $this->config['primaryApiUrl'] = $this->scopeConfig->getValue('givexconfig/givexapi/givexprimaryapi') . ':' . $this->scopeConfig->getValue('givexconfig/givexapi/givexporta');
        $this->config['secondaryApiUrl'] = $this->scopeConfig->getValue('givexconfig/givexapi/givexsecondaryapi') . ':' . $this->scopeConfig->getValue('givexconfig/givexapi/givexporta');
        $this->config['userId'] = $this->scopeConfig->getValue('givexconfig/givexapi/givexuserid');
        $this->config['password'] = $this->scopeConfig->getValue('givexconfig/givexapi/givexpassword');
        $this->config['givexreferenceid'] = $this->scopeConfig->getValue('givexconfig/givexapi/givexreferenceid');
        $this->storeManager = $storeManager;
        $this->validator = $validator;
        $this->quoteRepository = $quoteRepository;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Sets admin mode. If true, raw error message will be shown for errors not already handled, instead of a generic
     * 'Internal server error' message.
     * @param $isAdminMode
     */
    public function setIsAdminMode($isAdminMode)
    {
        $this->isAdminMode = $isAdminMode;
    }

    public function getBalance($giveXNumber, &$outErrorMessage, &$outHasPreauth)
    {
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Getting balance for card '{$giveXNumber}'.");

        // Clean and validate inputs
        $giveXNumber = $this->validator->cleanGiveXNumber($giveXNumber);

        if (!$this->validator->validateGiftCardNumberFormat($giveXNumber, $outErrorMessage)) {
            return false;
        }
        // Make request
        $params = [
            'en', // Language code
            $this->getNewTransactionCode('dc_994'), // Transaction code (of our choosing)
            $this->config['userId'], // User ID
            $this->config['password'], // Password
            $giveXNumber, // GiveX number
            '', // Serial
            '', // ISO
            'PreAuth', // History type
        ];
        $request = $this->getRequest('dc_994', $params);
        $response = $this->call($request, null);
        if ($response === false) {
            $this->logger->writeLog(self::TAG . 'Call failed.');
            $outErrorMessage = self::DEFAULT_FRIENDLY_ERROR_MESSAGE;
            
            return false;
        }

        // Parse response
        /**
            Transaction code
            Result
            Certificate balance or error message
            currency
            pointsBalance
            transHist
            totalRows
            ISO-serial
            Certificate expiration date
         */

        // Success
        if ($response[1] === '0') {
            $this->logger->writeLog(self::TAG . "Balance received: {$response[2]}");
            
            // If balance is zero, report it as an error instead.
            if ($response[2] === '0.00') {
                $outErrorMessage = __('Sorry, there is no credit remaining on this gift card.');
                return false;
            } else {
                return $response[2];
            }
        // Expired
        } elseif ($response[1] === '285') {
            $this->logger->writeLog(self::TAG . "Expired: {$response[2]}");
            $outErrorMessage = __('Sorry, this gift card has expired.');
            return false;
        // Error
        } else {
            $this->parseResponseError($response, $outErrorMessage);
            return false;
        }
    }
    
    /**
     * Performs an API call to GiveX. This implements GiveX's failover process if a transactionCode is provided.
     * @param $request
     * @param $reversalRequest
     * @return array|bool
     */
    private function call($request, $reversalRequest)
    {
        $outErrorMessage = null;

        // Try primary server
        $this->logger->writeLog(self::TAG . 'Trying primary API URL...');
        $client = $this->getClient($this->config['primaryApiUrl']);
        $response = $client->send($request, self::FAILOVER_TIMEOUT_SECONDS);
        // If response OK, done
        if ($response->errno === 0) {
            $this->logger->writeLog(self::TAG . 'Response received.');
            return $this->parseResponse($response);
        }

        // Failed. Probably timeout.
        $this->logger->writeLog(self::TAG . 'Primary API failed:', (array)$response);

        // Send reversal if provided. Can ignore response.
        if ($reversalRequest !== null) {
            $this->logger->writeLog(self::TAG . "Sending primary reversal.");
            $reversalResponse = $client->send($reversalRequest, self::FAILOVER_TIMEOUT_SECONDS);
            if ($reversalResponse->errno === 0) {

                $reversalResponse = $this->parseResponse($reversalResponse);
            }
            $this->logger->writeLog(self::TAG . "Primary reversal response:", (array)$reversalResponse);
        } else {
            $this->logger->writeLog(self::TAG . 'Primary reversal request not required.');
        }

        // Try again with secondary server
        $this->logger->writeLog(self::TAG . 'Trying secondary API URL...');
        $client = $this->getClient($this->config['secondaryApiUrl']);
        $response = $client->send($request, self::FAILOVER_TIMEOUT_SECONDS);

        // If response OK, done
        if ($response->errno === 0) {
            $this->logger->writeLog(self::TAG . 'Response received.');
            return $this->parseResponse($response);
        }

        // Failed. Probably timeout.
        $this->logger->writeLog(self::TAG . 'Secondary API failed.', (array)$response);

        // Send reversal if provided. Can ignore response.
        if ($reversalRequest !== null) {
            $this->logger->writeLog(self::TAG . "Sending secondary reversal.");
            $reversalResponse = $client->send($reversalRequest, self::FAILOVER_TIMEOUT_SECONDS);
            if ($reversalResponse->errno === 0) {
                $reversalResponse = $this->parseResponse($reversalResponse);
            }
            $this->logger->writeLog(self::TAG . "Secondary reversal response:", (array)$reversalResponse);
        } else {
            $this->logger->writeLog(self::TAG . 'Secondary reversal request not required.');
        }
        // All failed
        return false;
    }

    private function getNewTransactionCode($prefix)
    {
        return uniqid($prefix . '.', false);
    }

    private function getClient($url)
    {
        $client = new \xmlrpc_client($url);
        $client->setSSLVerifyHost(0); // Not verifying host as GiveX cert uses dcssl.givex.com as common name, which doesn't match host...
        $client->setSSLVerifyPeer(0); // No point verifying CA, as 1) GiveX use self-signed certificates, and 2) unable to verify host anyway.
        return $client;
    }

    private function getRequest($callId, $params)
    {
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Creating request for method '{$callId}':", $params);
        $xmlrpcParameters = [];
        
        foreach ($params as $param) {
            $xmlrpcParameters[] = new \xmlrpcval($param, 'string');
        }
        
        return new \xmlrpcmsg($callId, $xmlrpcParameters);
    }

    private function parseResponse($response)
    {
        $values = [];
        
        if (empty($response->val->me['array'])) {
            return $values;
        }
        
        foreach ($response->val->me['array'] as $value) {

            if (isset($value->me['string'])) {

                $values[] = $value->me['string'];
            } elseif ($value->me['array']) {

                $nestedParams = [];
                foreach ($value->me['array'] as $array) {

                    $nestedValues = [];
                    foreach ($array->me['array'] as $nestedValue) {

                        $nestedValues[] = $nestedValue->me['string'];
                        ;
                    }
                    $nestedParams[] = $nestedValues;
                }
                $values[] = $nestedParams;
            }
        }
        
        return $values;
    }
    private function parseResponseError($response, &$outErrorMessage)
    {

        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        // We only check for error codes we can provide a meaningful response to. Otherwise allow the
        // catch-all at the end handle it.
        if ($response[1] === '2') {
            $this->logger->writeLog(self::TAG . 'Invalid gift card number.');
            $outErrorMessage = 'Sorry, we cannot find that gift card in our database. Please confirm the gift card number or contact us for help.';
        } elseif ($response[1] === '6') {
            $this->logger->writeLog(self::TAG . 'Gift card expired.');
            $outErrorMessage = 'Sorry, this gift card has expired.';
        } elseif ($response[1] === '9') {
            $this->logger->writeLog(self::TAG . 'Insufficient funds.');
            $balance = substr($response[2], strpos($response[2], '$'));
            $outErrorMessage = "Sorry, insufficient funds on gift card. Balance is {$balance}.";
        } elseif ($response[1] === '40') {
            $this->logger->writeLog(self::TAG . 'Gift card not activated.');
            $outErrorMessage = 'Sorry, this gift card has not been activated. Please contact us for help.';
        } else {
        // Catch all
            $this->logger->writeLog(self::TAG . 'Unknown error.', $response);
            if ($this->isAdminMode) {
                $outErrorMessage = 'GiveX error: ' . $response[2];
            } else {
                $outErrorMessage = 'Sorry, there was a problem. ' . self::DEFAULT_FRIENDLY_ERROR_MESSAGE;
            }
        }
    }
    public function createGiftCard(
        \Magento\Quote\Model\Quote $customerQuote,
        $givexNumber,
        $givexBalance
    ) {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        $data = [
            'code' => $givexNumber,
            'balance' => $givexBalance,
            'status' => 1,
            'website_id' => $websiteId
        ];
        $giftCardModel = $this->giftCardsModel->create();
        $giftCardModel->setData($data);

        try {
            $giftCardModel->save();
        } catch (\Exception $e) {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Error message:'." ".$e->getMessage());
        }
        return true;
    }

        /**
         * Completes the associated pre-auth.
         *
         * For reference, if you try to post-auth more than what was pre-authed, and the post-auth amount is more than
         * the card balance, the amount post-authed will be the balance, which could be less than you were expecting.
         * Therefore, don't do it.
         *
         * @param $giveXNumber string The GiveX card number for the given card.
         * @param $preAuthCode string The code returned by preAuth for the transaction to confirm.
         * @param $amount string:float The amount in dollars and cents. Make sure this is the same amount or less than
         *         was pre-authed, otherwise there can be unintended consequences.
         * @param $outErrorMessage
         * @param $outTransactionReference string The transaction reference from GiveX.
         * @return bool If or not the post-auth was successful.
         */
    public function postAuth(
        $giveXNumber,
        $preAuthCode,
        $amount,
        &$outErrorMessage = '',
        &$outTransactionReference = ''
    ) {

        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Post-auth transaction '{$preAuthCode}' on card '{$giveXNumber}' for \${$amount}.");

        // Clean and validate inputs
        $giveXNumber = $this->validator->cleanGiveXNumber($giveXNumber);
        if (!$this->validator->validateGiftCardNumberFormat($giveXNumber, $outErrorMessage)) {

            return false;
        }
        $amount = $this->validator->cleanAmount($amount);

        // Make request
        $params = [
            'en', // Language code
            $this->getNewTransactionCode('dc_921'), // Transaction code (of our choosing)
            $this->config['userId'], // User ID
            $this->config['password'], // Password
            $giveXNumber, // GiveX number
            $amount, // Amount
            $preAuthCode, // Pre-auth code
        ];
        $request = $this->getRequest('dc_921', $params);
        $reversalRequest = $this->getRequest('dc_918', $params); // Uses same params
        $response = $this->call($request, $reversalRequest);
        if ($response === false) {

            $this->logger->writeLog(self::TAG . 'Call failed.');
            $outErrorMessage = self::DEFAULT_FRIENDLY_ERROR_MESSAGE;
            return false;
        }

        // Parse response
        /**
            Transaction code
            Result
            Givex transaction reference or error message
            Amount redeemed
            Certificate balance
            Certificate expiration date
            Receipt message (optional)
        */

        $outTransactionReference = $response[2];

        // Success
        if ($response[1] === '0') {

            $this->logger->writeLog(self::TAG . "Post-authed \${$response[3]}. Transaction reference '{$response[2]}'.");
            // Not returning the actual auth amount as should never be trying to post-auth more than pre-authed.
            return true;
        // If cancelling and already cancelled, it's kind of an error but it's the result we want...
        } elseif ($response[1] === '247' && $amount === '0.00') {
            $this->logger->writeLog(self::TAG . "Pre-auth already cancelled.");
            return true;
        // If cancelling and already closed (basically, a really old preauth), it's kind of an error but it's the result we want...
        } elseif ($response[1] === '381') {
            $this->logger->writeLog(self::TAG . "Pre-auth already closed.");
            return true;
        // Error
        } else {
            $this->parseResponseError($response, $outErrorMessage);
            return false;
        }
    }

    /**
     * Attempts to pre-auth the given amount on the given card. If successful, returns the pre-auth reference code
     * you need to pass to postAuth() or cancelPreAuth() to complete the transaction.
     *
     * When sending a pre-auth request to GiveX, if the available balance on a card is greater than zero, but less than
     * the amount requested, the pre-auth is actually successful but only for the available balance. This is at odds with
     * how we want it to work on offyatree, which is if there isn't enough to pre-auth, it should fail. Therefore, this
     * implements that functionality automatically: if a pre-auth is successful but the amount is less than
     * requested/expected, the pre-auth is automatically cancelled and an insufficient funds error returned.
     *
     * @param $giveXNumber string The GiveX card number for the given card.
     * @param $amount string:float The amount in dollars and cents. Can be a number or string. E.g. 1, 1.00 or '1.00'
     *         are all valid. Internally will be converted to a float, then a number formatted string with 2 decimal places.
     * @param $outErrorMessage
     * @param $quoteId
     * @return bool|string A pre-auth code, or false on error.
     */
    public function preAuth($giveXNumber, $amount, $quoteId = -1)
    {
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Pre-auth card '{$giveXNumber}' for \${$amount} for QuoteID '{$quoteId}'.");

        // Clean and validate inputs
        $giveXNumber = $this->validator->cleanGiveXNumber($giveXNumber);
        
        if (!$this->validator->validateGiftCardNumberFormat($giveXNumber, $outErrorMessage)) {
            return false;
        }
        $amount = $this->validator->cleanAmount($amount);

        // Make request
        $params = [
            'en', // Language code
            $this->getNewTransactionCode('dc_920'), // Transaction code (of our choosing)
            $this->config['userId'], // User ID
            $this->config['password'], // Password
            $giveXNumber, // GiveX number
            $amount, // Amount
        ];
        $request = $this->getRequest('dc_920', $params);
        $reversalRequest = $this->getRequest('dc_918', $params); // Uses same params
        $response = $this->call($request, $reversalRequest);
        if ($response === false) {

            $this->logger->writeLog(self::TAG . 'Call failed.');
            $outErrorMessage = self::DEFAULT_FRIENDLY_ERROR_MESSAGE;
            return false;
        }

        // Parse response
        /**
            Transaction code
            Result
            GiveX pre-auth reference or error message
            Authorized Amount
            Certificate balance
            Certificate expiration date
         */

        // Success
        if ($response[1] === '0') {

            // Check actual amount pre-authed is same as requested amount
            if ($response[3] === $amount) {

                $this->logger->writeLog(self::TAG . "Pre-authed \${$response[3]} with pre-auth code '{$response[2]}'.");
                return $response[2];
            // Amount is difference, cancel immediately.
            } else {
                $this->logger->writeLog(self::TAG . "Pre-authed amount \${$response[3]} (pre-auth code '{$response[2]}') is less than requested amount \${$amount}. Pre-auth will be cancelled.");
                if (!$this->cancelPreAuth($giveXNumber, $response['2'])) {

                    $outErrorMessage = "Sorry, insufficient funds on gift card and unable to reverse pre-authorisation. Please contact us for help and quote reference number '{$response[2]}'.";
                } else {

                    $outErrorMessage = 'Sorry, insufficient funds on gift card. Please try again.';
                }
                return false;
            }
         // Error
        } else {
            $this->parseResponseError($response, $outErrorMessage);
            return false;
        }
    }

    /**
     * Cancels the given pre-auth.
     *
     * @param $giveXNumber string The GiveX card number for the given card.
     * @param $preAuthCode string The code returned by preAuth for the transaction to cancel.
     * @param $outErrorMessage
     * @param $quoteId
     * @return bool If or not the cancellation was successful.
     */
    public function cancelPreAuth($giveXNumber, $preAuthCode, &$outErrorMessage = '', $quoteId = -1)
    {
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Cancel pre-auth transaction '{$preAuthCode}' on card '{$giveXNumber}' for QuoteID '{$quoteId}'");

        // To cancel a pre-auth, you simple call post-auth with an amount of zero.
        $result = $this->postAuth($giveXNumber, $preAuthCode, 0.00, $outErrorMessage);
        if ($result === true) {

            $this->logger->writeLog(self::TAG . "Pre-auth '{$preAuthCode}' cancelled.");
        } else {
            $this->logger->writeLog(self::TAG . "Unable to cancel pre-auth '{$preAuthCode}'.");
        }
        return $result;
    }

    /**
     * Post-auths a completed order.
     * @throws Varien_Exception
     * @return bool
     */
    public function postauthCompletedOrder($order)
    {

        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);

        // Ensure is order
        if (empty($order) || empty($order->getId())) {
            return false;
        }

        // Get the quote for the order
        $quoteId = $order->getQuoteId();
        $quote = $this->quoteRepository->get($quoteId);

        if (empty($quote) || empty($quote->getId())) {
            return false;
        }

           $cards = $quote['gift_cards'];
           
        if (empty($cards)) {
                return false;
        }

        $quoteSubtotal = $order->getSubtotal();
        $shippingAmount = $order->getShippingAmount();
        $orderTax = $order->getTaxAmount();
        $quoteGrandtotal = $quoteSubtotal + $shippingAmount + $orderTax;

        $cardsData = $this->serializer->unserialize($cards);
              
        if ($cardsData && is_array($cardsData)) {
            $preAuthorizedCodes = [];
            $postAuthRefCodes = [];
            
            if (!empty($quote->getGivexGiftcardPreauthCode())) {
                $preAuthorizedCodes = $this->serializer->unserialize($quote->getGivexGiftcardPreauthCode());
            }
            foreach ($cardsData as $key => $option) {
                $preauthCode = empty($preAuthorizedCodes[$key]) ? null : $preAuthorizedCodes[$key];
                
                // Ensure that if there is a giftcard discount, there is also a pre-auth. Otherwise, the customer has somehow
                // bypassed the pre-auth. All we can do in this case is flag it for manual intervention.
                $isPreauthBypassed = (floatval($option['ba']) > 0 && (empty($preauthCode))
                );
                
                if ($isPreauthBypassed) {
                    $reason = 'Order with giftcard discount is missing pre-auth or card number. Gift card must be charged manually.';
                    $reason .= ' (Order ID: ' . $order->getId() . ')';
                    $this->logger->writeLog(''.$reason);
                    $this->logger->writeLog('Post-auth not required?');
                    return false;
                }

                $cardApplied = 0;
                if ($quoteGrandtotal > 0) {
                    $cardCode = $option['c'];
                    $cardStoreBalance = $option['ba'];
                    $cardApplied      = min($quoteGrandtotal, $cardStoreBalance);
                    $quoteGrandtotal  -= $cardApplied;
                }
                
                // Check if post-auth required
                $giftcardNumber = $option['c'];
                $giftcardAmount = $cardApplied;
                if (empty($preauthCode) || empty($giftcardNumber) || empty($giftcardAmount)) {
                    $this->logger->writeLog('Post-auth not required?');
                     return true;
                }

                // Perform post-auth
                $outErrorMessage = null;
                $outPostauthReference = null;
                $result = $this->postAuth($giftcardNumber, $preauthCode, $giftcardAmount, $outErrorMessage, $outPostauthReference);

                if ($result) {
                    $postAuthRefCodes[$key] = $outPostauthReference;
                    // Add message to order history
                    $message = "Applied giftcard number '{$giftcardNumber}' for amount of \$" . number_format($giftcardAmount, 2) . ". Reference: {$outPostauthReference}";
                    $order->addStatusHistoryComment($message)
                        ->setIsVisibleOnFront(true)
                        ->setIsCustomerNotified(false);
                        $this->logger->writeLog(''.$message);
                // If this happens, there's basically an outstanding giftcard amount to be billed, so send an email
                } else {
                    $reason = 'Unable to post-auth gift card: ' . $outPostauthReference;
                    $reason .= ' (Order ID: ' . $order->getId() . ')';
                    $this->logger->writeLog(''.$reason);
                }
            }
            $this->logger->writeLog(json_encode($postAuthRefCodes));
            // Copy details from quote to order...
            $order->setGivexGiftcardPreauthCode($quote->getGivexGiftcardPreauthCode());
            // Update order to show post-auth complete
            $order->setGivexGiftcardPostauthRef($this->serializer->serialize($postAuthRefCodes));
            $order->save(); // Saves children too
            $this->logger->writeLog('Post-auth complete.');
        }
        return true;
    }

    /**
     * Creates a new virtual gift card for the specified amount. Card is activated automatically so is ready for use.
     * If someone refers to creating an eCert, then that is would be done with this call.
     *
     * This card will be created using regular API credentials meaning its expiry is 12 months or so.
     *
     * @param $amount string:float The amount in dollars and cents to activate the card for.
     * @param $outErrorMessage
     * @return bool|string The new GiveX card number, or false if unsuccessful.
     */
    public function createVirtualGiftCard($giftCards, &$outErrorMessage = '')
    {
         $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);
         $this->logger->writeLog(self::TAG . "Create new virtual gift card for \${$giftCards['cardamount']}.");
         $requestXML = $this->getRequestForGiftcard($giftCards);
        
         $params = php_xmlrpc_decode_xml($requestXML);
         $response = $this->call($params, null);
        if ($response === false) {
            $this->logger->writeLog(self::TAG . 'Call failed.');
            $outErrorMessage = self::DEFAULT_FRIENDLY_ERROR_MESSAGE;
            return false;
        }
        
        if ($response[1] == '0') {
            //$this->logger->writeLog(self::TAG . "Card created with GiveX number '{json_encode($response)}', balance {json_encode($giftCards['cardamount'])}, Transaction reference '{json_encode($response[0])}'.");
            return $response[2];
        // Error
        } else {
            $this->parseResponseError($response, $outErrorMessage);
            return false;
        }
    }

    public function getRequestForGiftcard($giftCards)
    {
        $xmlstring = '<methodCall>
	<methodName>dc_956</methodName>
	<params>
		<param>
			<value>
				<string>'.$giftCards['language'].'</string>
			</value>
		</param>
		<param>
			<value>
				<string>'.$this->getNewTransactionCode('dc_956').'</string>
			</value>
		</param>
		<param>
			<value>
				<string>'.$this->config['userId'].'</string>
			</value>
		</param>
		<param>
			<value>
				<string>'.$this->config['password'].'</string>
			</value>
		</param>
		<param>
			<value>
				<string>'.$giftCards['customerorderid'].'</string>
			</value>
		</param>
		<param>
			<value>
				<string>'.$giftCards['sendername'].'</string>
			</value>
		</param>
		<param>
			<value>
				<string>'.$giftCards['senderemail'].'</string>
			</value>
		</param>
		<param>
			<value>
				<string>'.$giftCards['ordertotal'].'</string>
			</value>
		</param>
		<param>
			<value>
				<array>
					<data>
						<value>
							<array>
								<data>
									<value>
										<string>'.$giftCards['billingid'].'</string>
									</value>
									<value>
										<string>'.$giftCards['recivername'].'</string>
									</value>
									<value>
										<string>'.$giftCards['reviveremail'].'</string>
									</value>
									<value>
										<string></string>
									</value>
									<value>
										<array>
											<data>
												<value>
													<array>
														<data>
															<value>
																<string>'.$giftCards['customerorderid'].'</string>
															</value>
															<value>
																<string>'.$giftCards['orderitemdetailid'].'</string>
															</value>
															<value>
																<string>'.$giftCards['itemqty'].'</string>
															</value>
															<value>
																<string>'.$giftCards['cardamount'].'</string>
															</value>
															<value>
																<array>
																	<data>
																		<value>
																			<array>
																				<data>
																					<value>
																						<string>'.$giftCards['recivername'].'</string>
																					</value>
																					<value>
																						<string>'.$giftCards['sendername'].'</string>
																					</value>
																					<value>
																						<string>'.$giftCards['message'].'</string>
																					</value>
																				</data>
																			</array>
																		</value>
																	</data>
																</array>
															</value>
															<value>
																<string/>
															</value>
														</data>
													</array>
												</value>
											</data>
										</array>
									</value>
									<value>
										<string></string>
									</value>
									<value>
										<string>email</string>
									</value>
								</data>
							</array>
						</value>
					</data>
				</array>
			</value>
		</param>
		<param>
			<value>
				<string/>
			</value>
		</param>
		<param>
			<value>
				<string/>
			</value>
		</param>
		<param>
			<value>
				<array>
					<data/>
				</array>
			</value>
		</param>
		<param>
			<value>
				<string></string>
			</value>
		</param>
		<param>
			<value>
				<array>
					<data/>
				</array>
			</value>
		</param>
	</params>
</methodCall>';
        return $xmlstring;
    }

    /**
     * Common code for generating a new virtual gift card. The sort of card created will depend on the API credentials
     * passed in.
     *
     * @param $apiUsername
     * @param $apiPassword
     * @param $amount
     * @param string $outErrorMessage
     * @param array $outCardData If provided, is populated with additional details about the such, such as expiration.
     * @return bool
     */
    private function createVirtualCard($apiUsername, $apiPassword, $amount, &$outErrorMessage = '', &$outCardData = [])
    {
        $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);

        // Clean and validate inputs
        $amount = $this->validator->cleanAmount($amount);

        if ((float)$amount < self::MIN_ECERT_AMOUNT) {

            $amount = number_format(self::MIN_ECERT_AMOUNT, 2, '.', '');
            $this->logger->writeLog(self::TAG . "As amount is less than minimum ecert amount, amount has been changed to: \${$amount}");
        }

        $reversalRequest = $this->getRequest('dc_918', $params); // Uses same params
        $response = $this->call($request, $reversalRequest);
        if ($response === false) {

            $this->logger->writeLog(self::TAG . 'Call failed.');
            $outErrorMessage = self::DEFAULT_FRIENDLY_ERROR_MESSAGE;
            return false;
        }

        // Parse response
        /**
        Transaction code
        Result
        Givex transaction reference or error message
        Givex number
        Certificate balance
        Certificate expiration date
        Receipt message (optional)
         */

        // Success
        if ($response[1] === '0') {

            $this->logger->writeLog(self::TAG . "Card created with GiveX number '{$response[3]}', balance \${$response[4]} and expiry {$response[5]}. Transaction reference '{$response[2]}'.");
            $outCardData = [
                'givex_number' => $response[3],
                'balance' => $response[4],
                'expiration_date' => $response[5],
            ];
            return $response[3];
        // Error
        } else {
            $this->parseResponseError($response, $outErrorMessage);
            return false;
        }
    }
}
