<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * @package   uploaderjumploader
 * @author    Marko Cupic <m.cupic@gmx.ch> & Yanick Witschi <yanick.witschi@certo-net.ch>
 * @license   LGPL
 * @copyright Marko Cupic & Yanick Witschi
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Uploaderjumploader;

/**
 * Class JumpLoader
 *
 * @copyright Marko Cupic & Yanick Witschi
 * @author    Marko Cupic <m.cupic@gmx.ch> & Yanick Witschi <yanick.witschi@certo-net.ch>
 * @package   uploaderjumploader
 */
class JumpLoader extends \FileUpload
{
       /**
        * targetDir
        * @var string
        */
       protected $targetDir;

       /**
        * fileName
        * @var string
        */
       protected $fileName;

       /**
        * fileId
        * @var integer
        */
       protected $fileId;

       /**
        * partitionIndex
        * @var integer
        */
       protected $partitionIndex;

       /**
        * partitionCount
        * @var integer
        */
       protected $partitionCount;

       /**
        * fileLength
        * @var integer
        */
       protected $fileLength;

       /**
        * tmpFilePrefix
        * @var string
        */
       protected $tmpFilePrefix = 'JUMPL_%s_';

       /**
        * tmpDir
        * @var string
        */
       protected $tmpDir = 'system/tmp/';

       /**
        * Initialize the object
        */
       public function __construct()
       {
              // Load user object before calling the parent constructor
              $this->import('BackendUser', 'User');
              $this->import('Files');
              parent::__construct();
              $this->loadLanguageFile('default');

              // Register Hooks
              $GLOBALS['TL_HOOKS']['postUpload'][] = array('JumpLoader', 'cleanTmpFolder');
              $GLOBALS['TL_HOOKS']['postUpload'][] = array('JumpLoader', 'sendMessageToBrowser');

              // Specify upload directory - storage for reconstructed uploaded file
              $this->targetDir = $this->Input->get('pid');

              if ($this->Input->post('FORM_SUBMIT') == 'tl_upload')
              {
                     // Retrieve jumploader-specific request parameters
                     $this->fileName = $_FILES['file']['name'];
                     $this->fileId = $this->Input->post('fileId');
                     $this->partitionIndex = $this->Input->post('partitionIndex');
                     $this->partitionCount = $this->Input->post('partitionCount');
                     $this->fileLength = $this->Input->post('fileLength');

                     // Generate the tempFile - Praefix
                     $this->tmpFilePrefix = sprintf($this->tmpFilePrefix, md5($this->fileId));

                     $this->handleFilePartitions();
              }
       }

       /**
        * Post upload Hook
        */
       public function sendMessageToBrowser()
       {
              echo json_encode(array('messagesString' => $this->getMessages(false, true)));
              exit();
       }

       /**
        * Post upload Hook
        */
       public function cleanTmpFolder()
       {
              foreach (scan(TL_ROOT . '/' . $this->tmpDir) as $source)
              {
                     if (is_file(TL_ROOT . '/' . $this->tmpDir . $source))
                     {
                            $tmpFile = new \File($this->tmpDir . $source);
                            if (false !== strpos($tmpFile->basename, $this->tmpFilePrefix))
                            {
                                   $tmpFile->delete();
                            }
                     }
              }
       }

       /**
        * getMessages from session
        * This Method overwrites the parent method
        */
       public function getMessages($blnDcLayout = false, $blnNoWrapper = false)
       {
              $arrTypes = array('TL_ERROR', 'TL_CONFIRM', 'TL_NEW', 'TL_INFO', 'TL_RAW');
              $strMessages = '';
              foreach ($arrTypes as $strType)
              {
                     $strClass = strtolower($strType);
                     $_SESSION[$strType] = is_array($_SESSION[$strType]) ? array_unique($_SESSION[$strType]) : array();

                     foreach ($_SESSION[$strType] as $strMessage)
                     {
                            if ($strType == 'TL_RAW')
                            {
                                   $strMessages .= $strMessage;
                            }
                            else
                            {
                                   $strMessages .= sprintf('<p class="%s">%s</p>%s', $strClass, $strMessage, "\n");
                            }
                     }
                     $_SESSION[$strType] = array();
              }

              $strMessages = trim($strMessages);

              // Verify upload
              if (!is_file(TL_ROOT . '/' . $this->targetDir . '/' . $this->fileName))
              {
                     $strMessages = sprintf('<p class="tl_gerror">The file %s could not be uploaded.</p>', $this->fileName);
                     $strClass = 'tl_error';
              }

              // Wrapping container
              if (!$blnNoWrapper && $strMessages != '')
              {
                     $strMessages = sprintf('%s<div class="tl_message">%s%s%s</div>%s', ($blnDcLayout ? "\n\n" : "\n"), "\n", $strMessages, "\n", ($blnDcLayout ? '' : "\n"));
              }

              return $strMessages;
       }

