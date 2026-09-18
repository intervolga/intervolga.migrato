<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Web\CommandRunner;
use Intervolga\Migrato\Tool\Web\Helper;
use Symfony\Component\Console\Output\OutputInterface;

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

Helper::checkWriteRights();

if (!Helper::isConsoleAvailable())
{
	LocalRedirect(Helper::getUrl(Helper::PAGE_INDEX));
}

$commandName = (string)($_REQUEST['command'] ?? '');
$command = CommandRunner::getCommand($commandName);
if (!$command)
{
	LocalRedirect(Helper::getUrl(Helper::PAGE_INDEX));
}

$request = array_merge($_GET, $_POST);
$errors = array();
$isStarted = false;
$parameters = array();
$verbosity = CommandRunner::normalizeVerbosity(
	$request['VERBOSITY'] ?? OutputInterface::VERBOSITY_NORMAL
);

/**
 * Значение по умолчанию для поля формы
 *
 * @param mixed $default
 *
 * @return string
 */
function migratoDefaultValue($default)
{
	return is_scalar($default) ? (string)$default : '';
}

if (isset($_POST['run']) && check_bitrix_sessid())
{
	if (!Helper::isConfigExists())
	{
		$errors[] = Loc::getMessage('INTERVOLGA_MIGRATO.WEB_NO_CONFIG');
	}
	if (CommandRunner::isDangerous($commandName) && ($_POST['CONFIRM'] ?? '') !== 'Y')
	{
		$errors[] = Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIRM_REQUIRED');
	}
	$parameters = CommandRunner::extractParameters($command, $request);
	$errors = array_merge($errors, CommandRunner::validateParameters($command, $parameters));
	$isStarted = !$errors;
}

$executeUrlParams = array('command' => $commandName, 'VERBOSITY' => $verbosity);
foreach ($request as $key => $value)
{
	if (is_string($value) && (strpos($key, 'ARG_') === 0 || strpos($key, 'OPT_') === 0))
	{
		$executeUrlParams[$key] = $value;
	}
}
$executeUrlParams['sessid'] = bitrix_sessid();
$executeUrl = Helper::getUrl(Helper::PAGE_EXECUTE, $executeUrlParams);

$APPLICATION->SetTitle(Loc::getMessage(
	'INTERVOLGA_MIGRATO.WEB_RUN_TITLE',
	array('#COMMAND#' => $commandName)
));

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

$contextMenu = new CAdminContextMenu(array(
	array(
		'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_BACK_TO_LIST'),
		'LINK' => Helper::getUrl(Helper::PAGE_INDEX),
		'ICON' => 'btn_list',
	),
	array(
		'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_LOG'),
		'LINK' => Helper::getUrl(Helper::PAGE_LOG),
		'ICON' => 'btn',
	),
));
$contextMenu->Show();

if ($errors)
{
	CAdminMessage::ShowMessage(array(
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_RUN_ERROR'),
		'DETAILS' => implode('<br>', array_map('htmlspecialcharsbx', $errors)),
		'HTML' => true,
	));
}
?>
<style>
	.migrato-console-wrap
	{
		margin: 15px 0;
		border: 1px solid #c6cdd3;
		border-radius: 2px;
		background: #1f2529;
	}
	.migrato-console-frame
	{
		display: block;
		width: 100%;
		height: 500px;
		border: 0;
		resize: vertical;
		overflow: auto;
	}
	.migrato-console-panel
	{
		padding: 8px 12px;
		background: #eef2f4;
		border-bottom: 1px solid #c6cdd3;
		font-size: 12px;
	}
	.migrato-console-string
	{
		font-family: "Courier New", monospace;
		color: #4a4a4a;
	}
	.migrato-hint
	{
		color: #8b8b8b;
		font-size: 11px;
		display: block;
		margin-top: 3px;
	}
	.migrato-danger-note
	{
		color: #c0392b;
	}
</style>
<?php
$tabControl = new CAdminTabControl(
	'migratoRunTabControl',
	array(
		array(
			'DIV' => 'params',
			'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_TAB_PARAMS'),
			'TITLE' => htmlspecialcharsbx($command->getDescription()),
		),
	)
);
$tabControl->Begin(array(
	'FORM_ACTION' => Helper::getUrl(Helper::PAGE_RUN, array('command' => $commandName)),
));
$tabControl->BeginNextTab();
?>
<tr>
	<td width="40%"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_COLUMN_COMMAND') ?>:</td>
	<td width="60%"><b><?= htmlspecialcharsbx($commandName) ?></b>
		<span class="migrato-hint"><?= htmlspecialcharsbx($command->getDescription()) ?></span>
	</td>
