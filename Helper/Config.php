<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

namespace Eighteentech\Givex\Helper;

use \Magento\Store\Model\ScopeInterface;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
     
     // general
    const CONFIG_PATH = 'givexconfig/generalconfig';
    const XML_PATH_PRIMARY_API = 'givexconfig/givexapi/givexprimaryapi';
    const XML_PATH_SECONDARY_API = 'givexconfig/givexapi/givexsecondaryapi';
    const XML_PATH_USER_ID = 'givexconfig/givexapi/givexuserid';
    const XML_PATH_PASSWORD = 'givexconfig/givexapi/givexpassword';
    const XML_PATH_PORT_A = 'givexconfig/givexapi/givexporta';
    const XML_PATH_PORT_B = 'givexconfig/givexapi/givexportb';
    const XML_PATH_PORT_C = 'givexconfig/givexapi/givexportc';
    const PHP_PATH = 'givexconfig/server_environment/php_path';
    
    const EXPORT_ORDER_FAILURE_THRESHOLD = 3;
    
    //clearlogs
    const XML_PATH_LOGS_FULLFILENT_CLEAN_DAYS = 'givexconfig/logsconfig/fulfilmentcleantime';
    
    /**
     * Get Config Value
     *
     * @param  $field
     * @param  $storeId
     * @return string
     */
    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get General Config
     *
     * @param  $code
     * @param  storeId
     * @return string
     */
    public function getGeneralConfig($code, $storeId = null)
    {
        return $this->getConfigValue(self::CONFIG_PATH ."/". $code, $storeId);
    }
    
    /*
     * Get Clean days
     * @return string
     */
    public function getGivexCleanLogDays()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_LOGS_FULLFILENT_CLEAN_DAYS, ScopeInterface::SCOPE_STORE);
    }
       
    /*
    * Get Primary API URL
    */
    public function getPrimaryAPIUrl()
    {
        //return 'https://dev-dataconnect.givex.com';
        return $this->scopeConfig->getValue(self::XML_PATH_PRIMARY_API, ScopeInterface::SCOPE_STORE);
    }
    
    /*
    * Get Secondary API URL
    */
    public function getSecondaryAPIUrl()
    {
        //return 'https://dev-dataconnect.givex.com';
        return $this->scopeConfig->getValue(self::XML_PATH_SECONDARY_API, ScopeInterface::SCOPE_STORE);
    }
    
    /*
    * Get Givex userid
    */
    public function getGivexUserId()
    {
        //return '36966';
        return $this->scopeConfig->getValue(self::XML_PATH_USER_ID, ScopeInterface::SCOPE_STORE);
    }
    
    /*
    * Get Givex password
    */
    public function getGivexPassword()
    {
        //return 'CniOcKVf5oY83lFU';
        return $this->scopeConfig->getValue(self::XML_PATH_PASSWORD, ScopeInterface::SCOPE_STORE);
    }

    /*
    * Get PORT A
    */
    public function getPortA()
    {
        //return ':50060';
        return ':'.$this->scopeConfig->getValue(self::XML_PATH_PORT_A, ScopeInterface::SCOPE_STORE);
    }
    /*
    * Get PORT B
    */
    public function getPortB()
    {
        //return ':55060';
        return ':'.$this->scopeConfig->getValue(self::XML_PATH_PORT_B, ScopeInterface::SCOPE_STORE);
    }
    /*
    * Get PORT C
    */
    public function getPortC()
    {
        //return ':56060';
        return ':'.$this->scopeConfig->getValue(self::XML_PATH_PORT_C, ScopeInterface::SCOPE_STORE);
    }
    /*
    * Get PORT D
    */
    public function getPortD()
    {
        return ':55061';
    }
}
