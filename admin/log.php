<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Intervolga\Migrato\Tool\Web\Helper;

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

/**
 * Максимальное число записей, выбираемых из журнала за один раз
 */
const INTERVOLGA_MIGRATO_LOG_MAX_ROWS = 10000;

if (($_REQUEST['action'] ?? '') === 'clear' && Helper::canWrite() && check_bitrix_sessid())
{
	LogTable::deleteAll();
	LocalRedirect(Helper::getUrl(Helper::PAGE_LOG, array('cleared' => 'Y')));
}

$sTableID = 'tbl_intervolga_migrato_log';
$oSort = new CAdminSorting($sTableID, 'ID', 'desc');
$lAdmin = new CAdminList($sTableID, $oSort);

$filterFields = array(
	'filter_result',
	'filter_command',
	'filter_module',
	'filter_entity',
	'filter_operation',
	'filter_xml_id',
);
$lAdmin->InitFilter($filterFields);

/**
 * @var string $filter_result
 * @var string $filter_command
 * @var string $filter_module
 * @var string $filter_entity
 * @var string $filter_operation
 * @var string $filter_xml_id
 */
$filter = array();
if ($filter_result)
{
	$filter['=RESULT'] = $filter_result;
}
if ($filter_command)
{
	$filter['%COMMAND'] = $filter_command;
}
if ($filter_module)
{
	$filter['%MODULE_NAME'] = $filter_module;
}
if ($filter_entity)
{
	$filter['%ENTITY_NAME'] = $filter_entity;
}
if ($filter_operation)
{
	$filter['%OPERATION'] = $filter_operation;
}
if ($filter_xml_id)
{
	$filter['%DATA_XML_ID'] = $filter_xml_id;
}

$lAdmin->AddHeaders(array(
	array(
		'id' => 'ID',
		'content' => 'ID',
		'sort' => 'ID',
		'default' => true,
	),
	array(
		'id' => 'TIMESTAMP_X',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_TIME'),
		'sort' => 'TIMESTAMP_X',
		'default' => true,
	),
	array(
		'id' => 'COMMAND',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_COMMAND'),
		'sort' => 'COMMAND',
		'default' => true,
	),
	array(
		'id' => 'DATA',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_DATA'),
		'default' => true,
	),
	array(
		'id' => 'DATA_XML_ID',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_XML_ID'),
		'sort' => 'DATA_XML_ID',
		'default' => true,
	),
	array(
		'id' => 'RECORD_ID',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_ID'),
		'default' => true,
	),
	array(
		'id' => 'OPERATION',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_OPERATION'),
		'sort' => 'OPERATION',
		'default' => true,
	),
	array(
		'id' => 'RESULT',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_RESULT'),
		'sort' => 'RESULT',
		'default' => true,
	),
	array(
		'id' => 'COMMENT',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_COMMENT'),
		'default' => true,
	),
));

$by = method_exists($oSort, 'getField') ? $oSort->getField() : ($_REQUEST['by'] ?? 'ID');
$by = strtoupper((string)$by);
$order = method_exists($oSort, 'getOrder') ? $oSort->getOrder() : ($_REQUEST['order'] ?? 'DESC');
$order = strtoupper((string)$order) === 'ASC' ? 'ASC' : 'DESC';
$allowedSort = array('ID', 'TIMESTAMP_X', 'COMMAND', 'DATA_XML_ID', 'OPERATION', 'RESULT');
if (!in_array($by, $allowedSort, true))
{
	$by = 'ID';
}

$totalCount = LogTable::getCount($filter);
$rows = LogTable::getList(array(
	'filter' => $filter,
	'order' => array($by => $order),
	'limit' => INTERVOLGA_MIGRATO_LOG_MAX_ROWS,
))->fetchAll();

