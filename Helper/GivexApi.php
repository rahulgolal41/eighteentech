<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

namespace Eighteentech\Givex\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Eighteentech\Givex\Helper\GivexConstants;

//require_once(BP.'/lib/internal/phpxmlrpc/lib/xmlrpc.inc');
require_once(BP.'/app/code/Eighteentech/Givex/lib/phpxmlrpc/lib/xmlrpc.inc');

class GivexApi extends AbstractHelper
{
    const TAG = 'GivexApi: ';
    const CARD_TYPE_GIFT = 1;
    const CARD_TYPE_LOYALTY = 2;
    const DEFAULT_FRIENDLY_ERROR_MESSAGE = 'Internal server error.';

    const GIVEX_FAILOVER_TIMEOUT_SECONDS = 16;

    // Config
    private $config = [
        'primaryApiUrl' => null,
        'secondaryApiUrl' => null,
        'userId' => null,
        'password' => null,
        'portA' => null,
        'portB' => null,
        'portC' => null
    ];

    // State
    private $isInitiated = false;
    private $isAdminMode = false;
    private $soapClient = null; // Only needed while loyalty expiry is in Futura.

    /**
     * @var \Eighteentech\Givex\Helper\Config
     */
    protected $_givexConfigHelper;

    /**
     * @var Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface
     */
    protected $customerRepositoryInterface;
    
    /**
     * @var \Magento\Customer\Model\ResourceModel\Customer\Collection $customerCollection
     */
    protected $_customerCollection;

     /**
      * @var \Magento\Customer\Model\Session
      */
    protected $_customerSession;

    /**
     * @var $_soapClientFactory
     */
    protected $_soapClientFactory;

    /**
     * @var $validator
     */
    private $validator = null;

    /**
     * @var \Eighteentech\Givex\Logger\Logger
     */
    protected $logger;

