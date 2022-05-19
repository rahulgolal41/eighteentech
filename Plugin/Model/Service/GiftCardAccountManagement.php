<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

declare(strict_types=1);

namespace Eighteentech\Givex\Plugin\Model\Service;

use Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface;
use Magento\GiftCardAccount\Model\Giftcardaccount as GiftCardAccount;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\GiftCardAccount\Model\Spi\GiftCardAccountManagerInterface;
use Magento\GiftCardAccount\Model\GiftcardaccountFactory;
use Magento\Checkout\Model\Session;
use Magento\CustomerBalance\Helper\Data as CustomerBalanceHelper;
use Eighteentech\Givex\Helper\GivexConstants;
use Eighteentech\Givex\Helper\Giftcard;
use Eighteentech\Givex\Helper\Config;
use Eighteentech\Givex\Logger\Logger;

/**
 * Class GiftCardAccountManagement
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GiftCardAccountManagement
{
    /**
     * @var GiftCardsModel
     */
    protected $giftCardsModel;
    
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var \Eighteentech\Givex\Helper\Giftcard
     */
    protected $giftcardHelper;

    /**
     * @var \Eighteentech\Givex\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Eighteentech\Givex\Helper\GivexConstants
     */
    protected $givexConstantsHelper;

    /**
     * @var \Eighteentech\Givex\Logger\Logger
     */
    protected $logger;

    // Constants
    const TAG = 'GiftCardAccountManagement: ';

   /**
    * @param GiftcardaccountFactory $giftCardsModel
    * @param Session $checkoutSession
    * @param Giftcard $giftcardHelper
    * @param Config $configHelper
    * @param Logger $logger
    */
    public function __construct(
        GiftcardaccountFactory $giftCardsModel,
        Session $checkoutSession,
        Giftcard $giftcardHelper,
        Config $configHelper,
        Logger $logger
    ) {
        
        $this->giftCardsModel = $giftCardsModel;
        $this->checkoutSession = $checkoutSession;
        $this->giftcardHelper = $giftcardHelper;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }
    
    /**
     * @inheritDoc
     */
    public function beforeCheckGiftCard(\Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement $subject, $cartId, $giftCardCode)
    {
        if ($this->configHelper->getGeneralConfig('enable') == 1) {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $outErrorMessage = null;
            $outHasPreauth = false;
            if ($giftCardCode) {
                $cardCodeBalance = $this->giftcardHelper->getBalance($giftCardCode, $outErrorMessage, $outHasPreauth);
                if (empty($cardCodeBalance)) {
                    throw new LocalizedException(__('No Such Entity.'));
                }
            }

            $card = $this->giftCardsModel->create()->load($giftCardCode, 'code');
            $balance = $card->getData('balance');

            $this->logger->writeLog(self::TAG . 'Your card balance is:'." ".$balance);

            if (!empty($giftCardCode)) {
                if (isset($balance)) {
                    $givexBalance = $this->giftcardHelper->getBalance($giftCardCode, $outErrorMessage, $outHasPreauth);
                    $this->logger->writeLog(self::TAG . 'Your card balance from givex is:'." ".$givexBalance);

                    if ($givexBalance) {
                        $balanceupdated = $givexBalance;
                        $result = $this->giftCardsModel->create()->load($giftCardCode, 'code');
                        $result->setData('balance', $balanceupdated)->save();
                    }
                }
                if (empty($card->getId())) {
                    $givexBalance = $this->giftcardHelper->getBalance($giftCardCode, $outErrorMessage, $outHasPreauth);
                
                    if ($givexBalance) {
                        $customerQuote = $this->checkoutSession->getQuote();
                        $this->giftcardHelper->createGiftCard(
                            $customerQuote,
                            $giftCardCode,
                            floatval($givexBalance)
                        );
                    }
                }
            }
        }
    }
    
    /**
     * @inheritDoc
     */
    public function beforeSaveByQuoteId(
        \Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement $subject,
        $cartId,
        \Magento\GiftCardAccount\Api\Data\GiftCardAccountInterface $giftCardAccountData
    ) {
        if ($this->configHelper->getGeneralConfig('enable') == 1) {
            $this->logger->initLog(GivexConstants::GIVEX_CHECKOUT_LOG_FILENAME);
            $outErrorMessage = null;
            $outHasPreauth = false;
            $giftCardData = $giftCardAccountData->getGiftCards();
            $giftCardCode = $giftCardData[0];
            
            if ($giftCardCode) {
                $cardCodeBalance = $this->giftcardHelper->getBalance($giftCardCode, $outErrorMessage, $outHasPreauth);
                if (empty($cardCodeBalance)) {
                    throw new LocalizedException(__('No Such Entity.'));
                }
            }
            $card = $this->giftCardsModel->create()->load($giftCardCode, 'code');
            $balance = $card->getData('balance');
        
            $this->logger->writeLog(self::TAG . 'Your card balance is:'." ".$balance);

            if (!empty($giftCardCode)) {
                if (isset($balance)) {
                    $givexBalance = $this->giftcardHelper->getBalance($giftCardCode, $outErrorMessage, $outHasPreauth);
                    $this->logger->writeLog(self::TAG . 'Your card balance from givex is:'." ".$givexBalance);

                    if ($givexBalance) {
                        $balanceupdated = $givexBalance;

                        $result = $this->giftCardsModel->create()->load($giftCardCode, 'code');
                        $result->setData('balance', $balanceupdated)->save();
                    }
                }
                if (empty($card->getId())) {
                    $givexBalance = $this->giftcardHelper->getBalance($giftCardCode, $outErrorMessage, $outHasPreauth);
                    $this->logger->writeLog(self::TAG . 'Your card balance is:'." ".$givexBalance);

                    if ($givexBalance) {
                        $customerQuote = $this->checkoutSession->getQuote();
                        $this->giftcardHelper->createGiftCard(
                            $customerQuote,
                            $giftCardCode,
                            floatval($givexBalance)
                        );
                    }
                }
            }
        }
    }
}
