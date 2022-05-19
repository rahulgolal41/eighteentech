<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

declare(strict_types=1);

namespace Eighteentech\Givex\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\GiftCardAccount\Model\Giftcardaccount;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\GiftCardAccount\Block\Checkout\Cart\Total;
use Eighteentech\Givex\Helper\Giftcard;
use Magento\Checkout\Model\Cart;

/**
 * Observer to validate the GiftCards value
 *
 * @package    Eighteentech
 * @subpackage Givex
 * @author     18th Digitech
 */
class GiftCardsPreAuth implements ObserverInterface
{
      /**
       * var@ \Magento\Framework\Serialize\Serializer\Json
       */
    protected $serializer;
    
    /**
     * @var \Eighteentech\Givex\Helper\Giftcard
     */
    protected $giftcardHelper;
    
    /**
     * @var \Magento\GiftCardAccount\Block\Checkout\Cart\Total
     */
    protected $totalGiftCards;

   /**
    * @var \Magento\Checkout\Model\Cart
    */
    protected $cart;

    /**
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     * @param \Magento\GiftCardAccount\Block\Checkout\Cart\Total $totalGiftCards
     * @param \Eighteentech\Givex\Helper\Giftcard $giftcardHelper
     * @param \Magento\Checkout\Model\Cart $cart
     */
    public function __construct(
        Json $serializer,
        Total $totalGiftCards,
        Giftcard $giftcardHelper,
        Cart $cart
    ) {
                $this->serializer = $serializer;
                $this->totalGiftCards = $totalGiftCards;
        $this->giftcardHelper = $giftcardHelper;
        $this->cart = $cart;
    }
        
        /**
         * Before checkout submit cancle preauth and generate new preauth and save into quote
         */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quote = $observer->getQuote();
        $quoteId = $quote->getId();
        $cards = $this->totalGiftCards->getQuoteGiftCards($quoteId);
        $quoteSubtotal = $quote->getSubtotal();
        $shippingAmount = $this->cart->getQuote()->getShippingAddress()->getShippingAmount();
        $quoteGrandtotal = $quoteSubtotal + $shippingAmount;
        try {
            if (empty($quote->getId())) {
                return $this;
            }
        
            if ($cards && is_array($cards)) {
                $preauthCodeArr = [];
                $preAuthorizedCodes = [];
                
                if (!empty($quote->getGivexGiftcardPreauthCode())) {
                    $preAuthorizedCodes = $this->serializer->unserialize($quote->getGivexGiftcardPreauthCode());
                    $quote->setGivexGiftcardPreauthCode(null);
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
                    // cards applied
                    $cardApplied = 0;
                    if ($quoteGrandtotal > 0) {
                        $cardCode = $option['c'];
                        $cardStoreBalance = $option['ba'];
                        $cardApplied      = min($quoteGrandtotal, $cardStoreBalance);
                        $quoteGrandtotal    -= $cardApplied;
                        
                    }
                    //Pre-auth
                    $outErrorMessage = null;
                    $preauthCode = null;
                    $preauthCode = $this->giftcardHelper->preAuth(
                        $option['c'],
                        $cardApplied,
                        $quote->getId()
                    );
               
                    // If pre-auth failed, add error to session and stop
                    if (empty($preauthCode)) {
                        $quote->setGivexGiftcardPreauthCode($this->serializer->serialize($preauthCodeArr));
                        $quote->save();
                        throw new LocalizedException(__('Error: Unable to pre-authorise gift card. Reason: %1', $outErrorMessage));
                    }
                    
                    $preauthCodeArr[$key] = $preauthCode;
                }
                
                // Save pre-auth details against quote (order not created yet)
                $quote->setGivexGiftcardPreauthCode($this->serializer->serialize($preauthCodeArr));
                $quote->save();
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            throw new LocalizedException(__('Something went wrong!'));
        }
        return $this;
    }
}
