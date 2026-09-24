<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Web\ConfigEditor;
use Intervolga\Migrato\Tool\Web\Helper;

/**
 * @global CMain $APPLICATION
 * @global string $REQUEST_METHOD
 */
Loc::loadMessages(__FILE__);

$module_id = 'intervolga.migrato';

if (!Loader::includeModule($module_id))
{
	ShowError(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MODULE_NOT_INSTALLED'));

	return;
}

Helper::checkReadRights();
$canWrite = Helper::canWrite();

$errors = array();
$notes = array();
$request = $_POST;

if ($REQUEST_METHOD === 'POST' && (isset($_POST['save']) || isset($_POST['apply'])) && check_bitrix_sessid())
{
	if (!$canWrite)
	{
		$errors[] = Loc::getMessage('INTERVOLGA_MIGRATO.WEB_READ_ONLY');
	}
	else
	{
		$newConfig = ConfigEditor::fromRequest($_POST, ConfigEditor::read());
		$errors = ConfigEditor::save($newConfig);
		if (!$errors)
		{
			/**
			 * Сохранение прав доступа на вкладке "Доступ"
			 * @see /bitrix/modules/main/admin/group_rights.php
			 */
			$Update = 'Y';
			ob_start();
			require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/admin/group_rights.php');
			ob_end_clean();

			if (isset($_POST['save']))
			{
				LocalRedirect('settings.php?lang=' . LANGUAGE_ID . '&mid=' . urlencode($module_id)
					. '&mid_menu=1&saved=Y');
			}
			$notes[] = Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_SAVED');
		}
	}
}

if (($_REQUEST['saved'] ?? '') === 'Y')
{
	$notes[] = Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_SAVED');
}

$config = ConfigEditor::read();
$rows = ConfigEditor::getFormRows($config);
$optionRules = $config['options'];
if ($errors && $request)
{
	$optionRules = ConfigEditor::fromRequest($request, $config);
	$optionRules = $optionRules['options'];
}

$aTabs = array(
	array(
		'DIV' => 'migrato_entities',
		'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_ENTITIES'),
		'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_ENTITIES_TITLE'),
	),
	array(
		'DIV' => 'migrato_options',
		'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_OPTIONS'),
		'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_OPTIONS_TITLE'),
	),
	array(
		'DIV' => 'migrato_orm',
		'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_ORM'),
		'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_ORM_TITLE'),
	),
	array(
		'DIV' => 'migrato_xml',
		'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_XML'),
		'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_XML_TITLE'),
	),
	array(
		'DIV' => 'migrato_rights',
		'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_RIGHTS'),
		'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_RIGHTS_TITLE'),
	),
);

$tabControl = new CAdminTabControl('migratoOptionsTabControl', $aTabs);

foreach ($errors as $error)
{
	CAdminMessage::ShowMessage(array(
		'TYPE' => 'ERROR',
		'MESSAGE' => $error,
	));
}
foreach ($notes as $note)
{
	CAdminMessage::ShowNote($note);
}
?>
<style>
	.migrato-options-hint
	{
		color: #8b8b8b;
		font-size: 11px;
		display: block;
		margin-top: 3px;
	}
	.migrato-options-filter
	{
		width: 100%;
		min-height: 36px;
		font-family: "Courier New", Consolas, monospace;
		font-size: 11px;
	}
	.migrato-options-area
	{
		width: 100%;
		min-height: 220px;
		font-family: "Courier New", Consolas, monospace;
		font-size: 12px;
	}
	.migrato-options-missing
	{
		color: #c0392b;
	}
	.migrato-options-entity
	{
		font-family: "Courier New", Consolas, monospace;
	}
</style>
<form method="POST" name="migrato_options_form"
	action="settings.php?lang=<?= htmlspecialcharsbx(LANGUAGE_ID) ?>&amp;mid=<?= htmlspecialcharsbx($module_id) ?>&amp;mid_menu=1">
<?php
$tabControl->Begin();

