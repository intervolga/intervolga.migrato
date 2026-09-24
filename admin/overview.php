<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Web\Helper;
use Intervolga\Migrato\Tool\Web\Overview;

Loc::loadMessages(__FILE__);

/**
 * @global CMain $APPLICATION
 */
if (!Loader::includeModule('intervolga.migrato'))
{
	require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
	ShowError(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MODULE_NOT_INSTALLED'));
	require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
	die();
}

Helper::checkReadRights();

@set_time_limit(0);

$path = Overview::normalizePath($_REQUEST['path'] ?? '');
if (!Overview::isPathExists($path))
{
	$path = '';
}

$APPLICATION->SetTitle(Loc::getMessage(
	'INTERVOLGA_MIGRATO.WEB_OVERVIEW_TITLE',
	array('#PATH#' => $path ? $path : '/')
));

$sTableID = 'tbl_intervolga_migrato_overview';
$oSort = new CAdminSorting($sTableID, 'NAME', 'asc');
$lAdmin = new CAdminList($sTableID, $oSort);

$lAdmin->AddHeaders(array(
	array(
		'id' => 'NAME',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_NAME'),
		'default' => true,
	),
	array(
		'id' => 'TYPE_NAME',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_TYPE'),
		'default' => true,
	),
	array(
		'id' => 'DESCRIPTION',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_DESCRIPTION'),
		'default' => true,
	),
));

$rows = array();
if (!Helper::isConfigExists())
{
	$rows = array();
}
else
{
	$rows = Overview::getRows($path);
}

if ($path !== '')
{
	array_unshift($rows, array(
		'NAME' => '..',
		'PATH' => Overview::getParentPath($path),
		'TYPE' => 'up',
		'TYPE_NAME' => '',
		'DESCRIPTION' => '',
		'IS_ERROR' => false,
	));
}

$dbResult = new CDBResult();
$dbResult->InitFromArray($rows);
$rsData = new CAdminResult($dbResult, $sTableID);
$rsData->NavStart(max(count($rows), 1), false, 1);

$index = 0;
while ($item = $rsData->NavNext(false))
{
	$row = &$lAdmin->AddRow('row_' . $index++, $item);

	$isFolder = ($item['PATH'] !== '' || $item['TYPE'] === 'up');
	$icon = $isFolder
		? ($item['TYPE'] === 'up' ? 'migrato-icon-up' : 'migrato-icon-folder')
		: 'migrato-icon-item';
	$name = '<span class="migrato-icon ' . $icon . '"></span>';
	if ($isFolder)
	{
		$url = Helper::getUrl(Helper::PAGE_OVERVIEW, array('path' => $item['PATH']));
		$name .= '<a href="' . htmlspecialcharsbx($url) . '"><b>'
			. htmlspecialcharsbx($item['NAME']) . '</b></a>';
	}
	else
	{
		$name .= htmlspecialcharsbx($item['NAME']);
	}
	$row->AddViewField('NAME', $name);
	$row->AddViewField('TYPE_NAME', htmlspecialcharsbx($item['TYPE_NAME']));
	$row->AddViewField(
		'DESCRIPTION',
		$item['IS_ERROR']
			? '<span class="migrato-overview-error">' . htmlspecialcharsbx($item['DESCRIPTION']) . '</span>'
			: htmlspecialcharsbx($item['DESCRIPTION'])
	);

	if ($isFolder)
	{
		$row->AddActions(array(
			array(
				'ICON' => 'view',
				'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_OPEN'),
				'ACTION' => $lAdmin->ActionRedirect(
					Helper::getUrl(Helper::PAGE_OVERVIEW, array('path' => $item['PATH']))
				),
				'DEFAULT' => true,
			),
		));
	}
}

$lAdmin->AddAdminContextMenu(Helper::getPagesMenu(Helper::PAGE_OVERVIEW), false, false);
$lAdmin->CheckListMode();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

if (!Helper::isConfigExists())
{
	CAdminMessage::ShowMessage(array(
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_NO_CONFIG'),
		'DETAILS' => htmlspecialcharsbx(Helper::getConfigRelativePath()),
		'HTML' => true,
	));
}
?>
<style>
	.migrato-icon
	{
		display: inline-block;
		width: 13px;
		height: 11px;
		margin-right: 6px;
		vertical-align: -1px;
		border-radius: 2px;
	}
	.migrato-icon-folder
	{
		background: #f6c85f;
		border: 1px solid #d9a93b;
	}
	.migrato-icon-up
	{
		background: #c6cdd3;
		border: 1px solid #a9b2ba;
	}
	.migrato-icon-item
	{
		width: 7px;
		height: 7px;
		margin-left: 3px;
		margin-right: 9px;
		background: #b3bdc5;
		border-radius: 50%;
	}
	.migrato-overview-error
	{
		color: #c0392b;
	}
	.migrato-overview-hint
	{
		color: #8b8b8b;
		font-size: 11px;
		margin: 0 0 10px;
	}
</style>
<div class="migrato-overview-hint"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_HINT') ?></div>
<?php
$lAdmin->DisplayList();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
