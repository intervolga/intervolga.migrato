<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

require __DIR__.'/simplesign.php';


if ($_REQUEST['action'] == 'create_admin_sessid'
 && SimpleSign::getInstance()->check($_REQUEST))
{
	$USER->Authorize(1);
	$sessid = bitrix_sessid();
} else {
	$sessid = '';
}

echo json_encode(array(
	'sessid' => $sessid,
));

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
