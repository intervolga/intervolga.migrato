<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Web\CommandRunner;
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

$APPLICATION->SetTitle(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_INDEX_TITLE'));

if (!Helper::isConsoleAvailable())
{
	require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
	ShowError(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_NO_SYMFONY'));
	require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
	die();
}

$sTableID = 'tbl_intervolga_migrato_commands';
$oSort = new CAdminSorting($sTableID, 'NAME', 'asc');
$lAdmin = new CAdminList($sTableID, $oSort);

$lAdmin->AddHeaders(array(
	array(
		'id' => 'NAME',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_COLUMN_COMMAND'),
		'sort' => 'NAME',
		'default' => true,
	),
	array(
		'id' => 'DESCRIPTION',
		'content' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_COLUMN_DESCRIPTION'),
		'default' => true,
	),
));

$commands = CommandRunner::getCommands();
$rows = array();
foreach ($commands as $name => $command)
{
	$rows[] = array(
		'NAME' => $name,
		'DESCRIPTION' => $command->getDescription(),
	);
}

$order = method_exists($oSort, 'getOrder') ? $oSort->getOrder() : ($_REQUEST['order'] ?? 'asc');
$isDesc = (strtoupper((string)$order) === 'DESC');
usort(
	$rows,
	function(array $first, array $second) use ($isDesc)
	{
		$result = strcmp($first['NAME'], $second['NAME']);

		return $isDesc ? -$result : $result;
	}
);

$dbResult = new CDBResult();
$dbResult->InitFromArray($rows);
$rsData = new CAdminResult($dbResult, $sTableID);
$rsData->NavStart(max(count($rows), 1), false, 1);

while ($command = $rsData->NavNext(false))
{
	$runUrl = Helper::getUrl(Helper::PAGE_RUN, array('command' => $command['NAME']));
	$row = &$lAdmin->AddRow($command['NAME'], $command);

	$name = '<a href="' . htmlspecialcharsbx($runUrl) . '"><b>'
		. htmlspecialcharsbx($command['NAME']) . '</b></a>';
	if (CommandRunner::isDangerous($command['NAME']))
	{
		$name .= '<div class="migrato-command-danger">'
			. Loc::getMessage('INTERVOLGA_MIGRATO.WEB_COMMAND_DANGEROUS') . '</div>';
	}
	$row->AddViewField('NAME', $name);
	$row->AddViewField('DESCRIPTION', htmlspecialcharsbx($command['DESCRIPTION']));

	if (Helper::canWrite())
	{
		$row->AddActions(array(
			array(
				'ICON' => 'edit',
				'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_RUN'),
				'ACTION' => $lAdmin->ActionRedirect($runUrl),
				'DEFAULT' => true,
			),
		));
	}
}

$lAdmin->AddAdminContextMenu(Helper::getPagesMenu(Helper::PAGE_INDEX), false, false);
$lAdmin->CheckListMode();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

if (!Helper::isConfigExists())
{
	CAdminMessage::ShowMessage(array(
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_NO_CONFIG'),
		'DETAILS' => Loc::getMessage(
			'INTERVOLGA_MIGRATO.WEB_NO_CONFIG_DETAILS',
			array(
				'#PATH#' => Helper::getConfigRelativePath(),
				'#LINK#' => Helper::getUrl(Helper::PAGE_CONFIG),
			)
		),
		'HTML' => true,
	));
}
if (!Helper::canWrite())
{
	CAdminMessage::ShowMessage(array(
		'TYPE' => 'PROGRESS',
		'MESSAGE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_READ_ONLY'),
	));
}
?>
<style>
	.migrato-quick-start
	{
		margin: 0 0 12px;
	}
	.migrato-quick-start .adm-btn
	{
		margin: 0 8px 8px 0;
	}
	.migrato-command-danger
	{
		color: #c0392b;
		font-size: 11px;
	}
</style>
<?php if (Helper::canWrite()): ?>
<div class="migrato-quick-start">
	<?php foreach (CommandRunner::MAIN_COMMANDS as $name): ?>
		<?php if (isset($commands[$name])): ?>
			<a class="adm-btn<?= (CommandRunner::isDangerous($name) ? '' : ' adm-btn-save') ?>"
				href="<?= htmlspecialcharsbx(Helper::getUrl(Helper::PAGE_RUN, array('command' => $name))) ?>"
				title="<?= htmlspecialcharsbx($commands[$name]->getDescription()) ?>"><?= htmlspecialcharsbx($name) ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
<?php endif; ?>
<?php
$lAdmin->DisplayList();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
