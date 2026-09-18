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

$commands = CommandRunner::getCommands();
$mainCommands = array();
foreach (CommandRunner::MAIN_COMMANDS as $mainCommand)
{
	if (isset($commands[$mainCommand]))
	{
		$mainCommands[$mainCommand] = $commands[$mainCommand];
	}
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

$contextMenu = new CAdminContextMenu(Helper::getPagesMenu(Helper::PAGE_INDEX));
$contextMenu->Show();

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
		margin: 0 0 20px;
	}
	.migrato-quick-start .adm-btn
	{
		margin: 0 8px 8px 0;
	}
	.migrato-command-name
	{
		font-family: "Courier New", monospace;
		font-weight: bold;
		white-space: nowrap;
	}
	.migrato-command-note
	{
		color: #8b8b8b;
		font-size: 11px;
	}
	.migrato-command-danger
	{
		color: #c0392b;
	}
</style>
<div class="migrato-quick-start">
	<h4><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_QUICK_START') ?></h4>
	<?php foreach ($mainCommands as $name => $command): ?>
		<a class="adm-btn<?= (CommandRunner::isDangerous($name) ? '' : ' adm-btn-save') ?>"
			href="<?= htmlspecialcharsbx(Helper::getUrl(Helper::PAGE_RUN, array('command' => $name))) ?>"
			title="<?= htmlspecialcharsbx($command->getDescription()) ?>"><?= htmlspecialcharsbx($name) ?></a>
	<?php endforeach; ?>
</div>
<table class="adm-list-table">
	<thead>
	<tr class="adm-list-table-header">
		<td class="adm-list-table-cell"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_COLUMN_COMMAND') ?></td>
		<td class="adm-list-table-cell"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_COLUMN_DESCRIPTION') ?></td>
		<td class="adm-list-table-cell"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_COLUMN_ACTIONS') ?></td>
	</tr>
	</thead>
	<tbody>
	<?php foreach ($commands as $name => $command): ?>
		<tr class="adm-list-table-row">
			<td class="adm-list-table-cell">
				<span class="migrato-command-name"><?= htmlspecialcharsbx($name) ?></span>
				<?php if (CommandRunner::isDangerous($name)): ?>
					<div class="migrato-command-note migrato-command-danger">
						<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_COMMAND_DANGEROUS') ?>
					</div>
				<?php endif; ?>
			</td>
			<td class="adm-list-table-cell">
				<?= htmlspecialcharsbx($command->getDescription()) ?>
				<?php
				$parameters = array();
				foreach ($command->getDefinition()->getArguments() as $argument)
				{
					$parameters[] = $argument->getName();
				}
				foreach ($command->getDefinition()->getOptions() as $option)
				{
					$parameters[] = '--' . $option->getName();
				}
				?>
				<?php if ($parameters): ?>
					<div class="migrato-command-note"><?= htmlspecialcharsbx(implode(', ', $parameters)) ?></div>
				<?php endif; ?>
			</td>
			<td class="adm-list-table-cell">
				<?php if (Helper::canWrite()): ?>
					<a class="adm-btn"
						href="<?= htmlspecialcharsbx(Helper::getUrl(Helper::PAGE_RUN, array('command' => $name))) ?>">
						<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_RUN') ?>
					</a>
				<?php else: ?>
					&nbsp;
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
