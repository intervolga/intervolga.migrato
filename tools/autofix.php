<?php
include dirname(__DIR__) . "/include/tools_before.php";

try
{
	$report = array();
	\Intervolga\Migrato\Tool\Process\BaseProcess::run();
	$errors = \Intervolga\Migrato\Tool\Process\BaseProcess::validate();
	if (!$errors)
	{
		$report[] = "No validation errors";
	}
	else
	{
		$fixed = \Intervolga\Migrato\Tool\Process\BaseProcess::fixErrors($errors);
		$report[] = "Errors fixed ($fixed/" . count($errors) . ")";
	}
	\Intervolga\Migrato\Tool\Page::showReport($report);
}
catch (\Exception $exception)
{
	\Intervolga\Migrato\Tool\Page::handleException($exception);
}

include dirname(__DIR__) . "/include/tools_after.php";