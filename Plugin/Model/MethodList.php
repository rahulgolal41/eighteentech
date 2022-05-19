<?php
/*
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
*/

namespace Eighteentech\Givex\Plugin\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\GiftCardAccount\Block\Checkout\Cart\Total;
use Eighteentech\Givex\Helper\GivexConstants;
use Eighteentech\Givex\Helper\Giftcard;
use Eighteentech\Givex\Logger\Logger;

class MethodList
{
    /**
     * Serializer
     *
     * var@ \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;
    
    /**
     *
     * @var \Eighteentech\Givex\Helper\Giftcard
     */
    protected $giftcardHelper;

    /**
     *
     * @var \Magento\GiftCardAccount\Block\Checkout\Cart\Total
     */
    protected $totalGiftCards;

    /**
     * @var \Eighteentech\Givex\Logger\Logger
     */
    protected $logger;

    /**
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     * @param \Eighteentech\Givex\Helper\Giftcard $giftcardHelper
     * @param \Magento\GiftCardAccount\Block\Checkout\Cart\Total $totalGiftCards
     * @param \Eighteentech\Givex\Logger\Logger $logger
     */
    public function __construct(
        Json $serializer,
        Giftcard $giftcardHelper,
        Total $totalGiftCards,
        Logger $logger
    ) {
        $this->serializer = $serializer;
        $this->giftcardHelper = $giftcardHelper;
        $this->totalGiftCards = $totalGiftCards;
        $this->logger = $logger;
    }
    
    /**
     * Cancle the pre-Auth
     */
    public function afterGetAvailableMethods(
        \Magento\Payment\Model\MethodList $subject,
        $availableMethods,
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        $quoteId = $quote->getId();
        $cards = $this->totalGiftCards->getQuoteGiftCards();
        
        try {
            if (empty($quote->getId())) {
                return $availableMethods;
            }
            if ($cards && is_array($cards)) {
                $preauthCodeArr = [];
                $preAuthorizedCodes = [];
                if (!empty($quote->getGivexGiftcardPreauthCode())) {
                    $preAuthorizedCodes = $this->serializer->unserialize($quote->getGivexGiftcardPreauthCode());
                    $quote->setGivexGiftcardPreauthCode(null);
                    $quote->setGiftCards(null);
                    $quote->save();
                }
                foreach ($cards as $key => $option) {
                    // If there's an existing pre-auth, cancel it
                    if (!empty($preAuthorizedCodes[$key])) {
                        
                        // Cancel at GiveX
                        $outErrorMessage = '';
                        $this->giftcardHelper->cancelPreAuth(
                            $option['c'],
                            $preAuthorizedCodes[$key],
                            $outErrorMessage,
                            $quote->getId()
                        );
                    }
                }
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'Error message:'." ".$e->getMessage());
        }
        return $availableMethods;
    }
}
