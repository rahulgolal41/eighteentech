<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

namespace Eighteentech\Givex\Plugin;

use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\GiftCardAccount\Model\GiftcardaccountFactory;
use Eighteentech\Givex\Helper\Giftcard;
use Eighteentech\Givex\Helper\GivexConstants;
use Eighteentech\Givex\Logger\Logger;

class OrderManagement
{
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var GiftCardsModel
     */
    protected $giftCardsModel;

    /**
     * @var Giftcard
     */
    protected $giftcardHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param GiftcardaccountFactory $giftCardsModel
     * @param Giftcard $giftcardHelper
     * @param Logger $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        GiftcardaccountFactory $giftCardsModel,
        Giftcard $giftcardHelper,
        Logger $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->giftCardsModel = $giftCardsModel;
        $this->giftcardHelper = $giftcardHelper;
        $this->logger = $logger;
    }

    /**
     * After place an order set the status of giftcard to Inactive
     */
    public function afterPlace(
        OrderManagementInterface $subject,
        OrderInterface $result,
        OrderInterface $order
    ) {
        $orderId = $result->getEntityId();
        $orderData = $this->orderRepository->get($orderId);

        foreach ($orderData->getAllItems() as $item) {
            $productData = $item->getProductOptions();
            $product     = $item->getProductType();

            if ($product == 'giftcard') {
                // get all giftcards data
                $options = $item->getProductOptions();
               // get codes of all purchased giftcards
                $giftcardscodes = $options['giftcard_created_codes'];
              
                foreach ($giftcardscodes as $code) {
                 // load giftcard by code
                    $cards = $this->giftCardsModel->create()->load($code, 'code');
                 // get status of giftcards
                    $status = $cards->getStatus();

                    if ($status == self::STATUS_ACTIVE) {
                          // set status to inactive
                          $cards->setStatus('status', self::STATUS_INACTIVE)->save();
                    }
                }
            }
        }
        if ($orderId) {
            $postOrderAuth = $this->giftcardHelper->postauthCompletedOrder($orderData);
        }
        return $result;
    }
}
