<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Intervolga\Migrato\Tool\Orm\ColorLog;

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
	/**
	 * @var int[]
	 */
	protected static $reportTypeCounter = array();

	public static function run()
	{
		static::$reports = array();
		LogTable::deleteAll();
		static::report("Process started");
	}

	public static function finalReport()
	{
		static::addSeparator();
		if (static::$reportTypeCounter["fail"])
		{
			static::report("Process completed with errors");
		}
		else
		{
			static::report("Process completed, no errors");
		}
	}

	public static function addSeparator($symbol = "-")
	{
		static::$reports[] = str_repeat($symbol, 80);
	}

	/**
	 * @return string[]
	 */
	public static function getReports()
	{
		return static::$reports;
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
	 * @param string $module
	 *
	 * @return string
	 */
	protected static function getModuleOptionsDirectory($module)
	{
		return INTERVOLGA_MIGRATO_DIRECTORY . $module . "/";
	}

	/**
	 * @param string $step
	 */
	protected static function startStep($step)
	{
		static::$step = $step;
		static::addSeparator();
		static::report("step: " . static::$step);
	}

	/**
	 * @param string $message
	 * @param string $type
	 */
	protected static function report($message, $type = "")
	{
		$type = trim($type);
		if ($type)
		{
			static::$reportTypeCounter[$type]++;
			$type = ColorLog::getColoredString("[" . $type . "] ", $type);
		}
		static::$reports[] = $type . $message;
	}

	protected static function reportStepLogs()
	{
		$getList = LogTable::getList(array(
			"filter" => array(
				"=STEP" => static::$step,
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