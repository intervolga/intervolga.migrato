<?php
use Bitrix\Main\Loader;
use Intervolga\Migrato\Tool\Console\Application;
use Intervolga\Migrato\Tool\Console\Output;

$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../../../..");
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);
define('NO_AGENT_CHECK', true);
define("STATISTIC_SKIP_ACTIVITY_CHECK", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

@set_time_limit(0);
if (!Loader::includeModule("intervolga.migrato"))
{
	echo "Module intervolga.migrato not installed";
}
else
{
	$application = new Application();
	$application->run(null, new Output());
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");