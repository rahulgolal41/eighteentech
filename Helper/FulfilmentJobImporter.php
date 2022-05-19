<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */
 
namespace Eighteentech\Givex\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Locale\CurrencyInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\GiftCardAccount\Model\GiftcardaccountFactory;
use Magento\GiftCard\Model\Giftcard;
use Magento\Store\Model\ScopeInterface;
use Eighteentech\Givex\Helper\GivexConstants;
use Eighteentech\Givex\Logger\Logger;
use Eighteentech\Givex\Helper\Config;

class FulfilmentJobImporter extends AbstractHelper
{
    const TAG = 'GivexFulfilment: ';
    const STATUS_ACTIVE = 1;

    /**
     * @var \Magento\GiftCard\Helper\Data
     */
    private $giftCardData;

    /**
     * @var \Eighteentech\Givex\Logger\Logger
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Api\SortOrderBuilder
     */
    protected $sortOrderBuilder;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Eighteentech\Givex\Model\GivexEcertFulfilment
     */
    protected $givexEcertFulfilmentFactory;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $orderFactory;

    /**
     * @var \Eighteentech\Givex\Helper\Giftcard
     */
    protected $giftcardHelper;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $timezone;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var GiftCardsModel
     */
    protected $giftCardsModel;

    /**
     * @var OrderItemRepository
     */
    protected $orderItemRepository;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var CurrencyInterface
     */
    private $localeCurrency;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var state
     */
    protected $state;

    /**
     * @var givexConfigHelper
     */
    protected $givexConfigHelper;

   /**
    * @param \Magento\Framework\App\Helper\Context $context
    * @param \Magento\GiftCard\Helper\Data $giftCardData
    * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
    * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    * @param \Eighteentech\Givex\Model\GivexEcertFulfilmentFactory $givexEcertFulfilmentFactory
    * @param \Magento\Sales\Model\OrderFactory $orderFactory
    * @param \Eighteentech\Givex\Helper\Giftcard $giftcardHelper
    * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
    * @param \Magento\GiftCardAccount\Model\GiftcardaccountFactory $giftCardsModel
    * @param \Magento\Sales\Api\OrderItemRepositoryInterface $orderItemRepository
    * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
    * @param \Magento\Framework\Locale\CurrencyInterface $localeCurrency
    * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    * @param \Magento\Framework\App\State $state
    * @param \Eighteentech\Givex\Logger\Logger $logger
    * @param \Eighteentech\Givex\Helper\Config $givexConfigHelper
    */

    /**
     * Data constructor.
     */
    public function __construct(
        Context $context,
        \Magento\GiftCard\Helper\Data $giftCardData,
        SortOrderBuilder $sortOrderBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        \Eighteentech\Givex\Model\GivexEcertFulfilmentFactory $givexEcertFulfilmentFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Eighteentech\Givex\Helper\Giftcard $giftcardHelper,
        TimezoneInterface $timezone,
        TransactionFactory $transactionFactory,
        GiftcardaccountFactory $giftCardsModel,
        OrderItemRepositoryInterface $orderItemRepository,
        TransportBuilder $transportBuilder,
        CurrencyInterface $localeCurrency,
        ScopeConfigInterface $scopeConfig,
        State $state,
        Logger $logger,
        Config $givexConfigHelper
    ) {
        
        $this->giftCardData = $giftCardData;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->givexEcertFulfilmentFactory = $givexEcertFulfilmentFactory;
        $this->orderFactory = $orderFactory;
        $this->giftcardHelper = $giftcardHelper;
        $this->timezone = $timezone;
        $this->transactionFactory = $transactionFactory;
        $this->giftCardsModel = $giftCardsModel;
        $this->orderItemRepository = $orderItemRepository;
        $this->transportBuilder = $transportBuilder;
        $this->localeCurrency = $localeCurrency;
        $this->scopeConfig = $scopeConfig;
        $this->state = $state;
        $this->logger = $logger;
        $this->_givexConfigHelper = $givexConfigHelper;
        $this->config['givexreferenceid'] = $this->scopeConfig->getValue('givexconfig/givexapi/givexreferenceid');
        parent::__construct($context);
    }
    
   /**
    * Runs the import process. Orders in any state but cancelled will be imported.
    *
    * Also, if orders contain only digital items, this will mark them as shipped and notify the customer.
    *
    * Then updates any fulfilment jobs in progress whose order has been cancelled.
    */
    public function initProcess()
    {
        $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "=========================");
        $this->logger->writeLog(self::TAG . "Running import.");

