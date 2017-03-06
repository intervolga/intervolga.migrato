<?php
include dirname(__DIR__) . "/include/tools_before.php";

try
{
	\Intervolga\Migrato\Tool\Page::checkRights();
	$report = array();
	foreach (\Intervolga\Migrato\Tool\Process\BaseProcess::validate() as $error)
	{
		$name = "Validate error at " . $error->getDataClass()->getModule() . "/" . $error->getDataClass()->getEntityName();
		$xmlId = $error->getXmlId();
		if ($error->getType() == \Intervolga\Migrato\Tool\XmlIdValidateError::TYPE_EMPTY)
		{
			$report[] = $name . " " . $error->getId()->getValue() . " empty xmlid";
		}
		if ($error->getType() == \Intervolga\Migrato\Tool\XmlIdValidateError::TYPE_REPEAT)
		{
			$report[] = $name . " " . $xmlId . " repeat error";
		}
		if ($error->getType() == \Intervolga\Migrato\Tool\XmlIdValidateError::TYPE_INVALID)
		{
			$report[] = $name . " " . $xmlId . " invalid";
		}
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