<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */
namespace Eighteentech\Givex\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Glob;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\App\ResourceConnection;
use Eighteentech\Givex\Helper\GivexConstants;
use Eighteentech\Givex\Logger\Logger;

class CleanLogs extends AbstractHelper
{
     /**
      * @var DirectoryList
      */
    protected $directoryList;
    /**
     * @var File
     */
    protected $file;
        
     /**
      * @var IoFile
      */
    protected $ioFile;
    
     /**
      * @var Filesystem
      */
    protected $filesystem;
    
     /**
      * @var Glob
      */
    protected $glob;
    
    /**
     * @var TimezoneInterface
     */
    protected $timezone;
    
    /**
     *
     * @var ResourceConnection
     */
    protected $resourceConnection;
    
    /**
     * @var Logger
     */
    protected $logger;
       
    /**
     *
     * @param \Magento\Framework\App\Filesystem\DirectoryList $directoryList
     * @param \Magento\Framework\Filesystem\Driver\File $file
     * @param \Magento\Framework\Filesystem\Io\File $ioFile
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Filesystem\Glob $glob
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Eighteentech\Givex\Logger\Logger $logger
     */
    public function __construct(
        DirectoryList $directoryList,
        File $file,
        IoFile $ioFile,
        Filesystem $filesystem,
        Glob $glob,
        TimezoneInterface $timezone,
        ResourceConnection $resourceConnection,
        Logger $logger
    ) {
        $this->directoryList = $directoryList;
        $this->file = $file;
        $this->ioFile = $ioFile;
        $this->filesystem = $filesystem;
        $this->glob = $glob;
        $this->timezone = $timezone;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }
    
    /*
     * init clean DB and File logs
     */
    public function initCleanFileAndDbLogs($cleanDays)
    {
        if ($cleanDays > 0) {
            $this->logger->initLog(GivexConstants::GIVEX_FULFILMENT_LOG_FILENAME);
            $this->logger->writeLog("------------------------------");
            $this->logger->writeLog("Clean File and DB logs Start for Givex");
            $this->logger->writeLog("Clean Day(s):  {$cleanDays}");
            $currentdate = $this->timezone->date()->format('Y-m-d H:i:s');
            $dateBefore = date('d-m-Y', strtotime($currentdate . ' -' . $cleanDays . 'days'));
            $dateTimeBefore = date('Y-m-d H:i:s', strtotime($currentdate . ' -' . $cleanDays . 'days'));
            $this->logger->writeLog("Current date: {$currentdate}");
            $this->logger->writeLog("Datetime Before: {$dateTimeBefore}");
            
            $this->deleteDblog($dateTimeBefore);
            $this->deleteFileLog($dateBefore);
            $this->logger->writeLog("Clean File and DB logs End  for Givex");
            $this->logger->writeLog("------------------------------");
        }
    }
    
    /*
     * Delete the Db log
     */
    private function deleteDblog($dateTimeBefore)
    {
        if ($dateTimeBefore) {
            $connection = $this->getResourceConn();
            
             $this->logger->writeLog("Delete DB log for givex_ecert_fulfilment");
                    $connection->delete(
                        $connection->getTableName(GivexConstants::GIVEX_FULFILMENT_LOG_TABLE),
                        [
                        'created_utc <= ?' => $dateTimeBefore
                        ]
                    );
        }
    }
     
     /*
     * Delete the File log
     */
    private function deleteFileLog($dateBefore)
    {
        $mainDir = BP . '/' . DirectoryList::VAR_DIR . GivexConstants::GIVEX_LOG_DIR;
        $givexDir = scandir($mainDir);
        
        unset($givexDir[0]);
        unset($givexDir[1]);
        $this->logger->writeLog("Deleting File log for Givex");
        foreach ($givexDir as $subDirectory) {
                            
            if (strtotime($subDirectory) <= strtotime($dateBefore)) {
                $this->logger->writeLog("Directory Found: {$subDirectory}");
                // ... and then loop through all files in each sub directory
                foreach ($this->glob->glob($mainDir.$subDirectory.'/*') as $file) {
                    if (!$this->file->isFile($file)) {
                        continue;
                    }
                        $fileInfo = $this->ioFile->getPathInfo($file);
                        $logFileName = $fileInfo['basename'];
                        $this->logger->writeLog("Deleted File {$logFileName}");
                        $this->file->deleteFile($file);
                }
                $this->deleteDirectory($mainDir. $subDirectory);
            }
        }
    }
         
        /**
         * Delete Directory
         * @return Boolean
         */
    private function deleteDirectory($dir)
    {
        if (!$this->file->isExists($dir)) {
            return true;
        }
    
        if (!$this->file->isDirectory($dir)) {
            return $this->file->deleteDirectory($dir);
        }
    
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
    
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return $this->file->deleteDirectory($dir);
    }
        
     /**
      * To get resource connection
      * @return Object
      */
    private function getResourceConn()
    {
        return $this->resourceConnection->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
    }
    
    /**
     * To get root path
     **/
    public function getRootPath()
    {
        return  $this->directoryList->getRoot();
    }
}