    /**
     * @param Magento\Framework\App\Helper\Context $context
     * @param \Eighteentech\Givex\Helper\Config $givexConfigHelper
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface
     * @param \Magento\Customer\Model\ResourceModel\Customer\Collection $customerCollection
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Webapi\Soap\ClientFactory $soapClientFactory
     * @param \Eighteentech\Givex\Helper\GivexValidator $validator
     * @param \Eighteentech\Givex\Logger\Logger $logger
     */
    public function __construct(
        Context $context,
        \Eighteentech\Givex\Helper\Config $givexConfigHelper,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Magento\Customer\Model\ResourceModel\Customer\Collection $customerCollection,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Webapi\Soap\ClientFactory $soapClientFactory,
        \Eighteentech\Givex\Helper\GivexValidator $validator,
        \Eighteentech\Givex\Logger\Logger $logger
    ) {
        $this->_givexConfigHelper = $givexConfigHelper;
        $this->_encryptor = $encryptor;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
           $this->_customerCollection = $customerCollection;
           $this->_customerSession = $customerSession;
           $this->_soapClientFactory = $soapClientFactory;
           $this->validator = $validator;
           $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Called internally before performing any actions. Sets up logging and config settings.
     */
    private function init()
    {
        // Only need to init once
        if ($this->isInitiated) {

            return;
        }
        $this->isInitiated = true;

        // Get givex config settings
        $this->config['primaryApiUrl'] = $this->_givexConfigHelper->getPrimaryAPIUrl();
        $this->config['secondaryApiUrl'] = $this->_givexConfigHelper->getSecondaryAPIUrl();
        $this->config['userId'] = $this->_givexConfigHelper->getGivexUserId();
        $password = $this->_givexConfigHelper->getGivexPassword();
        $this->config['password'] = $password;//$this->_encryptor->decrypt($password);

        $this->config['portA'] = $this->_givexConfigHelper->getPortA();
        $this->config['portB'] = $this->_givexConfigHelper->getPortB();
        $this->config['portC'] = $this->_givexConfigHelper->getPortC();
    }

       /**
        * Get Customer Data
        *
        * @return Customer
        */
    public function getCustomerData()
    {
        $customerSessionData = '';
        $customerSessionData = $this->_customerSession->getCustomer();
        return $customerSessionData;
    }

    /**
     * Cleans the given GiveX number, removing any spaces.
     * @param $originalGiveXNumber
     * @return mixed|string
     */
    public function cleanGiveXNumber($originalGiveXNumber)
    {
        $giveXNumber = trim($originalGiveXNumber);
        $giveXNumber = str_replace(' ', '', $giveXNumber);
        return $giveXNumber;
    }

    /**
     * Validates the given GiveX gift card number format. Does not query GiveX.
     * @param $giveXNumber
     * @param $outErrorMessage
     * @return bool
     */
    public function validateGiftCardNumberFormat($giveXNumber, &$outErrorMessage)
    {
        return $this->validateCardNumberFormat($giveXNumber, self::CARD_TYPE_GIFT, $outErrorMessage);
    }

    /**
     * Validates the given GiveX loyalty card number format. Does not query GiveX.
     * @param $giveXNumber
     * @param $outErrorMessage
     * @return bool
     */
    public function validateLoyaltyCardNumberFormat($giveXNumber, &$outErrorMessage)
    {
        return $this->validateCardNumberFormat($giveXNumber, self::CARD_TYPE_LOYALTY, $outErrorMessage);
    }

   /**
    * Returns the account associated with the given GiveX number, or false if not found or on error.
    *
    * @param $giveXNumber
    * @param $outErrorMessage
    * @return bool
    */
    public function accountLookup($giveXNumber, &$outErrorMessage, &$logger = null)
    {
        if (!empty($logger)) {
            $this->logger = $logger;
        }

        $this->init();
        // Clean and validate inputs
        $giveXNumber = $this->cleanGiveXNumber($giveXNumber);
        if (!$this->validateLoyaltyCardNumberFormat($giveXNumber, $outErrorMessage)) {

            return false;
        }

        //return true;

        // Make request
        $params = [
            'en', // Language code
            $this->getNewTransactionCode('dc_996'), // Transaction code (of our choosing)
            $this->config['userId'], // User ID
            $this->config['password'], // Password
            $giveXNumber, // GiveX number

        ];
        $request = $this->getRequest('dc_996', $params);

        $response = $this->call($request, null);

        if ($response === false) {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Call failed.');
            $outErrorMessage = self::DEFAULT_FRIENDLY_ERROR_MESSAGE;
            return false;
        }

        // Parse response
        /**
        Transaction code
        Result
        Member address
        Member mobile
        Member email
        Member birthdate
        SMS contact answer
        Email contact answer
        Mail contact answer
        Member phone
        Referring member name
        Member title
        ISO-serial
        Purchase amount to reach next tier
        Purchase amount to remain in current tier
        Purchase amount via default promo code to get next "points to money conversion" reward Date of the last tier change
        Amount spent since last tier change
        Message Type
        Message Delivery Method
         */
        // Success
        if ($response[1] === '0') {

            $this->logger->info(self::TAG . "Account retrieved. ");
            $defaultUsername = $response[4];
            // Work out the account's default username in case it's needed
            if (empty($defaultUsername)) {

                $defaultUsername = "{$giveXNumber}@offyatree.com.au";
            }
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Default username set to: {$defaultUsername}");
            return [
                'address' => $response[2],
                'email' => $response[4],
                'birthdate' => $response[5],
                'phone' => $response[9],
                'default_username' => $defaultUsername,
            ];
        } else {
        // Error
            $this->parseResponseError($response, $outErrorMessage);
            return false;
        }
    }

    /**
     * Validates the given GiveX number format. Does not query GiveX.
     * @param $giveXNumber
     * @param $cardType int An ID for gift or loyalty.
     * @param $outErrorMessage
     * @return bool
     */
    public function validateCardNumberFormat($giveXNumber, $cardType, &$outErrorMessage)
    {

        // Ensure not empty
        if (empty($giveXNumber)) {
            
            if ($cardType === self::CARD_TYPE_GIFT) {

                $outErrorMessage = 'Please enter your gift card number.';
            } elseif ($cardType === self::CARD_TYPE_LOYALTY) {

                $outErrorMessage = 'Please enter your offyatree Rewards Membership Card number.';
            }
            return false;
        }

        // Validate format

        // Ensure number is all digits
        if (!ctype_digit($giveXNumber)) {
            if ($cardType === self::CARD_TYPE_GIFT) {
                $outErrorMessage = 'Sorry, the gift card number you entered is incorrect because it contains letters. A gift card number is 17 or 20 digits with no letters.';
            } elseif ($cardType === self::CARD_TYPE_LOYALTY) {
                $outErrorMessage = 'Sorry, the offyatree Rewards Membership Card number you entered is incorrect because it contains letters. A loyalty card number is 17 or 20 digits with no letters.';
            }
            return false;
        }

        // Don't check length as have been getting 17, 20 and 21 digit cards, so don't trust
        // what we've been told about lengths.

        // Based on the card type, ensure the card starts with an allowed prefix
        $validPrefixes = [];
        if ($cardType === self::CARD_TYPE_GIFT) {

            $validPrefixes = '';//Mage::getStoreConfig('givex_options/givex_number_validation/gift_card_prefixes');
        } elseif ($cardType === self::CARD_TYPE_LOYALTY) {
            $validPrefixes = $this->_membershipConfigHelper->getMembershipCardPrefixFormat();
        }
        $validPrefixes = explode(',', $validPrefixes);
        foreach ($validPrefixes as $i => &$validPrefix) {

            $validPrefix = trim(str_replace(' ', '', $validPrefix));
            if (empty($validPrefix)) {

                unset($validPrefixes[$i]);
            }
        }
        unset($validPrefix);

        if (!empty($validPrefixes)) {

            $isValid = false;
            foreach ($validPrefixes as $validPrefix) {

                if (strpos($giveXNumber, $validPrefix) === 0) {

                    $isValid = true;
                    break;
                }
            }
            if (!$isValid) {
                
                if ($cardType === self::CARD_TYPE_GIFT) {
                    $outErrorMessage = 'Sorry, the card number you entered starts with incorrect digits for a gift card. Maybe you entered your offyatree Rewards Membership Card number by mistake?';
                }
                return false;
            }
        }

        // Valid
        return true;
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
        // The GiveX API fails if a param value contains Word-style characters such as O’Donnell. It might be an issue within the
        // XMLRPC library we use, or that we're not correctly configuring that library. Either way, simply fix is to (safely) convert
        // these characters to their boring equivalent.
        // Source: https://stackoverflow.com/a/1262210
        $quotes = [
            "\xC2\xAB"     => '"', // « (U+00AB) in UTF-8
            "\xC2\xBB"     => '"', // » (U+00BB) in UTF-8
            "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
            "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
            "\xE2\x80\x9A" => ",", // ‚ (U+201A) in UTF-8
            "\xE2\x80\x9B" => "'", // ‛ (U+201B) in UTF-8
            "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
            "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
            "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
            "\xE2\x80\x9F" => '"', // ‟ (U+201F) in UTF-8
            "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
            "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8

            "\xE2\x80\xAD" => "", // LEFT-TO-RIGHT OVERRIDE (U+202D) in UTF-8
            "\xE2\x80\xAE" => "", // RIGHT-TO-LEFT OVERRIDE (U+202E) in UTF-8
        ];
        foreach ($params as $param) {

            // Note we also want to trim everything sent to GiveX
            $param = trim(strtr($param, $quotes));

            $xmlrpcParameters[] = new \xmlrpcval($param, 'string');
        }
     
        return new \xmlrpcmsg($callId, $xmlrpcParameters);
    }

    /**
     * Performs an API call to GiveX. This implements GiveX's failover process if a transactionCode is provided.
     * @param $request
     * @param $reversalRequest
     * @return array|bool
     */
    private function call($request, $reversalRequest, &$logger = null)
    {
        $outErrorMessage = null;

        // Try primary server
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . 'Trying primary API URL...'.$this->config['primaryApiUrl'].$this->config['portA']);
        $client = $this->getClient($this->config['primaryApiUrl'].$this->config['portA']);
        $response = $client->send($request, self::GIVEX_FAILOVER_TIMEOUT_SECONDS);
        
        // If response OK, done
        if ($response->errno === 0) {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Response received.');
            return $this->parseResponse($response);
        }

        // Failed. Probably timeout.

        // Send reversal if provided. Can ignore response.
        if ($reversalRequest !== null) {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Sending primary reversal.");
            $reversalResponse = $client->send($reversalRequest, self::GIVEX_FAILOVER_TIMEOUT_SECONDS);
            if ($reversalResponse->errno === 0) {

                $reversalResponse = $this->parseResponse($reversalResponse);
            }
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Primary reversal response:", (array)$reversalResponse);
        } else {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Primary reversal request not required.');
        }

        // Failed. Probably timeout.

        // Send reversal if provided. Can ignore response.
        if ($reversalRequest !== null) {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Sending secondary reversal.");
            
            $reversalResponse = $client->send($reversalRequest, self::GIVEX_FAILOVER_TIMEOUT_SECONDS);
            if ($reversalResponse->errno === 0) {
                $reversalResponse = $this->parseResponse($reversalResponse);
            }
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Secondary reversal response:", (array)$reversalResponse);
        } else {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Secondary reversal request not required.');
        }
         
        // All failed
        return false;
    }

