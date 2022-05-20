<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

namespace Eighteentech\Givex\Cron;

use Eighteentech\Givex\Helper\Config;
use Eighteentech\Givex\Logger\Logger;
use Eighteentech\Givex\Helper\FulfilmentJobImporter;
use Eighteentech\Givex\Helper\GivexConstants;

class GivexFulfilment
{

     /**
      * @var FulfilmentJobImporter
      */
    private $fulfilmentJobImporter;
    
     /**
      * @var Config
      */
    private $configHelper;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @param FulfilmentJobImporter $fulfilmentJobImporter
     * @param Config $configHelper
     * @param Logger $logger
     */
    public function __construct(
        FulfilmentJobImporter $fulfilmentJobImporter,
        Config $configHelper,
        Logger $logger
    ) {
        $this->fulfilmentJobImporter = $fulfilmentJobImporter;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }
    
    /**
     * Execute cron method
     */
    public function execute()
    {
        $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);
        if ($this->configHelper->getGeneralConfig('enable')) {
            $this->logger->writeLog('----------------------------');
            $this->logger->writeLog('Cron Givex Fulfilment Start');
            $this->logger->writeLog('----------------------------');
            $this->fulfilmentJobImporter->initProcess();
            $this->logger->writeLog('------------------------------');
            $this->logger->writeLog('Cron Givex Fulfilment  End');
            $this->logger->writeLog('------------------------------');
        } else {
            $this->logger->writeLog('Module is disabled.', 'error');
        }
        return true;
    }
}
