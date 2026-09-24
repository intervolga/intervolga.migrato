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
$level = Overview::getLevel($path);
$xmlId = (string)($_REQUEST['xml_id'] ?? '');
$isDetail = ($level === 'records' && $xmlId !== '');

if ($isDetail)
{
	require(__DIR__ . '/overview_detail.php');

	return;
}

$APPLICATION->SetTitle(Loc::getMessage(
	'INTERVOLGA_MIGRATO.WEB_OVERVIEW_TITLE',
	array('#PATH#' => $path ? $path : '/')
));

$sTableID = 'tbl_intervolga_migrato_overview';
$oSort = new CAdminSorting($sTableID, 'NAME', 'asc');
$lAdmin = new CAdminList($sTableID, $oSort);

$headers = array(
	array(
		'id' => 'NAME',
		'content' => $level === 'records'
			? Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_XML_ID')
			: Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_NAME'),
		'default' => true,
	),
);
if ($level === 'modules')
{
	$headers[] = array(
		'id' => 'ENTITIES',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_ENTITIES'),
		'align' => 'right',
		'default' => true,
	);
}
if ($level === 'records')
{
	$headers[] = array(
		'id' => 'RECORD_ID',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_ID'),
		'default' => true,
	);
	$headers[] = array(
		'id' => 'ATTRIBUTES',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_ATTRIBUTES'),
		'align' => 'right',
		'default' => true,
	);
}
else
{
	$headers[] = array(
		'id' => 'RECORDS',
		'content' => $level === 'option-modules'
			? Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_OPTIONS')
			: Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_RECORDS'),
		'align' => 'right',
		'default' => true,
	);
	$headers[] = array(
		'id' => 'DESCRIPTION',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_DESCRIPTION'),
		'default' => true,
	);
}
$lAdmin->AddHeaders($headers);

$rows = Helper::isConfigExists() ? Overview::getRows($path) : array();
if ($path !== '')
{
	array_unshift($rows, array(
		'NAME' => '..',
		'PATH' => Overview::getParentPath($path),
		'TYPE' => Overview::TYPE_UP,
		'TYPE_NAME' => '',
		'ENTITIES' => '',
		'RECORDS' => '',
		'RECORD_ID' => '',
		'ATTRIBUTES' => '',
		'DESCRIPTION' => '',
		'IS_ERROR' => false,
	));
}

$dbResult = new CDBResult();
$dbResult->InitFromArray($rows);
$rsData = new CAdminResult($dbResult, $sTableID);
if ($level === 'records' || $level === 'option-names')
{
	$rsData->NavStart();
}
else
{
	$rsData->NavStart(max(count($rows), 1), false, 1);
}
$lAdmin->NavText($rsData->GetNavPrint(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_NAV')));

$index = 0;
while ($item = $rsData->NavNext(false))
{
	$row = &$lAdmin->AddRow('row_' . $index++, $item);

	$isFolder = ($item['PATH'] !== '');
	$isUp = ($item['TYPE'] === Overview::TYPE_UP);
	$url = '';
	if ($isFolder || $isUp)
	{
		$url = Helper::getUrl(Helper::PAGE_OVERVIEW, array('path' => $item['PATH']));
	}
	elseif ($item['TYPE'] === Overview::TYPE_RECORD)
	{
		$url = Helper::getUrl(
			Helper::PAGE_OVERVIEW,
			array('path' => $path, 'xml_id' => $item['NAME'])
		);
	}

	$name = '';
	if ($isUp)
	{
		$name = '<span class="fileman_icon_folder_up"></span> ';
	}
	elseif ($isFolder)
	{
		$name = '<span class="fileman_icon_folder"></span> ';
	}
	if ($url)
	{
		$name .= '<a href="' . htmlspecialcharsbx($url) . '">'
			. ($isFolder || $isUp ? '<b>' : '')
			. htmlspecialcharsbx($item['NAME'])
			. ($isFolder || $isUp ? '</b>' : '')
			. '</a>';
	}
	else
	{
		$name .= htmlspecialcharsbx($item['NAME']);
	}
	$row->AddViewField('NAME', $name);

	if ($level === 'modules')
	{
		$row->AddViewField('ENTITIES', htmlspecialcharsbx((string)$item['ENTITIES']));
	}
	if ($level === 'records')
	{
		$row->AddViewField('RECORD_ID', htmlspecialcharsbx((string)$item['RECORD_ID']));
		$row->AddViewField('ATTRIBUTES', htmlspecialcharsbx((string)$item['ATTRIBUTES']));
	}
	else
	{
		$row->AddViewField('RECORDS', htmlspecialcharsbx((string)$item['RECORDS']));
		$row->AddViewField(
			'DESCRIPTION',
			$item['IS_ERROR']
				? '<span class="migrato-overview-error">' . htmlspecialcharsbx($item['DESCRIPTION']) . '</span>'
				: htmlspecialcharsbx($item['DESCRIPTION'])
		);
	}

	if ($url)
	{
		$row->AddActions(array(
			array(
				'ICON' => 'view',
				'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_OPEN'),
				'ACTION' => $lAdmin->ActionRedirect($url),
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