$dbResult = new CDBResult();
$dbResult->InitFromArray($rows);
$rsData = new CAdminResult($dbResult, $sTableID);
$rsData->NavStart();
$lAdmin->NavText($rsData->GetNavPrint(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_NAV')));

while ($log = $rsData->NavNext(false))
{
	$row = &$lAdmin->AddRow($log['ID'], $log);

	$time = $log['TIMESTAMP_X'];
	$row->AddViewField('TIMESTAMP_X', $time ? htmlspecialcharsbx((string)$time) : '');

	$data = '';
	if ($log['MODULE_NAME'] && $log['ENTITY_NAME'])
	{
		$data = $log['MODULE_NAME'] . ':' . $log['ENTITY_NAME'];
	}
	elseif ($log['MODULE_NAME'])
	{
		$data = $log['MODULE_NAME'];
	}
	$row->AddViewField('DATA', htmlspecialcharsbx($data));

	if (is_array($log['DATA_ID_COMPLEX']))
	{
		$ids = array();
		foreach ($log['DATA_ID_COMPLEX'] as $key => $value)
		{
			$ids[] = $key . '=' . $value;
		}
		$recordId = implode('; ', $ids);
	}
	else
	{
		$recordId = trim($log['DATA_ID_NUM'] . ' ' . $log['DATA_ID_STR']);
	}
	$row->AddViewField('RECORD_ID', htmlspecialcharsbx($recordId));

	if ($log['RESULT'] === LogTable::RESULT_FAIL)
	{
		$row->AddViewField(
			'RESULT',
			'<span style="color:#c0392b;font-weight:bold;">' . htmlspecialcharsbx($log['RESULT']) . '</span>'
		);
	}
	else
	{
		$row->AddViewField('RESULT', htmlspecialcharsbx((string)$log['RESULT']));
	}

	$comment = (string)$log['COMMENT'];
	$shortComment = explode(PHP_EOL . PHP_EOL, $comment);
	$shortComment = $shortComment[0];
	if ($shortComment !== $comment)
	{
		$row->AddViewField(
			'COMMENT',
			'<span title="' . htmlspecialcharsbx($comment) . '">'
			. htmlspecialcharsbx($shortComment) . ' &hellip;</span>'
		);
	}
	else
	{
		$row->AddViewField('COMMENT', nl2br(htmlspecialcharsbx($comment)));
	}
}

$contextMenu = Helper::getPagesMenu(Helper::PAGE_LOG);
if (Helper::canWrite())
{
	$contextMenu[] = array(
		'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_CLEAR'),
		'LINK' => 'javascript:void(0)',
		'ONCLICK' => 'if (confirm(\'' . CUtil::JSEscape(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_CLEAR_CONFIRM')) . '\')) '
			. 'window.location=\'' . CUtil::JSEscape(Helper::getUrl(
				Helper::PAGE_LOG,
				array('action' => 'clear', 'sessid' => bitrix_sessid())
			)) . '\';',
		'ICON' => 'btn_delete',
	);
}
$lAdmin->AddAdminContextMenu($contextMenu, false);

$lAdmin->CheckListMode();

$APPLICATION->SetTitle(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_TITLE'));

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

if (($_REQUEST['cleared'] ?? '') === 'Y')
{
	CAdminMessage::ShowNote(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_CLEARED'));
}
if ($totalCount > INTERVOLGA_MIGRATO_LOG_MAX_ROWS)
{
	CAdminMessage::ShowMessage(array(
		'TYPE' => 'PROGRESS',
		'MESSAGE' => Loc::getMessage(
			'INTERVOLGA_MIGRATO.WEB_LOG_TOO_MANY',
			array(
				'#TOTAL#' => $totalCount,
				'#SHOWN#' => INTERVOLGA_MIGRATO_LOG_MAX_ROWS,
			)
		),
	));
}

$oFilter = new CAdminFilter(
	$sTableID . '_filter',
	array(
		Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_COMMAND'),
		Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_MODULE'),
		Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_ENTITY'),
		Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_OPERATION'),
		Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_XML_ID'),
	)
);
?>
<form name="find_form" method="GET" action="<?= htmlspecialcharsbx(Helper::PAGE_LOG) ?>">
	<?php $oFilter->Begin(); ?>
	<tr>
		<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_RESULT') ?>:</td>
		<td>
			<select name="filter_result">
				<option value=""><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_ANY') ?></option>
				<?php foreach (array('ok', 'info', 'fail') as $result): ?>
					<option value="<?= $result ?>" <?= ($filter_result === $result ? 'selected' : '') ?>>
						<?= $result ?>
					</option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_COMMAND') ?>:</td>
		<td><input type="text" name="filter_command" size="30"
				value="<?= htmlspecialcharsbx((string)$filter_command) ?>"></td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_MODULE') ?>:</td>
		<td><input type="text" name="filter_module" size="30"
				value="<?= htmlspecialcharsbx((string)$filter_module) ?>"></td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_ENTITY') ?>:</td>
		<td><input type="text" name="filter_entity" size="30"
				value="<?= htmlspecialcharsbx((string)$filter_entity) ?>"></td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_OPERATION') ?>:</td>
		<td><input type="text" name="filter_operation" size="30"
				value="<?= htmlspecialcharsbx((string)$filter_operation) ?>"></td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_LOG_XML_ID') ?>:</td>
		<td><input type="text" name="filter_xml_id" size="30"
				value="<?= htmlspecialcharsbx((string)$filter_xml_id) ?>"></td>
	</tr>
	<?php
	$oFilter->Buttons(array(
		'table_id' => $sTableID,
		'url' => Helper::PAGE_LOG,
		'form' => 'find_form',
	));
	$oFilter->End();
	?>
</form>
<?php
$lAdmin->DisplayList();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
