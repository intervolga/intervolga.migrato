<?php
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../../../..");
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);
define('NO_AGENT_CHECK', true);
define("STATISTIC_SKIP_ACTIVITY_CHECK", true);
$isCli = php_sapi_name() == "cli";

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
if (!$isCli && !$USER->IsAdmin())
{
	die("Access denied");
}

@set_time_limit(0);
if (\Bitrix\Main\Loader::includeModule("intervolga.migrato"))
{
	try
	{
		$report = array();
		$errors = \Intervolga\Migrato\Tool\Process\BaseProcess::validate();
		if (!$errors)
		{
			$report[] = "No validation errors";
		}
		else
		{
			\Intervolga\Migrato\Tool\Process\BaseProcess::fixErrors($errors);
			$report[] = "Errors fixed";
		}
	}
	catch (\Exception $exception)
	{
		$report = array(
			"EXCEPTION (Class: " . get_class($exception) . ")",
			"Message: " . $exception->getMessage() . " (Code: " . $exception->getCode() . ")",
			"Location: " . $exception->getFile() . ":" . $exception->getLine()
		);
	}
}
else
{
	$report = array("Module intervolga.migrato not installed");
}
if ($isCli)
{
	echo implode("\r\n", $report)."\r\n";
}
else
{
	echo "<pre>" . implode("<br>", $report) . "</pre>";
}