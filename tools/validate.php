<?php
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../../../..");
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);
define('NO_AGENT_CHECK', true);
define("STATISTIC_SKIP_ACTIVITY_CHECK", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

@set_time_limit(0);
if (\Bitrix\Main\Loader::includeModule("intervolga.migrato"))
{
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
}
else
{
	echo "Module intervolga.migrato not installed";
}
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");