<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

declare(strict_types=1);

namespace Eighteentech\Givex\Controller\Cart;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\GiftCardAccount\Api\Exception\TooManyAttemptsException;
use Magento\GiftCardAccount\Model\Spi\GiftCardAccountManagerInterface;
use Magento\Framework\App\Action\Action;
use Psr\Log\LoggerInterface;
use Eighteentech\Givex\Helper\Giftcard;
use Eighteentech\Givex\Helper\Config;
use Eighteentech\Givex\Helper\GivexConstants;
use Magento\Checkout\Model\Session;
use Magento\GiftCardAccount\Model\GiftcardaccountFactory;

/**
 * Check a gift card account availability.
 */
class Quick extends \Magento\GiftCardAccount\Controller\Cart\QuickCheck
{

   /**
    * Core registry
    *
    * @var \Magento\Framework\Registry
    */
    protected $_coreRegistry = null;

    /**
     * @var GiftCardAccountManagerInterface
     */
    private $management;

    /**
     * @var LoggerInterface
     */
    private $logger;

     /**
      * @var giftcardHelper
      */
    private $giftcardHelper;

    /**
     * @var configHelper
     */
    private $configHelper;

    /**
     * @var CheckoutSession
     */

    private $checkoutSession;

    /**
     * @var GiftCardsModel
     */
    protected $giftCardsModel;

    /**
     * @param Session $checkoutSession
     * @param GiftcardaccountFactory $giftCardsModel
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param ?GiftCardAccountManagerInterface $management = null
     * @param ?LoggerInterface $logger = null
     * @param Giftcard $giftcardHelper
     * @param Config $configHelper
     */
    public function __construct(
        Session $checkoutSession,
        GiftcardaccountFactory $giftCardsModel,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        ?GiftCardAccountManagerInterface $management = null,
        ?LoggerInterface $logger = null,
        Giftcard $giftcardHelper,
        Config $configHelper
    ) {
        parent::__construct($context, $coreRegistry, $management, $logger);
        $this->checkoutSession = $checkoutSession;
        $this->giftCardsModel = $giftCardsModel;
        $this->_coreRegistry = $coreRegistry;
        $this->management = $management
            ?? ObjectManager::getInstance()->get(GiftCardAccountManagerInterface::class);
        $this->logger = $logger ?? ObjectManager::getInstance()->get(LoggerInterface::class);
        $this->giftcardHelper = $giftcardHelper;
        $this->configHelper = $configHelper;
    }

    /**
     * To get the updated cards balance from givex
     */
    public function execute()
    {
      /** @var HttpRequest $request */
        $request = $this->getRequest();
        $this->_coreRegistry->unregister('current_giftcardaccount_check_error');
        $this->_coreRegistry->unregister('current_giftcardaccount');
        if (!$request->isXmlHttpRequest()) {
            throw new NotFoundException(__('Invalid Request'));
        } else {
            try {
                if ($this->configHelper->getGeneralConfig('enable') == 1) {
                    $giftCardCode = trim((string)$this->getRequest()->getParam('giftcard_code'));
                    $outErrorMessage = null;
                    $outHasPreauth = false;
                    if ($giftCardCode) {
                        $cardCodeBalance = $this->giftcardHelper->getBalance($giftCardCode, $outErrorMessage, $outHasPreauth);
                        if (empty($cardCodeBalance)) {
                            $this->_coreRegistry->register(
                                'current_giftcardaccount_check_error',
                                'No Such Entity.'
                            );
                            throw new LocalizedException(__('Your error message'));
                        }
                    }
                    $card = $this->giftCardsModel->create()->load($giftCardCode, 'code');
                    $balance = $card->getData('balance');
                    if (!empty($giftCardCode)) {
                        $card = $this->giftCardsModel->create()->load($giftCardCode, 'code');
                        if (isset($balance)) {
                            $givexBalance = $this->giftcardHelper->getBalance($giftCardCode, $outErrorMessage, $outHasPreauth);

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
                $card = $this->management->requestByCode($request->getParam('giftcard_code', ''));
                $this->_coreRegistry->register('current_giftcardaccount', $card);
            } catch (TooManyAttemptsException $exception) {
                $this->_coreRegistry->register(
                    'current_giftcardaccount_check_error',
                    $exception->getMessage()
                );
            } catch (NoSuchEntityException|\InvalidArgumentException $exception) {
           
                //Will show default error message.
                $this->logger->error($exception);
            } catch (\Throwable $exception) {
           
                //Will show default error message.
                $this->logger->error($exception);
            }
        }
        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }
}
