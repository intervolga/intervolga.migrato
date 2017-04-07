<?php
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../../../..");
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);
define('NO_AGENT_CHECK', true);
define("STATISTIC_SKIP_ACTIVITY_CHECK", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

@set_time_limit(0);
if (!\Bitrix\Main\Loader::includeModule("intervolga.migrato"))
{
	echo "Module intervolga.migrato not installed";
}

try
{
	\Intervolga\Migrato\Tool\Page::checkRights();
}
catch (\Exception $exception)
{
	\Intervolga\Migrato\Tool\Page::handleException($exception);
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
	die;
}

/**
 * @see \Intervolga\Migrato\Tool\Process\AutoFix
 * @see \Intervolga\Migrato\Tool\Process\ClearData
 * @see \Intervolga\Migrato\Tool\Process\ExportData
 * @see \Intervolga\Migrato\Tool\Process\ExportOption
 * @see \Intervolga\Migrato\Tool\Process\ImportData
 * @see \Intervolga\Migrato\Tool\Process\ImportOption
 * @see \Intervolga\Migrato\Tool\Process\Validate
 */
$processes = array(
	"autofix" => "\\Intervolga\\Migrato\\Tool\\Process\\AutoFix",
	"cleardata" => "\\Intervolga\\Migrato\\Tool\\Process\\ClearData",
	"exportdata" => "\\Intervolga\\Migrato\\Tool\\Process\\ExportData",
	"exportoption" => "\\Intervolga\\Migrato\\Tool\\Process\\ExportOption",
	"importdata" => "\\Intervolga\\Migrato\\Tool\\Process\\ImportData",
	"importoption" => "\\Intervolga\\Migrato\\Tool\\Process\\ImportOption",
	"validate" => "\\Intervolga\\Migrato\\Tool\\Process\\Validate",
	"clearcache" => "\\Intervolga\\Migrato\\Tool\\Process\\ClearCache",
	"reindex" => "\\Intervolga\\Migrato\\Tool\\Process\\Reindex",
	"reindexurlrewrite" => "\\Intervolga\\Migrato\\Tool\\Process\\ReindexUrlRewriter",
);
if (\Intervolga\Migrato\Tool\Page::isCli())
{
	$cmdProcess = $argv[1];
}
else
{
	$cmdProcess = $_GET["process"];
}
$found = false;
foreach ($processes as $process => $processClass)
{
	/**
	 * @var \Intervolga\Migrato\Tool\Process\BaseProcess $processClass
	 */
	if ($cmdProcess == $process)
	{
		$found = true;
		try
		{
			$processClass::run();
			$report = $processClass::getReports();
			\Intervolga\Migrato\Tool\Page::showReport($report);
		}
		catch (\Exception $exception)
		{
			\Intervolga\Migrato\Tool\Page::handleException($exception);
		}
	}
}

if (!$found)
{
	if (\Intervolga\Migrato\Tool\Page::isCli())
	{
		$message = "Use first argv";
	}
	else
	{
		$message = "Use Get variable 'process'";
	}
	$message .= " to run process (" . implode(", ", array_keys($processes)) . ")";
	\Intervolga\Migrato\Tool\Page::showReport(array($message));
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");