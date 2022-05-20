<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

namespace Eighteentech\Givex\Cron;

use Eighteentech\Givex\Helper\Config;
use Eighteentech\Givex\Logger\Logger;
use Eighteentech\Givex\Helper\CleanLogs;
use Eighteentech\Givex\Helper\GivexConstants;

class ClearLogs
{

     /**
      * @var CleanLogs
      */
    private $cleanLogs;
    
     /**
      * @var Config
      */
    private $configHelper;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @param CleanLogs $cleanLogs
     * @param Config $configHelper
     * @param Logger $logger
     */
    public function __construct(
        CleanLogs $cleanLogs,
        Config $configHelper,
        Logger $logger
    ) {
        $this->cleanLogs = $cleanLogs;
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
            $cleanDays = $this->configHelper->getGivexCleanLogDays();
            
            $this->logger->writeLog("------------------------------");
            $this->logger->writeLog("Cron Clean Givex File & DB Log Start");
            $this->logger->writeLog("------------------------------");
            $this->cleanLogs->initCleanFileAndDbLogs($cleanDays);
            $this->logger->writeLog("------------------------------");
            $this->logger->writeLog("Cron Clean Givex File & DB Log End");
            $this->logger->writeLog("------------------------------");
        } else {
            $this->logger->writeLog('Module is disabled.', 'error');
        }
        return $this;
    }
}