</tr>
<?php foreach ($command->getDefinition()->getArguments() as $argument): ?>
	<tr>
		<td><?php if ($argument->isRequired()): ?><span class="required">*</span><?php endif; ?>
			<?= htmlspecialcharsbx($argument->getName()) ?>:
		</td>
		<td>
			<input type="text" size="40" name="ARG_<?= htmlspecialcharsbx($argument->getName()) ?>"
				value="<?= htmlspecialcharsbx((string)($request['ARG_' . $argument->getName()] ?? migratoDefaultValue($argument->getDefault()))) ?>">
			<span class="migrato-hint"><?= htmlspecialcharsbx($argument->getDescription()) ?></span>
		</td>
	</tr>
<?php endforeach; ?>
<?php foreach ($command->getDefinition()->getOptions() as $option): ?>
	<tr>
		<td>--<?= htmlspecialcharsbx($option->getName()) ?>:</td>
		<td>
			<?php if ($option->acceptValue()): ?>
				<input type="text" size="40" name="OPT_<?= htmlspecialcharsbx($option->getName()) ?>"
					value="<?= htmlspecialcharsbx((string)($request['OPT_' . $option->getName()] ?? migratoDefaultValue($option->getDefault()))) ?>">
			<?php else: ?>
				<input type="hidden" name="OPT_<?= htmlspecialcharsbx($option->getName()) ?>" value="N">
				<input type="checkbox" value="Y" name="OPT_<?= htmlspecialcharsbx($option->getName()) ?>"
					<?= (($request['OPT_' . $option->getName()] ?? '') === 'Y' ? 'checked' : '') ?>>
			<?php endif; ?>
			<span class="migrato-hint"><?= htmlspecialcharsbx($option->getDescription()) ?></span>
		</td>
	</tr>
<?php endforeach; ?>
<?php if (!$command->getDefinition()->hasOption('fails')): ?>
	<tr>
		<td>--fails:</td>
		<td>
			<input type="hidden" name="OPT_fails" value="N">
			<input type="checkbox" value="Y" name="OPT_fails"
				<?= (($request['OPT_fails'] ?? '') === 'Y' ? 'checked' : '') ?>>
			<span class="migrato-hint"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTION_FAILS') ?></span>
		</td>
	</tr>
<?php endif; ?>
<tr>
	<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_VERBOSITY') ?>:</td>
	<td>
		<select name="VERBOSITY">
			<?php foreach (CommandRunner::getVerbosityLevels() as $level => $title): ?>
				<option value="<?= htmlspecialcharsbx($level) ?>"
					<?= ((int)$level === $verbosity ? 'selected' : '') ?>><?= htmlspecialcharsbx($title) ?></option>
			<?php endforeach; ?>
		</select>
	</td>
</tr>
<?php if (CommandRunner::isDangerous($commandName)): ?>
	<tr>
		<td><span class="required">*</span> <?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIRM') ?>:</td>
		<td>
			<input type="checkbox" value="Y" name="CONFIRM"
				<?= (($request['CONFIRM'] ?? '') === 'Y' ? 'checked' : '') ?>>
			<span class="migrato-hint migrato-danger-note">
				<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIRM_HINT') ?>
			</span>
		</td>
	</tr>
<?php endif; ?>
<?php
$tabControl->Buttons(false);
?>
<input type="hidden" name="command" value="<?= htmlspecialcharsbx($commandName) ?>">
<?= bitrix_sessid_post() ?>
<input type="submit" class="adm-btn-save" name="run"
	value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_EXECUTE') ?>">
<input type="button" name="back" value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_BACK_TO_LIST') ?>"
	onclick="window.location='<?= CUtil::JSEscape(Helper::getUrl(Helper::PAGE_INDEX)) ?>';">
<?php
$tabControl->End();

if ($isStarted)
{
	?>
	<div class="migrato-console-wrap" id="migrato-console-wrap">
		<div class="migrato-console-panel">
			<span class="migrato-console-string"><?= htmlspecialcharsbx(
				CommandRunner::getConsoleString($commandName, $parameters, $verbosity)
			) ?></span>
			&mdash;
			<a href="<?= htmlspecialcharsbx($executeUrl) ?>" target="_blank">
				<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPEN_IN_NEW_WINDOW') ?>
			</a>
		</div>
		<iframe class="migrato-console-frame" name="migrato-console" id="migrato-console"
			src="<?= htmlspecialcharsbx($executeUrl) ?>"></iframe>
	</div>
	<script>
		BX.ready(function () {
			var wrap = document.getElementById('migrato-console-wrap');
			if (wrap)
			{
				BX.scrollToNode(wrap);
			}
		});
	</script>
	<?php
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
