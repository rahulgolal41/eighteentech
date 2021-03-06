<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */

namespace Eighteentech\Givex\Logger;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem;
use Eighteentech\Givex\Helper\GivexConstants;
use Magento\Framework\Filesystem\Io\File as IoFile;

class Logger
{
    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $timezone;
    
    /**
     * @var \Magento\Framework\Filesystem\Driver\File
     */
    protected $_fileDriver;
    
    /**
     * @var Filesystem
     */
    protected $file;
    
    /**
     * @var WriteInterface
     */
    protected $newDirectory;
    
     /**
      * @var IoFile
      */
    protected $ioFile;
    
    /**
     * @var WriteInterface
     */
    protected $logger;

    public function __construct(
        TimezoneInterface $timezone,
        File $fileDriver,
        Filesystem $file,
        IoFile $ioFile
    ) {
        $this->timezone = $timezone;
        $this->_fileDriver = $fileDriver;
        $this->ioFile      = $ioFile;
        $this->newDirectory = $file->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    public function writeLog($message, $type = 'info', $name = 'givex')
    {
       
        if (!$this->logger) {
            $this->initLog($name);
        }
        switch ($type) {
            case 'info':
                $this->logger->info($message);
                break;
            case 'warn':
                $this->logger->warn($message);
                break;
            case 'error':
                $this->logger->err($message);
                break;
            default:
                $this->logger->info($message);
                break;
        }
    }
    
    public function initLog($name = 'givex')
    {
        $today = $this->timezone->date()->format('d-m-Y');
        $logPath = BP .   '/var'.GivexConstants::GIVEX_LOG_DIR . $today;
        
        if (!$this->isFileExist($logPath)) {
            $this->createDirectory($logPath);
        }
        
        $writer = new \Zend_Log_Writer_Stream($logPath . '/' . $name . '.log');
        $this->logger = new \Zend_Log();
        $this->logger->addWriter($writer);
        return $this->logger;
    }
    
    public function isFileExist($fileName)
    {
        return $this->_fileDriver->isExists($fileName);
    }
    
    /**
     * Create new folder
     * @return bool
     * @throws LocalizedException
     */
    public function createDirectory($directoryName)
    {
        $newDirectory = false;
        try {
            $newDirectory = $this->ioFile->mkdir($directoryName, 0775);
        } catch (FileSystemException $e) {
            return false;
        }
        return $newDirectory;
    }
}
