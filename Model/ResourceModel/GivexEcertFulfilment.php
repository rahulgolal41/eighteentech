<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */
declare(strict_types=1);

namespace Eighteentech\Givex\Model\ResourceModel;

class GivexEcertFulfilment extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    protected function _construct()
    {
        $this->_init('givex_ecert_fulfilment', 'id');
    }
}