       /**
        * Partitioned upload file handler script
        */
       public function handleFilePartitions()
       {
              if (!is_uploaded_file($_FILES['file']['tmp_name']))
              {
                     echo "Perhaps file wasn't uploaded via HTTP POST. Possible file upload attack!";
                     return false;
              }

              $targetFilePath = $this->tmpDir . $this->tmpFilePrefix . md5(session_id() . $this->fileId . $this->partitionIndex);
              if (!$this->Files->move_uploaded_file($_FILES['file']['tmp_name'], $targetFilePath))
              {
                     // Error
                     // echo json_encode(array('messagesString' => '<p class="tl_gerror">Could not load up partition. Error in: ' . __METHOD__ . ' on line: ' . __LINE__ . '.</p>'));
                     exit();
              }
              else
              {
                     // echo json_encode(array('messagesString' => '<p class="tl_confirm">Partition ' . $this->partitionIndex . ' of "' . $this->fileName . '" uploaded successfully!</p>'));
              }

              // Exit script if it is not the last partition
              if ($this->partitionIndex < $this->partitionCount - 1)
              {
                     exit();
              }

              // Check if we have collected all partitions properly
              $allInPlace = true;
              $partitionsLength = 0;
              for ($i = 0; $allInPlace && $i < $this->partitionCount; $i++)
              {
                     $partitionFile = $this->tmpDir . $this->tmpFilePrefix . md5(session_id() . $this->fileId . $i);
                     if (file_exists(TL_ROOT . '/' . $partitionFile))
                     {
                            $partitionsLength += filesize(TL_ROOT . '/' . $partitionFile);
                     }
                     else
                     {
                            $allInPlace = false;
                     }
              }

              /**
               * @todo generiert zu Unrecht Fehler
               */
              // Issue error if last partition uploaded, but partitions validation failed
              if ($this->partitionIndex == $this->partitionCount - 1 && (!$allInPlace || $partitionsLength != intval($this->fileLength)))
              {
                     // echo "Error: Upload validation error";
                     // return;
              }

              // Reconstruct original file if all ok
              if ($allInPlace)
              {
                     // Collect the content of all partitions
                     $content = '';
                     for ($i = 0; $allInPlace && $i < $this->partitionCount; $i++)
                     {
                            // Read partition file
                            $partitionFile = $this->tmpDir . $this->tmpFilePrefix . md5(session_id() . $this->fileId . $i);
                            $partitionFileHandle = new \File($partitionFile);
                            $partContent = $partitionFileHandle->getContent();
                            $partitionFileHandle->close();
                            // Remove partition file from the temp folder
                            $partitionFileHandle->delete();

                            $content .= $partContent;
                     }

                     // Rebuild the original file & store the content of all partitions in $_FILES['file']['tmp_name']
                     $originalfileHandle = fopen($_FILES['file']['tmp_name'], 'r+');
                     @ftruncate($originalfileHandle, 0);
                     @fwrite($originalfileHandle, $content);
                     @fclose($originalfileHandle);
              }
       }

       /**
        * Generate the markup
        * This method overwrites the parent method
        * @return string
        */
       public function generateMarkup()
       {
              $this->import('BackendUser', 'User');

              $objTemplate = new \BackendTemplate('be_jumploader');
              $objTemplate->jarFile = $this->Environment->base . 'system/modules/uploaderjumploader/assets/jumploader_z.jar';

              $url = sprintf('contao/main.php?do=files&act=move&mode=2&pid=%s&id=&rt=%s', $this->targetDir, REQUEST_TOKEN);
              $objTemplate->uploadUrl = $this->Environment->base . $url;

              // PHPSESSIONID & BE_USER_AUTH
              $objTemplate->userCookies = sprintf('PHPSESSID=%s; path=/; %s_USER_AUTH=%s; path=/;', session_id(), TL_MODE, $_COOKIE['BE_USER_AUTH']);

              // Jumploader language-file
              $language = ($this->User->language == "" || $this->User->language == "en") ? 'en' : strtolower($this->User->language);
              if (file_exists(TL_ROOT . '/system/modules/uploaderjumploader/assets/lang/messages_' . $language . '.zip'))
              {
                     $objTemplate->jumploaderLanguageFile = $this->Environment->base . 'system/modules/uploaderjumploader/assets/lang/messages_' . $language . '.zip';
              }
              else
              {
                     $objTemplate->jumploaderLanguageFile = $this->Environment->base . 'system/modules/uploaderjumploader/assets/lang/messages_en.zip';
              }

              // Generate fileNamePattern
              $arrAllowedTypes = trimsplit(',', strtoupper($GLOBALS['TL_CONFIG']['uploadTypes']) . ',' . strtolower($GLOBALS['TL_CONFIG']['uploadTypes']));
              $objTemplate->fileNamePattern = '^.+\.((' . implode(')|(', $arrAllowedTypes) . '))$';

              // Maximum file upload size
              $objTemplate->maxFileSize = $this->getMaximumUploadSize();

              return $objTemplate->parse();
       }

       /**
        * Overwrite this method as apparently jumploader has quite a different $_FILES structure because
        * the applet calls this script for every single file
        * @param string key < contao 3.0.0
        * @return array
        */
       protected function getFilesFromGlobal($strKey = '')
       {
              $arrFiles = array();

              $arrFiles[0] = array
              (
                     'name' => $_FILES['file']['name'],
                     'type' => $_FILES['file']['type'],
                     'tmp_name' => $_FILES['file']['tmp_name'],
                     'error' => $_FILES['file']['error'],
                     'size' => $_FILES['file']['size']
              );
              return $arrFiles;
       }

}