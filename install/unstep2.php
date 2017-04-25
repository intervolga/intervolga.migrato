<?
if(!check_bitrix_sessid()) return;

use Bitrix\Main\Localization\Loc;
Loc::loadLanguageFile(__FILE__);

global $APPLICATION;
if($ex = $APPLICATION->GetException())
{
	$adminMessage = new CAdminMessage(Loc::GetMessage("INTERVOLGA_MIGRATO.UNINSTALL_ERROR"), $ex);
	$adminMessage->Show();
}
else
{
	$adminMessage = new CAdminMessage(Loc::GetMessage("INTERVOLGA_MIGRATO.UNINSTALLED"));
	$adminMessage->ShowNote(Loc::GetMessage("INTERVOLGA_MIGRATO.UNINSTALLED"));
}
?>
<form action="<?echo $APPLICATION->GetCurPage()?>">
    <input type="hidden" name="lang" value="<?echo LANG?>">
    <input type="submit" name="" value="<?echo Loc::GetMessage("INTERVOLGA_MIGRATO.GO_BACK")?>">
<form>
