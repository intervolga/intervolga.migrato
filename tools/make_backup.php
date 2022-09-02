<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$USER->Authorize(1);
$sessid = bitrix_sessid();
$_POST['sessid'] = $sessid;
$_REQUEST['sessid'] = $sessid;

$makeBackupFile = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/admin/dump.php';
require $makeBackupFile;