$tabControl->BeginNextTab();
?>
<tr>
	<td colspan="2">
		<span class="migrato-options-hint">
			<?= Loc::getMessage(
				'INTERVOLGA_MIGRATO.WEB_OPTIONS_ENTITIES_HINT',
				array('#PATH#' => htmlspecialcharsbx(Helper::getConfigRelativePath()))
			) ?>
		</span>
	</td>
</tr>
<?php foreach ($rows as $module => $entities): ?>
	<tr class="heading">
		<td colspan="2">
			<?= htmlspecialcharsbx($module) ?>
			<?php if ($canWrite): ?>
				&mdash;
				<a href="javascript:void(0)"
					onclick="migratoToggleModule('<?= CUtil::JSEscape($module) ?>', true)"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_CHECK_ALL') ?></a>
				/
				<a href="javascript:void(0)"
					onclick="migratoToggleModule('<?= CUtil::JSEscape($module) ?>', false)"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_UNCHECK_ALL') ?></a>
			<?php endif; ?>
		</td>
	</tr>
	<?php foreach ($entities as $entity => $row): ?>
		<tr>
			<td width="45%">
				<input type="hidden" name="ENTITY[<?= htmlspecialcharsbx($module) ?>][<?= htmlspecialcharsbx($entity) ?>]" value="N">
				<input type="checkbox" value="Y"
					data-migrato-module="<?= htmlspecialcharsbx($module) ?>"
					id="migrato-entity-<?= htmlspecialcharsbx($module . '-' . $entity) ?>"
					name="ENTITY[<?= htmlspecialcharsbx($module) ?>][<?= htmlspecialcharsbx($entity) ?>]"
					<?= ($row['checked'] ? 'checked' : '') ?> <?= ($canWrite ? '' : 'disabled') ?>>
				<label for="migrato-entity-<?= htmlspecialcharsbx($module . '-' . $entity) ?>">
					<span class="migrato-options-entity"><?= htmlspecialcharsbx($entity) ?></span>
					<?php if ($row['data']): ?>
						&mdash; <?= htmlspecialcharsbx($row['data']->getEntityNameLoc()) ?>
					<?php else: ?>
						<span class="migrato-options-missing">
							&mdash; <?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_ENTITY_UNKNOWN') ?>
						</span>
					<?php endif; ?>
				</label>
			</td>
			<td width="55%">
				<textarea class="migrato-options-filter" rows="1"
					name="FILTER[<?= htmlspecialcharsbx($module) ?>][<?= htmlspecialcharsbx($entity) ?>]"
					placeholder="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_FILTER_PLACEHOLDER') ?>"
					<?= ($canWrite ? '' : 'readonly') ?>><?= htmlspecialcharsbx($row['filters']) ?></textarea>
			</td>
		</tr>
	<?php endforeach; ?>
<?php endforeach; ?>
<?php
$tabControl->BeginNextTab();
?>
<tr>
	<td colspan="2">
		<span class="migrato-options-hint"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_OPTIONS_HINT') ?></span>
	</td>
</tr>
<tr class="heading">
	<td width="40%"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_OPTION_MODULE') ?></td>
	<td width="60%"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_OPTION_NAME') ?></td>
</tr>
<?php foreach ($optionRules as $index => $rule): ?>
	<tr id="migrato-option-row-<?= (int)$index ?>">
		<td>
			<input type="text" size="30" name="OPTION_MODULE[<?= (int)$index ?>]"
				value="<?= htmlspecialcharsbx($rule['module']) ?>" <?= ($canWrite ? '' : 'readonly') ?>>
		</td>
		<td>
			<input type="text" size="40" name="OPTION_NAME[<?= (int)$index ?>]"
				value="<?= htmlspecialcharsbx($rule['name']) ?>" <?= ($canWrite ? '' : 'readonly') ?>>
			<?php if ($canWrite): ?>
				<input type="button" value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_OPTION_DELETE') ?>"
					onclick="migratoDeleteOptionRow(<?= (int)$index ?>)">
			<?php endif; ?>
		</td>
	</tr>
