<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */
 
namespace Eighteentech\Givex\Block\Giftcard;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use MSP\ReCaptcha\Model\Validate as ReCaptchaValidate;
use Eighteentech\Givex\Helper\Giftcard as GiftcardHelper;
use Eighteentech\Givex\Helper\GivexConstants;

/**
 * Check Giftcard Balance
 */
class Balance extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Eighteentech\Givex\Logger\Logger
     */
    protected $logger;
    
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\App\Response\RedirectInterface $redirect
     * @param \Magento\Framework\App\Response\Http $response
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \MSP\ReCaptcha\Model\Validate $reCaptchaValidate
     * @param \Eighteentech\Givex\Helper\Giftcard $giftcardHelper
     * @param \Eighteentech\Givex\Logger\Logger $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        RedirectInterface $redirect,
        ResponseHttp $response,
        MessageManager $messageManager,
        ReCaptchaValidate $reCaptchaValidate,
        GiftcardHelper $giftcardHelper,
        \Eighteentech\Givex\Logger\Logger $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        parent::__construct($context, $data);
        
        $balance = 0;
        $outHasPreauth = false;
        $giftcardNumber = trim((string)$this->getRequest()->getParam('giftcard_number'));
        $error = null;

        $this->setBalance($balance);
        $this->setHasPreauth($outHasPreauth);
        $this->setGiftcardNumber($giftcardNumber);
        $this->setError($error);

        if (!empty($giftcardNumber)) {
            // ReCapcha check
            $g_response = trim((string)$this->getRequest()->getParam('g-recaptcha-response'));
            if (isset($g_response) && !empty($g_response)) {
                if (!$reCaptchaValidate->validate($g_response, $this->getRequest()->getClientIp())) {
                    $messageManager->addError(__('Please click on the reCAPTCHA box'));
                    $url = $redirect->getRefererUrl() ? $redirect->getRefererUrl() : $this->getBaseUrl();
                    $response->setRedirect($url);
                    return $response;
                }
            } else {
                $messageManager->addError(__('Please click on the reCAPTCHA box'));
                $url = $redirect->getRefererUrl() ? $redirect->getRefererUrl() : $this->getBaseUrl();
                $response->setRedirect($url);
                return $response;
            }

            // Balance lookup
            $outErrorMessage = null;
            $response = $giftcardHelper->getBalance($giftcardNumber, $outErrorMessage, $outHasPreauth);

            if (!empty($outErrorMessage)) {
                $this->setError($outErrorMessage);
            }
            
            if (!empty($response)) {
                $balance = $response;
            }
        }

        $this->setBalance($balance);
        $this->setHasPreauth($outHasPreauth);
    }
    
    public function getSiteKey()
    {
        return $this->_scopeConfig->getValue('msp_securitysuite_recaptcha/general/public_key');
    }
}
