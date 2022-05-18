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

/**
 * Wraps up some common methods for validating and sanitising GiveX values.
 */
class GivexValidator extends AbstractHelper
{
    // Constants
    const TAG = 'GivexvalidatorHelper: ';
    const CARD_TYPE_GIFT = 1;
    const CARD_TYPE_LOYALTY = 2;

    /**
     * @var \Eighteentech\Givex\Logger\Logger
     */
    protected $logger;
    
    /**
     * Data constructor.
     *
     * @param Context $context
     * @param \Eighteentech\Givex\Logger\Logger $logger
     */
    public function __construct(
        Context $context,
        \Eighteentech\Givex\Logger\Logger $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
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
        
        // if ($this->logger !== null) {
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "GiveX number '{$originalGiveXNumber}' cleaned: '{$giveXNumber}'");
        // }
        
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
     * Validates the given GiveX number format. Does not query GiveX.
     * @param $giveXNumber
     * @param $cardType int An ID for gift or loyalty.
     * @param $outErrorMessage
     * @return bool
     */
    private function validateCardNumberFormat($giveXNumber, $cardType, &$outErrorMessage)
    {
        // Ensure not empty
        if (empty($giveXNumber)) {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . 'GiveX number blank.');
            if ($cardType === self::CARD_TYPE_GIFT) {

                $outErrorMessage = __('Please enter your gift card number.');
            }
            // else if ($cardType === self::CARD_TYPE_LOYALTY) {

            //     $outErrorMessage = __('Please enter your Card number.');
            // }
            
            return false;
        }

        // Validate format

        // Ensure number is all digits
        if (!ctype_digit($giveXNumber)) {

            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $this->logger->writeLog(self::TAG . "Invalid format for GiveX number: not all digits.");
            if ($cardType === self::CARD_TYPE_GIFT) {

                $outErrorMessage = __('Sorry, the gift card number you entered is incorrect because it contains letters. A gift card number is 17 or 20 digits with no letters.');
            }
            // else if ($cardType === self::CARD_TYPE_LOYALTY) {

            //     $outErrorMessage = __('Sorry, Card number you entered is incorrect because it contains letters. A loyalty card number is 17 or 20 digits with no letters.');
            // }
            
            return false;
        }

        // Don't check length as have been getting 17, 20 and 21 digit cards, so don't trust
        // what we've been told about lengths.

        // Based on the card type, ensure the card starts with an allowed prefix
        $validPrefixes = [];

        if ($cardType === self::CARD_TYPE_GIFT) {

            ////$validPrefixesStr = Mage::getStoreConfig('givex_options/givex_number_validation/gift_card_prefixes');
            $validPrefixesStr = '';
        }
        // else if ($cardType === self::CARD_TYPE_LOYALTY) {

        //     ////$validPrefixesStr = Mage::getStoreConfig('givex_options/givex_number_validation/loyalty_card_prefixes');
        //     $validPrefixesStr = 'loyalty_card';
        // }
        
        $validPrefixes = explode(',', $validPrefixesStr);
        
        if (count($validPrefixes)) {
            foreach ($validPrefixes as $i => &$validPrefix) {

                $validPrefix = trim(str_replace(' ', '', $validPrefix));
                if (empty($validPrefix)) {

                    unset($validPrefixes[$i]);
                }
            }
            
            unset($validPrefix);
        }

        if (!empty($validPrefixes)) {
            $isValid = false;
            
            foreach ($validPrefixes as $validPrefix) {

                if (strpos($giveXNumber, $validPrefix) === 0) {

                    $isValid = true;
                    break;
                }
            }
            
            if (!$isValid) {

                $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
                $this->logger->writeLog(self::TAG . "Invalid format for GiveX number: wrong prefix.");
                if ($cardType === self::CARD_TYPE_GIFT) {

                    $outErrorMessage = __('Sorry, the card number you entered starts with incorrect digits for a gift card. Maybe you entered your wrong Card number by mistake?');
                }
                return false;
            }
        }

        // Valid
        return true;
    }

    /**
     * Cleans the given amount, returning a string representation of a dollars and cents amount. I.e. to 2 decimal places.
     * @param $originalAmount
     * @return string
     */
    public function cleanAmount($originalAmount)
    {
        $amount = number_format(floatval($originalAmount), 2, '.', ''); // Don't have commas as a thousandth separator.
        $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
        $this->logger->writeLog(self::TAG . "Amount '{$originalAmount}' cleaned: '{$amount}'");
        return $amount;
    }
}
