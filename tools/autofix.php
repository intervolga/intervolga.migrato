<?php
include dirname(__DIR__) . "/include/tools_before.php";

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
	\Intervolga\Migrato\Tool\Page::showReport($report);
}
catch (\Exception $exception)
{
	\Intervolga\Migrato\Tool\Page::handleException($exception);
}

include dirname(__DIR__) . "/include/tools_after.php";