    function parseResponse($response)
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

    /**
     * Is loyalty number already attached to a Magento customer account
     *
     * @param $givex_number
     * @return array|bool
     */
    public function isLoyaltyNumberAttached($givex_number)
    {
        $givexNumber = '';

        $customerCollection = $this->_customerCollection->addAttributeToFilter('givex_number', $givex_number)->load();
        
        $customerData = $customerCollection->getData();
        if ($customerData) {
            $givexNumber = $customerData[0]['givex_number'];
        }

        if ($givexNumber) {
            return true;
        } else {
            return false;
        }
    }
    
    public function parseResponseError($response, &$outErrorMessage)
    {
        // We only check for error codes we can provide a meaningful response to. Otherwise allow the
        // catch-all at the end handle it.
        if ($response[1] === '2') {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Invalid loyalty card number.');
            $outErrorMessage = 'Sorry, we cannot find your offyatree Rewards Membership Card number in our database. Please confirm the card number or contact us for help.';
        } elseif ($response[1] === '4') {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Required field missing.');
            $outErrorMessage = "Sorry, there was a validation error. {$response[2]}."; // E.g. "Postal code is required"
        } elseif ($response[1] === '40') {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Loyalty card not activated.');
            $outErrorMessage = 'Sorry, your offyatree Rewards Membership Card has not been activated. Please contact us for help.';
        } else {
        // Catch all
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Unknown error.', $response);

                $outErrorMessage = 'Sorry, there was a problem. ' . self::DEFAULT_FRIENDLY_ERROR_MESSAGE;
            
        }
        return false;
    }

