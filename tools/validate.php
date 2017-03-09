<?php
include dirname(__DIR__) . "/include/tools_before.php";

try
{
	$report = array();
	foreach (\Intervolga\Migrato\Tool\Process\BaseProcess::validate() as $error)
	{
		$report[] = $error->toString();
	}
	if (!$report)
	{
		$report[] = "No validation errors";
	}
	\Intervolga\Migrato\Tool\Page::showReport($report);
}
catch (\Exception $exception)
{
	\Intervolga\Migrato\Tool\Page::handleException($exception);
}

include dirname(__DIR__) . "/include/tools_after.php";