<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Intervolga\Migrato\Tool\XmlIdValidateError;

Loc::loadMessages(__FILE__);

class BaseProcess
{
	/**
	 * @var string[]
	 */
	protected static $reports = array();
	/**
	 * @var string
	 */
	protected static $step = "";

	public static function run()
	{
		static::$reports = array();
		LogTable::deleteAll();
		static::report("Process started");
	}

	/**
	 * @return string[]
	 */
	public static function getReports()
	{
		return static::$reports;
	}

	/**
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError[]
	 * @throws \Exception
	 */
	public static function validate()
	{
		$result = array();
		$configDataClasses = Config::getInstance()->getDataClasses();
		$dataClasses = static::recursiveGetDependentDataClasses($configDataClasses);
		foreach ($dataClasses as $data)
		{
			$filter = Config::getInstance()->getDataClassFilter($data);
			if (!$data->isXmlIdFieldExists())
			{
				$data->createXmlIdField();
			}
			$result = array_merge($result, static::validateData($data, $filter));
		}

		static::reportStep("Validate");
		return $result;
	}

	/**
	 * @param BaseData[] $dataClasses
	 *
	 * @return BaseData[]
	 */
	protected static function recursiveGetDependentDataClasses(array $dataClasses)
	{
		$newClassesAdded = false;
		foreach ($dataClasses as $dataClass)
		{
			$dependencies = $dataClass->getDependencies();
			if ($dependencies)
			{
				foreach ($dependencies as $dependency)
				{
					$dependentDataClass = $dependency->getTargetData();
					if (!in_array($dependentDataClass, $dataClasses))
					{
						$dataClasses[] = $dependentDataClass;
						$newClassesAdded = true;
					}
				}
			}
			$references = $dataClass->getReferences();
			if ($references)
			{
				foreach ($references as $reference)
				{
					$dependentDataClass = $reference->getTargetData();
					if (!in_array($dependentDataClass, $dataClasses))
					{
						$dataClasses[] = $dependentDataClass;
						$newClassesAdded = true;
					}
				}
			}
		}
		if ($newClassesAdded)
		{
			return static::recursiveGetDependentDataClasses($dataClasses);
		}
		else
		{
			return $dataClasses;
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError[]
	 */
	protected static function validateData(BaseData $dataClass, array $filter = array())
	{
		$errors = array();
		$records = $dataClass->getList($filter);
		$xmlIds[] = array();
		foreach ($records as $record)
		{
			$errorType = 0;
			if ($record->getXmlId())
			{
				$matches = array();
				if (preg_match_all("/^[a-z0-9\-_]*$/i", $record->getXmlId(), $matches))
				{
					if (!in_array($record->getXmlId(), $xmlIds))
					{
						if (static::isSimpleXmlId($record->getXmlId()))
						{
							$errorType = XmlIdValidateError::TYPE_SIMPLE;
						}
						else
						{
							$xmlIds[] = $record->getXmlId();
						}
					}
					else
					{
						$errorType = XmlIdValidateError::TYPE_REPEAT;
					}
				}
				else
				{
					$errorType = XmlIdValidateError::TYPE_INVALID;
				}
			}
			else
			{
				$errorType = XmlIdValidateError::TYPE_EMPTY;

			}
			if ($errorType)
			{
				$errors[] = new XmlIdValidateError($dataClass, $errorType, $record->getId(), $record->getXmlId());
				LogTable::add(array(
					"RECORD" => $record,
					"OPERATION" => "validate",
					"COMMENT" => XmlIdValidateError::typeToString($errorType),
					"STEP" => "Validate",
				));
			}
		}

		return $errors;
	}

	/**
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	protected static function isSimpleXmlId($xmlId)
	{
		return is_numeric($xmlId);
	}

	/**
	 * @param XmlIdValidateError[] $errors
	 *
	 * @return int
	 */
	public static function fixErrors(array $errors)
	{
		$counter = 0;
		foreach ($errors as $error)
		{
			try
			{
				$xmlId = $error->getDataClass()->generateXmlId($error->getId());
				$error->setXmlId($xmlId);
				LogTable::add(array(
					"XML_ID_ERROR" => $error,
					"OPERATION" => "xmlid error fix",
					"STEP" => __FUNCTION__,
				));
				$counter++;
			}
			catch (\Exception $exception)
			{
				LogTable::add(array(
					"XML_ID_ERROR" => $error,
					"EXCEPTION" => $exception,
					"OPERATION" => "xmlid error fix",
					"STEP" => __FUNCTION__,
				));
			}
		}

		return $counter;
	}

	/**
	 * @param string $module
	 *
	 * @return string
	 */
	protected static function getModuleOptionsDirectory($module)
	{
		return INTERVOLGA_MIGRATO_DIRECTORY . $module . "/";
	}

	/**
	 * @param string $message
	 * @param string $type
	 */
	protected static function report($message, $type = "")
	{
		list($microSec,) = explode(" ", microtime());
		$microSec = round($microSec, 3)*1000;
		$microSec = str_pad($microSec, 3, "0", STR_PAD_RIGHT);
		$type = trim($type);
		if ($type)
		{
			$type = "[" . $type . "] ";
		}
		static::$reports[] = date("d.m.Y H:i:s") . ":" . $microSec . " " . $type . $message;
	}

	/**
	 * @param string $step
	 */
	protected static function reportStep($step)
	{
		$getList = LogTable::getList(array(
			"filter" => array(
				"=STEP" => $step,
			),
			"select" => array(
				"MODULE_NAME",
				"ENTITY_NAME",
				"OPERATION",
				"RESULT",
				new ExpressionField('CNT', 'COUNT(*)')
			),
			"group" => array(
				"MODULE_NAME",
				"ENTITY_NAME",
				"OPERATION",
				"RESULT",
			),
		));
		while ($logs = $getList->fetch())
		{
			static::report(
				Loc::getMessage(
					"INTERVOLGA_MIGRATO.STATISTICS_RECORD",
					array(
						"#MODULE#" => $logs["MODULE_NAME"],
						"#ENTITY#" => $logs["ENTITY_NAME"],
						"#OPERATION#" => $logs["OPERATION"],
						"#COUNT#" => $logs["CNT"],
					)
				),
				$logs["RESULT"] ? "ok" : "fail"
			);
		}
	}
}