<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
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

$configPath = Helper::getConfigPath();
$errors = array();
$notes = array();
$content = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Helper::canWrite() && check_bitrix_sessid())
{
	if (isset($_POST['create']))
	{
		$directory = dirname($configPath);
		if (!is_dir($directory))
		{
			CheckDirPath($configPath);
		}
		if (Helper::isConfigExists())
		{
			$errors[] = Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_ALREADY_EXISTS');
		}
		elseif (!copy(Helper::getDefaultConfigPath(), $configPath))
		{
			$errors[] = Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_CREATE_ERROR');
		}
		else
		{
			$notes[] = Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_CREATED');
		}
	}
	elseif (isset($_POST['save']))
	{
		$content = $_POST['CONFIG'] ?? '';
		$content = str_replace("\r\n", "\n", $content);
		$xmlError = Helper::getXmlError($content);
		if ($xmlError)
		{
			$errors[] = Loc::getMessage(
				'INTERVOLGA_MIGRATO.WEB_CONFIG_XML_ERROR',
				array('#ERROR#' => $xmlError)
			);
		}
		elseif (file_put_contents($configPath, $content) === false)
		{
			$errors[] = Loc::getMessage(
				'INTERVOLGA_MIGRATO.WEB_CONFIG_SAVE_ERROR',
				array('#PATH#' => Helper::getConfigRelativePath())
			);
		}
		else
		{
			$notes[] = Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_SAVED');
			$content = null;
		}
	}
}

if ($content === null)
{
	$content = Helper::isConfigExists() ? (string)file_get_contents($configPath) : '';
}

$APPLICATION->SetTitle(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_TITLE'));

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

$contextMenu = new CAdminContextMenu(Helper::getPagesMenu(Helper::PAGE_CONFIG));
$contextMenu->Show();

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
	.migrato-config-area
	{
		width: 100%;
		min-height: 500px;
		font-family: "Courier New", Consolas, monospace;
		font-size: 12px;
		white-space: pre;
		overflow: auto;
	}
	.migrato-config-path
	{
		font-family: "Courier New", Consolas, monospace;
	}
</style>
<?php
if (!Helper::isConfigExists())
{
	CAdminMessage::ShowMessage(array(
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_NO_CONFIG'),
		'DETAILS' => htmlspecialcharsbx(Helper::getConfigRelativePath()),
		'HTML' => true,
	));
	if (Helper::canWrite())
	{
		?>
		<form method="POST" action="<?= htmlspecialcharsbx(Helper::getUrl(Helper::PAGE_CONFIG)) ?>">
			<?= bitrix_sessid_post() ?>
			<input type="submit" class="adm-btn-save" name="create"
				value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_CREATE') ?>">
		</form>
		<?php
	}
	require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
	die();
}

$tabControl = new CAdminTabControl(
	'migratoConfigTabControl',
	array(
		array(
			'DIV' => 'config',
			'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_TAB'),
			'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_TAB_TITLE'),
		),
	)
);
$tabControl->Begin(array(
	'FORM_ACTION' => Helper::getUrl(Helper::PAGE_CONFIG),
));
$tabControl->BeginNextTab();
?>
<tr>
	<td width="20%"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_PATH') ?>:</td>
	<td width="80%">
		<span class="migrato-config-path"><?= htmlspecialcharsbx(Helper::getConfigRelativePath()) ?></span>
		<?php if (!is_writable($configPath)): ?>
			<div style="color:#c0392b;"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_NOT_WRITABLE') ?></div>
		<?php endif; ?>
	</td>
</tr>
<tr class="heading">
	<td colspan="2"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_CONTENT') ?></td>
</tr>
<tr>
	<td colspan="2">
		<textarea class="migrato-config-area" name="CONFIG" rows="30" wrap="off"
			<?= (Helper::canWrite() ? '' : 'readonly') ?>><?= htmlspecialcharsbx($content) ?></textarea>
	</td>
</tr>
<?php
$tabControl->Buttons(false);
if (Helper::canWrite())
{
	?>
	<?= bitrix_sessid_post() ?>
	<input type="submit" class="adm-btn-save" name="save"
		value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_CONFIG_SAVE') ?>">
	<?php
}
?>
<input type="button" name="back" value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_BACK_TO_LIST') ?>"
	onclick="window.location='<?= CUtil::JSEscape(Helper::getUrl(Helper::PAGE_INDEX)) ?>';">
<?php
$tabControl->End();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
