<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */
 
namespace Eighteentech\Givex\Ui\Component\Listing\Column\Fulfilment;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;

class ViewOrder extends Column
{
    
    /**
     * @var StoreManager
     */
    protected $storeManager;
    
    /**
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param array $components = []
     * @param  array $data = []
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item[$this->getData('name')] = '<a target="_blank" href="' . $this->storeManager->getStore()->getUrl('sales/order/view', ['order_id' => $item['order_id']]) . '">' . $item['order_increment_id'] . '</a>';
            }
        }
        return $dataSource;
    }
}