<?php endforeach; ?>
<tr id="migrato-option-add-row">
	<td colspan="2">
		<?php if ($canWrite): ?>
			<input type="button" value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_OPTION_ADD') ?>"
				onclick="migratoAddOptionRow()">
		<?php endif; ?>
	</td>
</tr>
<?php
$tabControl->BeginNextTab();
?>
<tr>
	<td colspan="2">
		<span class="migrato-options-hint"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_ORM_HINT') ?></span>
	</td>
</tr>
<tr>
	<td width="40%"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_ORM_MODULES') ?>:</td>
	<td width="60%">
		<textarea class="migrato-options-area" name="ORM_MODULES"
			<?= ($canWrite ? '' : 'readonly') ?>><?= htmlspecialcharsbx(implode("\n", $config['orm']['modules'])) ?></textarea>
	</td>
</tr>
<tr>
	<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_ORM_ENTITIES') ?>:</td>
	<td>
		<textarea class="migrato-options-area" name="ORM_ENTITIES"
			<?= ($canWrite ? '' : 'readonly') ?>><?= htmlspecialcharsbx(implode("\n", $config['orm']['entities'])) ?></textarea>
	</td>
</tr>
<?php
$tabControl->BeginNextTab();
?>
<tr>
	<td width="40%"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_PATH') ?>:</td>
	<td width="60%">
		<span class="migrato-options-entity"><?= htmlspecialcharsbx(Helper::getConfigRelativePath()) ?></span>
		<span class="migrato-options-hint">
			<a href="<?= htmlspecialcharsbx(Helper::getFileManUrl()) ?>">
				<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_RAW_EDITOR') ?>
			</a>
		</span>
	</td>
</tr>
<tr>
	<td colspan="2">
		<textarea class="migrato-options-area" readonly rows="30"><?= htmlspecialcharsbx(
			Helper::isConfigExists() ? (string)file_get_contents(Helper::getConfigPath()) : ''
		) ?></textarea>
	</td>
</tr>
<?php
$tabControl->BeginNextTab();
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/admin/group_rights.php');

$tabControl->Buttons();
?>
<?= bitrix_sessid_post() ?>
<input type="submit" class="adm-btn-save" name="save"
	value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_SAVE') ?>" <?= ($canWrite ? '' : 'disabled') ?>>
<input type="submit" name="apply"
	value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_APPLY') ?>" <?= ($canWrite ? '' : 'disabled') ?>>
<input type="button" name="commands"
	value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_COMMANDS') ?>"
	onclick="window.location='<?= CUtil::JSEscape(Helper::getUrl(Helper::PAGE_INDEX)) ?>';">
<?php
$tabControl->End();
?>
</form>
<script>
	var migratoOptionRowIndex = <?= (count($optionRules) ? max(array_keys($optionRules)) + 1 : 0) ?>;

	function migratoToggleModule(module, checked)
	{
		var inputs = document.querySelectorAll('input[data-migrato-module="' + module + '"]');
		for (var i = 0; i < inputs.length; i++)
		{
			inputs[i].checked = checked;
		}
	}

	function migratoDeleteOptionRow(index)
	{
		var row = document.getElementById('migrato-option-row-' + index);
		if (row)
		{
			row.parentNode.removeChild(row);
		}
	}

	function migratoAddOptionRow()
	{
		var addRow = document.getElementById('migrato-option-add-row');
		var index = migratoOptionRowIndex++;
		var row = document.createElement('tr');
		row.id = 'migrato-option-row-' + index;
		row.innerHTML = '<td><input type="text" size="30" name="OPTION_MODULE[' + index + ']"></td>'
			+ '<td><input type="text" size="40" name="OPTION_NAME[' + index + ']"> '
			+ '<input type="button" value="<?= CUtil::JSEscape(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_OPTION_DELETE')) ?>"'
			+ ' onclick="migratoDeleteOptionRow(' + index + ')"></td>';
		addRow.parentNode.insertBefore(row, addRow);
	}
</script>
