<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */
 
namespace Eighteentech\Givex\Controller\Adminhtml\Fulfilment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends \Magento\Backend\App\Action
{

    /**
     * @var PageFactory
     */
    protected $resultPageFactory = false;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((__('GiveX Card Fulfilment')));
        
        $errors = [];
        
        if (count($errors) > 0) {
            $this->messageManager->addError(__('One or more eCert fulfilment jobs failed. Please try running \'Import New Jobs\' again. If this message still appears, please contact support.'));
        }
        return $resultPage;
    }
}
