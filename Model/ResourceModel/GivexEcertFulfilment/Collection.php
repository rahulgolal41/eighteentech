<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */
declare(strict_types=1);

namespace Eighteentech\Givex\Model\ResourceModel\GivexEcertFulfilment;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    protected $_idFieldName = 'id';

    protected function _construct()
    {
        $this->_init(
            \Eighteentech\Givex\Model\GivexEcertFulfilment::class,
            \Eighteentech\Givex\Model\ResourceModel\GivexEcertFulfilment::class
        );
    }
}