    /**
     * Returns an associative array of the fields you should populate as best as possible with customer data.
     * Pass this to create and update customer methods.
     *
     * Note: country and province have specific validation rules by GiveX.
     *
     * @return array
     */
    public function getBlankCustomer()
    {
        $fields = [
            'firstname' => '',
            'lastname' => '',
            'gender' => '', // Optional
            'birthdate' => '', // Optional. YYYY-MM-DD
            'address' => '',
            'address2' => '',
            'city' => '',
            'state' => '', // Use Magento region code. E.g. WA, ACT
            'country' => '', // Magento address country_id is suitable. I.e. AU or NZ
            'postcode' => '',
            'phone' => '', // Optional
            'promotion_opt_in' => 'true',
            'email' => '',
            'company' => '',
        ];
        return $fields;
    }
    /**
     * Given a state name from Magento, returns the province required by GiveX, as they have some weird formatting.
     * @param $magentoRegionCode string
     * @return string
     */
    public function getGivexProvince($magentoRegionCode)
    {

        $value = $magentoRegionCode;
        if ($value === 'NT') {

            $value = 'NT:AU';
        } elseif ($value === 'WA') {

            $value = 'WA:AU';
        }

        if ($this->logger != null) {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog("Magento region code '{$magentoRegionCode}' converted to GiveX province '{$value}'.");
        }
        return $value;
    }

