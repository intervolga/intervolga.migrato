<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$rootDir = realpath(__DIR__.'/../../../../upload/');
$contentDir = scandir($rootDir);
sort($contentDir);
$serialized = serialize($contentDir);
$secret = md5($serialized);
$time = time();
$diffTime = 5;

$requestedTime = $_REQUEST['time'] ?? 0;
$expectedSign = hash('SHA256', 'create-admin-sessid|'.$requestedTime.'|'.$secret);

if (
	$requestedTime >= $time - $diffTime
	&& $requestedTime <= $time
	&& $_REQUEST['sign'] == $expectedSign
) {
	$USER->Authorize(1);
	$sessid = bitrix_sessid();
} else {
	$sessid = '';
}

echo json_encode(array(
	'sessid' => $sessid,
));

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
