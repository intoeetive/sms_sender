<?php

if ( ! defined('SMSSENDER_ADDON_NAME'))
{
	define('SMSSENDER_ADDON_NAME',         'SMS Sender');
	define('SMSSENDER_ADDON_VERSION',      '0.1');
}

$config['name']=SMSSENDER_ADDON_NAME;
$config['version']=SMSSENDER_ADDON_VERSION;

$config['nsm_addon_updater']['versions_xml']='http://www.intoeetive.com/index.php/update.rss/303';