    /**
     * Create a new CWS customer with GiveX using the provided customer details.
     *
     * On success, returns the customers login. This is because 1) you already knew their GiveX number to call this,
     * and 2) it's their login you need to be able to update them, not their existing customer number.
     *
     * @param $giveXNumber string The number to associate with this customer. // TODO: To confirm, should be an inactive loyalty card.
     * @param $customerData array Pass in a populated array from getBlankCustomer().
     * @param $outErrorMessage
     * @param $outGiveXCustomerId
     * @return bool|string The customer login on success, otherwise false.
     */
    public function createCustomer($giveXNumber, array $customerData, &$outErrorMessage, &$outGiveXCustomerId, &$logger = null)
    {
        if (!empty($logger)) {
            $this->logger = $logger;
        }

        // Init
        $this->init();
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Create customer with GiveX number '{$giveXNumber}'.");
        
        // Clean and validate inputs

        $giveXNumber = $this->cleanGiveXNumber($giveXNumber);
        if (!$this->validateLoyaltyCardNumberFormat($giveXNumber, $outErrorMessage)) {

            return false;
        }

        $customerData['state'] = $this->getGivexProvince($customerData['state']);

        $customerLogin = $customerData['email'];
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Customer login derived from email: '{$customerLogin}'.");

        $customerPassword = uniqid();
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Customer password generated: '{$customerPassword}'.");

        if (empty($customerData['gender'])) {

            $customerData['gender'] = 'N/A';
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Gender defaulted to: '{$customerData['gender']}'.");
        }
        if (empty($customerData['phone'])) {

            $customerData['phone'] = '0';
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Phone defaulted to: '{$customerData['phone']}'.");
        }

        // Make request
        $params = [
            'en', // Language code
            $this->getNewTransactionCode('dc_946'), // Transaction code (of our choosing)
            $this->config['userId'], // User ID
            $this->config['password'], // Password
            $giveXNumber,
            'customer',
            $customerLogin, //customerLogin
            '', //customerTitle (*optional)
            $customerData['firstname'], //customerFirstName
            '', //customerMiddleName (*optional)
            $customerData['lastname'], //customerLastName
            $customerData['gender'], //customerGender (*optional)
            $customerData['birthdate'], //customerBirthdate (*optional)
            $customerData['address'], //customerAddress
            $customerData['address2'], //customerAddress2
            $customerData['city'], //customerCity
            $customerData['state'], //customerProvince
            '', //customerCounty
            $customerData['country'], //customerCountry
            $customerData['postcode'], //customerPostalCode
            $customerData['phone'], //customerPhone
            '0', //customerDiscount (*optional)
            $customerData['promotion_opt_in'], //promotionOptIn (*optional)
            $customerData['email'], //customerEmail(optional)
            $customerPassword, //customerPassword
            '', //customerMobile(optional)
            $customerData['company'], //customerCompany(optional)
            '', //securityCode(optional)
            '', //newCardRequest(optional)
            'false', //promotionOptInMail(optional)
            '', //Member Type (optional)
            '', //customerLangPref(optional)
            '', //Message Type (optional)
            '', //Message Delivery Method (optional)
        ];
        $request = $this->getRequest('dc_946', $params);
        $response = $this->call($request, null, $logger);
        if ($response === false) {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Call failed.');
            $outErrorMessage = self::DEFAULT_FRIENDLY_ERROR_MESSAGE;
            return false;
        }

        // Parse response
        // Success
        if ($response[1] === '0') {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Customer created with GiveX Customer ID '{$response[2]}'.");
            $outGiveXCustomerId = $response[2];
            return $customerLogin;
        } else {

            $this->parseResponseError($response, $outErrorMessage);
            return false;
        }
    }

