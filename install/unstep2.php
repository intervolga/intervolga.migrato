<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
if(!check_bitrix_sessid()) return;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);
CAdminMessage::ShowNote(Loc::getMessage("INTERVOLGA_MIGRATO.UNINSTALLED"));
?>
<a href="/bitrix/admin/partner_modules.php" class="adm-btn adm-btn-save">
	<?= Loc::getMessage("INTERVOLGA_MIGRATO.GO_BACK")?>
</a>