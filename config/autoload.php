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
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
       'Uploaderjumploader',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Classes
	'Uploaderjumploader\JumpLoader' => 'system/modules/uploaderjumploader/classes/JumpLoader.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'be_jumploader' => 'system/modules/uploaderjumploader/templates',
));
