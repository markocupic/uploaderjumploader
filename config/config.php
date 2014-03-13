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


// JumpLoader version
@define('JL_VERSION', '2.28.0');

// Register Hooks
$GLOBALS['TL_HOOKS']['postUpload'][] = array('JumpLoader', 'cleanTmpFolder');
$GLOBALS['TL_HOOKS']['postUpload'][] = array('JumpLoader', 'sendMessageToBrowser');