        // Import the new fulfilment jobs
        $this->logger->writeLog(self::TAG . "_________________________");
        $this->importNewOrders();

        // For any ecerts that have been imported, send them off.
        $this->logger->writeLog(self::TAG . "_________________________");
        $this->generateAndSendEcerts();
        
        // Done
        $this->logger->writeLog(self::TAG . "Import finished.");
        $this->logger->writeLog(self::TAG . "=========================");
    }
    
    /**
     *
     * Internal methods
     */
    public function importNewOrders()
    {
        
        // Get all paid orders without a fulfilment job ID unless cancelled.
        $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Fetching new orders...");

        $sortOrder = $this->sortOrderBuilder->setField('entity_id')->setDirection(SortOrder::SORT_ASC)->create();
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(
                'givex_fulfilment_job_id',
                '0',
                'eq'
            )
            ->addFilter(
                'status',
                ['processing', 'complete', 'holded'],
                'in'
            )
           ->setSortOrders([$sortOrder])->create();

        $orders = $this->orderRepository->getList($searchCriteria);
        $this->logger->writeLog(self::TAG . "Found {$orders->getSize()} order(s).");
        // Loop over each order
        $counter = 0;
        
        if ($orders->getItems()) {
            foreach ($orders->getItems() as $order) {
                // Counter
                $counter++;
                
                $this->logger->writeLog(self::TAG . "{$counter}) Processing new order #{$order['increment_id']} entity_id: #{$order['entity_id']}");

                // Order state
                $giftCardType = '';
                                
                // Loop over each line item.
                $lineItems = $order->getAllVisibleItems();
                $incrementId = $order->getIncrementId();

                foreach ($lineItems as $lineItem) {

                    // Gift card
                    if ($lineItem['product_type'] === 'giftcard') {

                        $this->logger->writeLog("Gift card found Itemid #{$lineItem['item_id']} Qtyinvoice: {$lineItem->getQtyInvoiced()}");
                        if ($lineItem->getQtyInvoiced()) {
                            // Create the fulfilment job if required
                             $productionOptionsData = $lineItem->getProductOptions();
                             $giftCardType = $productionOptionsData['giftcard_type']; // 0=>egiftcard
                             $giftCardsCodes = $productionOptionsData['giftcard_created_codes'];
                             
                            // Common values for physical and digital gift cards
                            $quantity = intval($lineItem->getQtyInvoiced());
                            $value = number_format($lineItem->getPriceInclTax(), 2, '.', '');
                            $this->logger->writeLog(self::TAG . "{$counter}) Gift card type: {$giftCardType}, Gift card value: \${$value}, Quantity: {$quantity}");
                            // Digital gift card
                            if ($giftCardType == '0') {
                                // Quantity should only ever be 1 when card is digital
                                $this->logger->writeLog(self::TAG . "{$counter}) Digital gift card found with quantity {$quantity}.");
                                if (empty($quantity)) {
                                    $this->logger->writeLog(self::TAG . "{$counter}) Found Empty quantity {$quantity} and skip");
                                    continue;
                                }
                                // Create the ecert fulfilment job
                                for ($i = 0; $i < $quantity; $i++) {
                                        $ecertItem =    $this->givexEcertFulfilmentFactory->create()
                                        ->load($order->getId() . '-' . $lineItem->getItemId() . '-' . $i, 'item_identifier');
                                        $ecertItem->setData('order_id', $order->getId())
                                        ->setData('order_item_id', $lineItem->getItemId())
                                        ->setData('item_identifier', $order->getId() . '-' . $lineItem->getItemId() . '-' . $i)
                                        ->setData('sender_name', htmlentities($order->getCustomerFirstname()))
                                        ->setData('sender_email', htmlentities($productionOptionsData['giftcard_sender_email']))
                                        ->setData('recipient_name', htmlentities($productionOptionsData['giftcard_recipient_name']))
                                        ->setData('recipient_email', htmlentities($productionOptionsData['giftcard_recipient_email']))
                                        ->setData('recipient_message', htmlentities($productionOptionsData['giftcard_message']))
                                        ->setData('item_qty', $quantity)
                                        ->setData('gift_card_value', $value)
                                        ->setData('order_increment_id', $incrementId)
                                        ->setData('giftcard_code', $giftCardsCodes[$i])
                                        ->setData('created_utc', date('d-m-Y H:i:s'));
                                    try {
                                        $ecertItem->save();
                                    } catch (\Exception $e) {
                                        $this->logger->writeLog(self::TAG . "{$counter}) Exception for order #{$order_id}:".$e->getMessage());
                                    }
                                    $this->logger->writeLog(self::TAG . "{$counter}) Saved ecert fulfilment item with ID {$ecertItem->getId()}.");
                                }
                            // Physical gift card
                            } else {
                                $this->logger->writeLog(self::TAG . "{$counter}) digital gift card not found. Gift card type: {$giftCardType}");
                            }
                        }
                    }
                }
                // update
                if ($giftCardType == '0') {
                    $order->setData('givex_fulfilment_job_id', -1);
                    $message = "You will receive confirmation emails shortly for each digital gift card purchased.";
                    $order->addStatusHistoryComment($message)
                        ->setIsVisibleOnFront(true)
                        ->setIsCustomerNotified(true);
                    $order->save();
                    $this->logger->writeLog(self::TAG . "{$counter}) GiveX fulfilment job created.");
                }
            }
        }
    }
   
   /**
    *
    * Generate and send the E-certificate of Giftcard
    */
    public function generateAndSendEcerts()
    {
        // Fetch all incomplete ecert fulfilment jobs
        $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Generate and send eCerts...");
        $jobs = $this->givexEcertFulfilmentFactory->create()
            ->getCollection()
            ->addFieldToFilter('completed_utc', ['null' => true]);
        $this->logger->writeLog(self::TAG . 'Found ' . $jobs->getSize() . ' jobs.');

        // Loop jobs. Check state as we go. I.e. only thing we know at this point is the job wasn't marked complete.
        $orderStoreId = 1;
        $counter = 0;
        $ecertJobIds = [];
        $existItemIds = [];
        $allItemId = [];
        if ($jobs->getSize()) {
            foreach ($jobs as $job) {
                $ecertJobIds[] = $job->getId();
                $counter++;
                $allItemId[$job->getId()] = $job->getOrderItemId();
                if (in_array($job->getOrderItemId(), $existItemIds)) {
                    continue;
                }
                $existItemIds[$job->getId()] = $job->getOrderItemId();

                $this->logger->writeLog(self::TAG . "{$counter}) Processing ecert fulfilment #{$job->getId()}");
                // Generate card
                if (empty($job->getGivexNumber())) {
                    $this->logger->writeLog(self::TAG . "{$counter}) Generating new ecert with GiveX.");
                    
                    // load order
                    $order = $this->orderFactory->create()->load($job->getOrderId());
                    $orderStoreId = $order->getStoreId();

                    $outErrorMessage = '';
                    $giftCards = $this->getCwsGiftData($job, $order);
                    $cardNumber = $this->giftcardHelper->createVirtualGiftCard($giftCards, $outErrorMessage);

                    if ($cardNumber === false) {
                        $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);
                        $this->logger->writeLog(self::TAG . "{$counter}) Error generating ecert. Job has been flagged and skipped: {$outErrorMessage}");
                        $job->setIsError(1);
                        $job->save();
                        continue;
                    } else {
                        $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);

                        // for update the multiple givex code in eCert
                        $givexCodesCount = count($cardNumber);
                        
                        // multiple cards update
                        if ($givexCodesCount > 1) {

                            $jobItem = $this->givexEcertFulfilmentFactory->create()->getCollection()->addFieldToFilter('order_item_id', $job->getOrderItemId());

                            // make array of givexcard number
                            $givexNumber = [];
                            foreach ($cardNumber as $givexCodes) {
                                $givexNumber[] = $givexCodes[1];
                            }
                            
                            // make array of giftcard codes
                            $arrayOfCodes = [];
                            foreach ($jobItem as $giftCardCodes) {
                                    $allGiftCardCodes = $giftCardCodes->getData('giftcard_code');
                                    $arrayOfCodes[] = $allGiftCardCodes;
                            }
                            
                            if ($jobItem->getSize() > 1) {
                                // update ecert
                                $i = 0;
                                foreach ($jobItem as $item) {
                                    $this->logger->writeLog(self::TAG . "{$item->getId()}) New ecert generated: {$givexNumber[$i]}");
                                    $updateGivexCode = $this->givexEcertFulfilmentFactory->create()->load($item->getId());
                                    $updateGivexCode->setGivexNumber($givexNumber[$i]);
                                    $updateGivexCode->setGivexStatus(self::STATUS_ACTIVE);
                                    $updateGivexCode->setCompletedUtc($this->timezone->date()->format('Y-m-d H:i:s'));
                                    $updateGivexCode->save();
                                    $i++;
                                }
                                
                                // update to Magento giftcard
                                if (!empty($arrayOfCodes)) {
                                    $j = 0;
                                    foreach ($arrayOfCodes as $arrayOfCode) {
                                        $cards = $this->giftCardsModel->create()->load($arrayOfCode, 'code');
                                        $cards->setCode($cardNumber[$j][1]);
                                        $cards->setStatus(self::STATUS_ACTIVE);
                                        $cards->save();
                                        $this->logger->writeLog(self::TAG . "{$counter}) Updated GiveX number {$cardNumber[$j][1]} and status for {$arrayOfCode}");
                                        $this->logger->writeLog(self::TAG . "{$counter}) Notifying buyer...");
                                        $amount = number_format($job->getGiftCardValue(), 2, '.', '');
                                        $message = "<p>A gift certificate for \${$amount} has been emailed to {$job->getRecipientName()} at {$job->getRecipientEmail()}";
                                        if (!empty($job->getRecipientMessage())) {
                                            $message .= ", along with your message:</p>";
                                            $message .= "<p><em>" . nl2br($job->getRecipientMessage()) . "</em></p>";
                                        } else {
                                            $message .= "</p>";
                                        }
                                        $j++;
                                    }
                                }
                                
                            }
                            
                        } else {
                            // Single  update givexcode
                            
                            // update to ecert
                            $this->logger->writeLog(self::TAG . "{$job->getId()}) New ecert generated: {$cardNumber[0][1]}");
                            $job->setGivexNumber($cardNumber[0][1]);
                            $job->setGivexStatus(self::STATUS_ACTIVE);
                            $job->setCompletedUtc($this->timezone->date()->format('Y-m-d H:i:s'));
                            $job->save();
                        
                            // update to Magento giftcard
                            $cards = $this->giftCardsModel->create()->load($job->getGiftcardCode(), 'code');
                            $cards->setCode($cardNumber[0][1]);
                            $cards->setStatus(self::STATUS_ACTIVE);
                            $cards->save();
                            $this->logger->writeLog(self::TAG . "{$counter}) Updated GiveX number {$cardNumber[0][1]} and status for {$job->getGiftcardCode()}");
                            $this->logger->writeLog(self::TAG . "{$counter}) Notifying buyer...");
                            $amount = number_format($job->getGiftCardValue(), 2, '.', '');
                            $message = "<p>A gift certificate for \${$amount} has been emailed to {$job->getRecipientName()} at {$job->getRecipientEmail()}";
                            if (!empty($job->getRecipientMessage())) {
                                $message .= ", along with your message:</p>";
                                $message .= "<p><em>" . nl2br($job->getRecipientMessage()) . "</em></p>";
                            } else {
                                $message .= "</p>";
                            }
                            
                        }
                    }
                    
                        // update order status
                        $order->addStatusHistoryComment($message)
                            ->setIsVisibleOnFront(true)
                            ->setIsCustomerNotified(true);
                        $adminMessage = 'eCert card number: ' . $job->getGivexNumber();
                        $order->addStatusHistoryComment($adminMessage)
                            ->setIsVisibleOnFront(false)
                            ->setIsCustomerNotified(false);
                        $this->logger->writeLog(self::TAG . "{$counter}) Buyer notified.");
                            
                } else {
                    $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);
                    $this->logger->writeLog(self::TAG . "{$counter}) GiveX number already generated.");
                }
            }
            
                /*
                 * get all job having is_email_sent status 0
                 *  send email notification
                 */
            if ($ecertJobIds) {
                $emailSend = $this->emailNotification($ecertJobIds, $orderStoreId);
            }
        }
    }
    
    /*
     * get giftcard sender and receipant data
     * return Array
     */
    public function getCwsGiftData($eCertJobData, $orderModel)
    {

        $giftCards = [];
        if ($eCertJobData) {
            $giftCards =[
                        'language'=>'en',
                        'customerorderid'=>$eCertJobData->getOrderId(),
                        'cardamount'=>$eCertJobData->getGiftCardValue(),
                        'orderitemdetailid'=>$eCertJobData->getOrderItemId(),
                        'sendername'=>$eCertJobData->getSenderName(),
                        'senderemail'=>$eCertJobData->getSenderEmail(),
                        'message'=>$eCertJobData->getRecipientMessage(),
                        'recivername'=>$eCertJobData->getRecipientName(),
                        'reviveremail'=>$eCertJobData->getRecipientEmail(),
                        'itemqty'=>$eCertJobData->getItemQty(),
                        'ordertotal'=>$eCertJobData->getItemQty()*$eCertJobData->getGiftCardValue(),
                        'billingid'=>$orderModel->getBillingAddressId()
                    ];
        // use the Reference id if exist in admin config.
            if (isset($this->config['givexreferenceid'])) {
                $giftCards['orderitemdetailid'] = $this->config['givexreferenceid'];
            }
        }
        return $giftCards;
    }

    /*
     * Send gift card email notification
     * return Boolean
     */
    public function emailNotification($jobIds, $orderStoreId)
    {
        $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Init gift card email notification.");
        if ($jobIds) {
                $ecertJobs = $this->givexEcertFulfilmentFactory->create()
                ->getCollection()
                ->addFieldToFilter('id', $jobIds, 'in')
                ->addFieldToFilter('is_email_sent', ['0']);

            if ($ecertJobs->getSize()) {
                $givexNumbers = [];
                $emailSenderReceipantData = [];
                foreach ($ecertJobs as $ecertJob) {
                    $givexNumbers[$ecertJob['order_item_id']][] = $ecertJob['givex_number'];
                    $emailSenderReceipantData[$ecertJob['order_item_id']]['sender_name'] = $ecertJob['sender_name'];
                    $emailSenderReceipantData[$ecertJob['order_item_id']]['sender_email'] = $ecertJob['sender_email'];
                    $emailSenderReceipantData[$ecertJob['order_item_id']]['recipient_name'] = $ecertJob['recipient_name'];
                    $emailSenderReceipantData[$ecertJob['order_item_id']]['recipient_email'] = $ecertJob['recipient_email'];
                    $emailSenderReceipantData[$ecertJob['order_item_id']]['recipient_message'] = $ecertJob['recipient_message'];
                    $emailSenderReceipantData[$ecertJob['order_item_id']]['gift_card_value'] = $ecertJob['gift_card_value'];
                }
                $this->state->setAreaCode(Area::AREA_FRONTEND);
                $generatedCodesCount = count($givexNumbers);
                foreach ($givexNumbers as $itemId => $givexnumber) {
                        $codeList = $this->giftCardData->getEmailGeneratedItemsBlock()
                        ->setCodes($givexnumber)
                        ->setArea(Area::AREA_FRONTEND);
                        $recipientAddress = $emailSenderReceipantData[$itemId]['recipient_email'];
                        $recipientName = $emailSenderReceipantData[$itemId]['recipient_name'];
                        $sender = $emailSenderReceipantData[$itemId]['sender_name'];
                        $senderName = $emailSenderReceipantData[$itemId]['sender_name'];
                        $senderEmail = $emailSenderReceipantData[$itemId]['sender_email'];
                        $giftCardValue = $emailSenderReceipantData[$itemId]['gift_card_value'];
                        $template = 'giftcard_email_template';
                            
                    if ($senderEmail) {
                        $sender = "{$sender} <{$senderEmail}>";
                    }
                        $templateData = [
                        'name' => $recipientName,
                        'email' => $recipientAddress,
                        'sender_name_with_email' => $sender,
                        'sender_name' => $senderName,
                        'gift_message' => $emailSenderReceipantData[$itemId]['recipient_message'],
                        //'is_redeemable' => $isRedeemable,
                        'giftcards' => $codeList->toHtml(),
                        'balance' => $giftCardValue,
                        'is_multiple_codes' => 1 < $generatedCodesCount,
                        ];
                        $emailIdentity = $this->scopeConfig->getValue(
                            Giftcard::XML_PATH_EMAIL_IDENTITY,
                            ScopeInterface::SCOPE_STORE,
                            $orderStoreId
                        );

                        $templateOptions = [
                        'area' => Area::AREA_FRONTEND,
                        'store' => $orderStoreId,
                        ];
                        $transport = $this->transportBuilder->setTemplateIdentifier($template)
                        ->setTemplateOptions($templateOptions)
                        ->setTemplateVars($templateData)
                        ->setFrom($emailIdentity)
                        ->addTo($recipientAddress, $recipientName)
                        ->getTransport();
                        $transport->sendMessage();
                        $this->logger->writeLog(self::TAG . " Givex number(s): ".json_encode($codeList->getData()));
                        $this->logger->writeLog(self::TAG . " Template Data: ".json_encode($templateData));
                        $this->logger->writeLog(self::TAG . " Receipant notified.");
                }
                // update job
                foreach ($ecertJobs as $ecertJob) {
                    $ecertJob->setIsEmailSent(1);
                    $ecertJob->save();
                    $this->logger->writeLog(self::TAG . " Update is_email_sent status to 1 for job id #{$ecertJob['id']}");
                }
                return true;
            } else {
                $this->logger->writeLog(self::TAG . "No job found to send email notification.");
                return false;
            }
        } else {
            $this->logger->writeLog(self::TAG . "No job found.");
            return false;
        }
    }
}
