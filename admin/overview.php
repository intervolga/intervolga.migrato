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

/**
 * Значение ячейки или прочерк
 *
 * @param string $value
 *
 * @return string
 */
function migratoCell($value)
{
	$value = (string)$value;

	return $value === '' ? '&mdash;' : htmlspecialcharsbx($value);
}

$path = Overview::normalizePath($_REQUEST['path'] ?? '');
if (!Overview::isPathExists($path))
{
	$path = '';
}
$level = Overview::getLevel($path);
$xmlId = (string)($_REQUEST['xml_id'] ?? '');
$recordId = (string)($_REQUEST['id'] ?? '');

if ($level === 'records' && ($xmlId !== '' || $recordId !== ''))
{
	require(__DIR__ . '/overview_detail.php');

	return;
}

$APPLICATION->SetTitle(Loc::getMessage(
	'INTERVOLGA_MIGRATO.WEB_OVERVIEW_TITLE',
	array('#PATH#' => $path ? $path : '/')
));

$sTableID = 'tbl_intervolga_migrato_overview';
$oSort = new CAdminSorting($sTableID, 'CODE', 'asc');
$lAdmin = new CAdminList($sTableID, $oSort);

$columns = array(
	'modules' => array('CODE', 'NAME', 'ENTITIES', 'RECORDS'),
	'entities' => array('CODE', 'NAME', 'RECORDS'),
	'records' => array('CODE', 'NAME', 'XML_ID', 'ATTRIBUTES', 'FILE'),
	'option-modules' => array('CODE', 'NAME', 'RECORDS'),
	'option-names' => array('CODE', 'VALUE'),
);
$titles = array(
	'CODE' => $level === 'records'
		? Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_ID')
		: Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_CODE'),
	'NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_NAME'),
	'ENTITIES' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_ENTITIES'),
	'RECORDS' => $level === 'option-modules'
		? Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_OPTIONS')
		: Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_RECORDS'),
	'XML_ID' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_XML_ID'),
	'ATTRIBUTES' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_ATTRIBUTES'),
	'FILE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_FILE'),
	'VALUE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_VALUE'),
);
$headers = array();
foreach ($columns[$level] as $column)
{
	$headers[] = array(
		'id' => $column,
		'content' => $titles[$column],
		'align' => in_array($column, array('ENTITIES', 'RECORDS', 'ATTRIBUTES'), true) ? 'right' : 'left',
		'default' => true,
	);
}
$lAdmin->AddHeaders($headers);

$rows = Helper::isConfigExists() ? Overview::getRows($path) : array();
if ($path !== '')
{
	array_unshift($rows, array(
		'CODE' => '..',
		'NAME' => '',
		'PATH' => Overview::getParentPath($path),
		'TYPE' => Overview::TYPE_UP,
		'IS_ERROR' => false,
		'ERROR' => '',
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

	$isUp = ($item['TYPE'] === Overview::TYPE_UP);
	$isFolder = (!$isUp && (string)$item['PATH'] !== '');
	$url = '';
	if ($isFolder || $isUp)
	{
		$url = Helper::getUrl(Helper::PAGE_OVERVIEW, array('path' => $item['PATH']));
	}
	elseif ($item['TYPE'] === Overview::TYPE_RECORD && ($item['XML_ID'] !== '' || $item['CODE'] !== ''))
	{
		$url = Helper::getUrl(
			Helper::PAGE_OVERVIEW,
			$item['XML_ID'] !== ''
				? array('path' => $path, 'xml_id' => $item['XML_ID'])
				: array('path' => $path, 'id' => $item['CODE'])
		);
	}

	$code = '';
	if ($isUp)
	{
		$code = '<span class="adm-submenu-item-link-icon fileman_icon_folder_up"></span> ';
	}
	elseif ($isFolder)
	{
		$code = '<span class="adm-submenu-item-link-icon fileman_icon_folder"></span> ';
	}
	if ($url)
	{
		$code .= '<a href="' . htmlspecialcharsbx($url) . '">'
			. ($isFolder || $isUp ? '<b>' : '')
			. migratoCell($item['CODE'])
			. ($isFolder || $isUp ? '</b>' : '')
			. '</a>';
	}
	else
	{
		$code .= migratoCell($item['CODE']);
	}
	if ($item['IS_ERROR'] && $item['ERROR'])
	{
		$code .= '<div class="migrato-overview-error">' . htmlspecialcharsbx($item['ERROR']) . '</div>';
	}
	$row->AddViewField('CODE', $code);

	foreach ($columns[$level] as $column)
	{
		if ($column === 'CODE')
		{
			continue;
		}
		if ($isUp)
		{
			$row->AddViewField($column, '');
			continue;
		}
		if ($column === 'XML_ID')
		{
			$row->AddViewField(
				'XML_ID',
				(string)$item['XML_ID'] === ''
					? '<span class="migrato-fail">'
						. Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_NO_XML_ID') . '</span>'
					: htmlspecialcharsbx($item['XML_ID'])
			);
			continue;
		}
		if ($column === 'FILE')
		{
			if ($item['HAS_FILE'])
			{
				$row->AddViewField(
					'FILE',
					'<a href="' . htmlspecialcharsbx(Helper::getFileManViewUrl($item['FILE_PATH'])) . '">'
					. Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_YES') . '</a>'
				);
			}
			else
			{
				$row->AddViewField(
					'FILE',
					'<span class="migrato-overview-empty">'
					. Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_NO') . '</span>'
				);
			}
			continue;
		}
		if ($column === 'VALUE')
		{
			$value = (string)$item['VALUE'];
			$short = mb_strlen($value) > 200 ? mb_substr($value, 0, 200) . '…' : $value;
			$row->AddViewField(
				'VALUE',
				'<span title="' . htmlspecialcharsbx($value) . '">' . migratoCell($short) . '</span>'
			);
			continue;
		}
		$row->AddViewField($column, migratoCell($item[$column] ?? ''));
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
		font-size: 11px;
	}
	.migrato-overview-empty
	{
		color: #8b8b8b;
	}
	.migrato-fail
	{
		color: #c0392b;
		font-weight: bold;
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
