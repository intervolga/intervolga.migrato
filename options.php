<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Web\Helper;

/**
 * @global CMain $APPLICATION
 */
Loc::loadMessages(__FILE__);

$module_id = 'intervolga.migrato';

if (!Loader::includeModule($module_id))
{
	ShowError(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MODULE_NOT_INSTALLED'));

	return;
}

Helper::checkReadRights();

$tabControl = new CAdminTabControl(
	'migratoOptionsTabControl',
	array(
		array(
			'DIV' => 'migrato_config',
			'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_CONFIG'),
			'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_TAB_CONFIG_TITLE'),
		),
	)
);

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
?>
<style>
	.migrato-options-hint
	{
		color: #8b8b8b;
		font-size: 11px;
		display: block;
		margin-top: 3px;
	}
	.migrato-options-path
	{
		font-family: "Courier New", Consolas, monospace;
	}
</style>
<?php
$tabControl->Begin();
$tabControl->BeginNextTab();
?>
<tr>
	<td width="40%"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_PATH') ?>:</td>
	<td width="60%">
		<span class="migrato-options-path"><?= htmlspecialcharsbx(Helper::getConfigRelativePath()) ?></span>
		<span class="migrato-options-hint"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_CONFIG_HINT') ?></span>
	</td>
</tr>
<tr>
	<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_EDIT') ?>:</td>
	<td>
		<a href="<?= htmlspecialcharsbx(Helper::getFileManUrl()) ?>">
			<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_RAW_EDITOR') ?>
		</a>
	</td>
</tr>
<?php
$tabControl->Buttons(false);
?>
<input type="button" name="commands"
	value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OPTIONS_COMMANDS') ?>"
	onclick="window.location='<?= CUtil::JSEscape(Helper::getUrl(Helper::PAGE_INDEX)) ?>';">
<?php
$tabControl->End();
