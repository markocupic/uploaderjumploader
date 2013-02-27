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
       protected $tmpFilePrefix = 'JUMPL_%s';

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
              $this->import('Database');

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
              // Read content of uploaded file
              $tmpFileHandle = @fopen($_FILES['file']['tmp_name'], 'r');
              $content = @stream_get_contents($tmpFileHandle);
              @fclose($tmpFileHandle);

              // add the content of the partition to the tmp-file
              $this->strTmpFile = 'system/tmp/' . $this->tmpFilePrefix;
              $newTmpFile = new \File($this->strTmpFile);
              $oldContent = $newTmpFile->getContent();
              $newContent = $oldContent . $content;
              $newTmpFile->truncate();
              $newTmpFile->write($newContent);
              $newTmpFile->close();

              // Exit script if it is not the last partition
              if ($this->partitionIndex < $this->partitionCount - 1)
              {
                     exit();
              }
       }

       /**
        * Check the uploaded files and move them to the target directory
        * This method overwrites the parent method
        * @param string
        * @return array
        * @throws \Exception
        */
       public function uploadTo($strTarget)
       {
              $arrUploaded = array();

              if ($strTarget == '' || strpos($strTarget, '../') !== false)
              {
                     throw new \Exception("Invalid target path $strTarget");
              }

              if (!file_exists(TL_ROOT . '/' . $this->tmpFile))
              {
                     throw new \Exception("The temporary file '" . $this->tmpFile . "'does not exist.");
              }
              else
              {
                     // Get the files-array from $_FILES
                     $arrFiles = $this->getFilesFromGlobal();
                     $file = $arrFiles[0];

                     // Romanize the filename
                     $file['name'] = strip_tags($file['name']);
                     $file['name'] = utf8_romanize($file['name']);
                     $file['name'] = str_replace('"', '', $file['name']);

                     // check for allowed extension
                     $strExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                     $arrAllowedTypes = trimsplit(',', strtolower($GLOBALS['TL_CONFIG']['uploadTypes']));

                     // File type not allowed
                     if (!in_array(strtolower($strExtension), $arrAllowedTypes))
                     {
                            \Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['filetype'], $strExtension));
                            $this->log('File type "' . $strExtension . '" is not allowed to be uploaded (' . $file['name'] . ')', 'Uploader uploadTo()', TL_ERROR);
                            $this->blnHasError = true;
                     }
                     else
                     {
                            // Copy the file to the selected target and delete the tmp-file
                            $uploadedFile = new \File($this->strTmpFile);
                            $strNewFile = $strTarget . '/' . $file['name'];
                            $uploadedFile->copyTo($strNewFile);
                            $uploadedFile->chmod(0777);
                            $uploadedFile->close();

                            // Set CHMOD and resize if neccessary
                            $this->Files->chmod($strNewFile, $GLOBALS['TL_CONFIG']['defaultFileChmod']);
                            $blnResized = $this->resizeUploadedImage($strNewFile, $file);

                            // Notify the user
                            if (!$blnResized)
                            {
                                   \Message::addConfirmation(sprintf($GLOBALS['TL_LANG']['MSC']['fileUploaded'], $file['name']));
                                   $this->log('File "' . $file['name'] . '" uploaded successfully', 'Uploader uploadTo()', TL_FILES);
                            }

                            $arrUploaded[] = $strNewFile;
                     }
              }
              return $arrUploaded;

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

              // noJavaAlert
              $objTemplate->noJavaAlert = $GLOBALS['TL_LANG']['tl_files']['noJavaAlert'];

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
              if ($strKey === '')
              {
                     $strKey = 'file';
              }

              $arrFiles = array();

              $arrFiles[] = array
              (
                     'name' => $_FILES[$strKey]['name'],
                     'type' => $_FILES[$strKey]['type'],
                     'tmp_name' => $_FILES[$strKey]['tmp_name'],
                     'error' => $_FILES[$strKey]['error'],
                     'size' => $_FILES[$strKey]['size']
              );
              return $arrFiles;
       }

}