    /**
     * Updates an existing CWS customer with GiveX using the provided customer details.
     *
     * @param string $customerLogin The customer's GiveX username. You'll have set this when creating the customer.
     *         Otherwise if offyatree did that instore, it should be their email address.
     * @param array $customerData Pass in populated array from getBlankCustomer() You need to populate all fields,
     *         including ones you want to stay the same.
     * @return bool If or not the update was successful.
     */
    public function updateCustomer($customerLogin, array $customerData, &$logger = null)
    {

        if (!empty($logger)) {
            $this->logger = $logger;
        }

        // Init
        $this->init();
        
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Update customer '{$customerLogin}'.");

        // Clean and validate inputs
        $customerData['state'] = $this->getGivexProvince($customerData['state']);
        if (empty($customerData['gender'])) {

            $customerData['gender'] = 'N/A';
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Gender defaulted to '{$customerData['gender']}'.");
        }
        if (empty($customerData['phone'])) {

            $customerData['phone'] = '0';
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Phone defaulted to '{$customerData['phone']}'.");
        }

        // address, city, postal, country, county, province(optional)
        $city = (!empty($customerData['city'])) ? $customerData['city'] : ' ';
        if (strtoupper($customerData['country']) === 'NZ') {
            $customerData['state'] = '';
        }
        $address = "{$customerData['address']}|{$city}|{$customerData['postcode']}|{$customerData['country']}||{$customerData['state']}";
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Address formatted: " . $address);

        $params = [
            'en', // Language code
            $this->getNewTransactionCode('dc_941'), // Transaction code (of our choosing)
            $this->config['userId'], // User ID
            $this->config['password'], // Password
            $customerLogin, //givexNumber
            '', //memberTitle
            !empty($customerData['firstname']) ? $customerData['firstname'] : ' ', //memberFirstname
            '', //memberMiddleName
            !empty($customerData['lastname']) ? $customerData['lastname'] : ' ', //memberLastname
            $address, //memberAddress
            !empty($customerData['phone']) ? $customerData['phone'] : ' ', //memberMobile
            !empty($customerData['email']) ? strtolower($customerData['email']) : '', //memberEmail
            !empty($customerData['birthdate']) ? $customerData['birthdate'] : '', //memberBirthday
            // SMS contact answer (optional)
            // Email contact answer (optional)
            // Mail contact answer (optional)
            // Member phone (optional)
            // Referring member number (optional)
            // Security Code (optional)
            // Member Type (optional)
            // Member Status (optional)
            // Message Type (optional)
            // Message Delivery Method (optional)
        ];

        $request = $this->getRequest('dc_941', $params);
        $response = $this->call($request, null);

        if ($response === false) {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Call failed.');
            return false; // More likely network error
        }

        // Parse response
        // Success
        if ($response[1] === '0') {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Customer updated. ");
            return true;
        } else {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Failed to update customer. Response:", $response);
            return false; // More likely validation error
        }
    }

    /**
     * Records the given SKU list against the given GiveX account.
     * @param $givexNumber string The customer's GiveX number.
     * @param $skuList string A SKU list in the format sku,unitPrice,qty|sku,unitPrice,qty. E.g. 500123,19.99,2
     * @return mixed False on error, otherwise the transaction reference.
     */
    public function sendOrder($givexNumber, $skuList, &$logger = null)
    {
        // Init
        $this->init();
        if (!empty($logger)) {
            $this->logger = $logger;
        }

        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Sending order.");

        // Make request
        $params = [
            'en', // Language code
            $this->getNewTransactionCode('dc_911'), // Transaction code (of our choosing)
            $this->config['userId'], // User ID
            $this->config['password'], // Password
            $givexNumber,
            '',
            '',
            '',
            $skuList,
        ];
        $request = $this->getRequest('dc_911', $params);
        $reversalRequest = $this->getRequest('dc_930', $params);
        $response = $this->call($request, $reversalRequest);
        if ($response === false) {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Call failed.');
            return false; // More likely network error
        }

        // Parse response
        // Success
        if ($response[1] === '0') {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Order sent. Response-".$response[2]);
            return $response[2];
        } elseif ($response[1] === '28') {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Certificate cancelled. ", $response);
            return -1;
        } else {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Error sending order. ", $response);
            return false; // More likely validation error
        }
    }
}
