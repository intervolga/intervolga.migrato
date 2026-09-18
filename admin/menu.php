<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * @global CMain $APPLICATION
 */
if ($APPLICATION->GetGroupRight('intervolga.migrato') < 'R')
{
	return false;
}

$aMenu = array(
	'parent_menu' => 'global_menu_services',
	'section' => 'intervolga_migrato',
	'sort' => 500,
	'module_id' => 'intervolga.migrato',
	'text' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_SECTION'),
	'title' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_SECTION_TITLE'),
	'icon' => 'util_menu_icon',
	'page_icon' => 'util_page_icon',
	'items_id' => 'menu_intervolga_migrato',
	'items' => array(
		array(
			'text' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_COMMANDS'),
			'url' => 'intervolga_migrato_index.php?lang=' . LANGUAGE_ID,
			'more_url' => array('intervolga_migrato_run.php'),
			'title' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_COMMANDS_TITLE'),
		),
		array(
			'text' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_LOG'),
			'url' => 'intervolga_migrato_log.php?lang=' . LANGUAGE_ID,
			'title' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_LOG_TITLE'),
		),
		array(
			'text' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_SETTINGS'),
			'url' => 'settings.php?lang=' . LANGUAGE_ID . '&mid=intervolga.migrato&mid_menu=1',
			'more_url' => array('intervolga_migrato_config.php'),
			'title' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_SETTINGS_TITLE'),
		),
	),
);

return $aMenu;
