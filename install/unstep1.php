<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);
global $APPLICATION;
?>

<form action="<?= $APPLICATION->GetCurPage() ?>">
	<?= bitrix_sessid_post() ?>
	<input type="hidden" name="lang" value="<?= LANG ?>">
	<input type="hidden" name="id" value="intervolga.migrato">
	<input type="hidden" name="uninstall" value="Y">
	<input type="hidden" name="step" value="2">
	<p>
		<input type="checkbox" name="savedata" id="savedata" value="Y" checked>
		<label for="savedata">
			<?= Loc::getMessage("INTERVOLGA_MIGRATO.SAVE_UF_XML_ID") ?>
		</label>
	</p>
	<input type="submit" name="inst" value="<?= Loc::getMessage("INTERVOLGA_MIGRATO.UNINSTALL") ?>">
</form>