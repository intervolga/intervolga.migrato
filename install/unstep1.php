<?
use Bitrix\Main\Localization\Loc;
Loc::loadLanguageFile(__FILE__);
global $APPLICATION;
?>
<form action="<?echo $APPLICATION->GetCurPage()?>" method="post">
	<?=bitrix_sessid_post()?>
	<input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
	<input type="hidden" name="id" value="intervolga.migrato">
	<input type="hidden" name="uninstall" value="Y">
	<input type="hidden" name="step" value="2">
	<?
		$adminMessage = new CAdminMessage("");
		$adminMessage->ShowNote(Loc::GetMessage("INTERVOLGA_MIGRATO.UNINSTALL_WARNING"));
	?>
	<p><?= Loc::GetMessage("INTERVOLGA_MIGRATO.UNINSTALL_SAVE")?></p>
	<p>
		<input type="checkbox" name="save_tables" id="save_tables" value="Y" checked>
		<label for="save_tables"><?= Loc::GetMessage("INTERVOLGA_MIGRATO.UNINSTALL_SAVE_TABLES")?></label>
	</p>
	<input type="submit" value="<?= Loc::GetMessage("INTERVOLGA_MIGRATO.UNINSTALL_DEL")?>">
</form>