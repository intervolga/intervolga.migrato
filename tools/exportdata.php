<?php
include dirname(__DIR__) . "/include/tools_before.php";

try
{
	\Intervolga\Migrato\Tool\Page::checkRights();
	\Intervolga\Migrato\Tool\Process\ExportData::run();
	$report = \Intervolga\Migrato\Tool\Process\ExportData::getReports();
	\Intervolga\Migrato\Tool\Page::showReport($report);
}
catch (\Exception $exception)
{
	\Intervolga\Migrato\Tool\Page::handleException($exception);
}

include dirname(__DIR__) . "/include/tools_after.php";