<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\ColorLog;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

abstract class BaseCommand extends Command
{
	const REPORT_TYPE_FAIL = 'fail';
	const REPORT_TYPE_OK = 'ok';
	const REPORT_TYPE_INFO = 'info';

	/**
	 * @var \Symfony\Component\Console\Input\InputInterface $input
	 */
	protected $input = null;
	/**
	 * @var \Symfony\Component\Console\Output\OutputInterface $output
	 */
	protected $output = null;

	/**
	 * @var string
	 */
	protected $step = '';

	protected $isMainCommand = true;

	/**
	 * @param int $options
	 */
	public function separate($options = 0)
	{
		$this->output->writeln(str_repeat('-', 80), $options);
	}

	/**
	 * @param array $log
	 *
	 * @return \Bitrix\Main\Entity\AddResult
	 */
	protected function addLog(array $log)
	{
		$result = LogTable::add($log);
		$this->output->writeln('log', OutputInterface::VERBOSITY_VERY_VERBOSE);
		return $result;
	}

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->input = $input;
		$this->output = $output;

		if ($this->isMainCommand)
		{
			static::$reports = array();
			static::$reportTypeCounter = array();
			LogTable::deleteAll();
			static::checkFiles();
		}
		$this->separate();
		$this->output->writeln(Loc::getMessage(
				'INTERVOLGA_MIGRATO.COMMAND_STARTED',
				array(
					'#COMMAND#' => $this->getDescription(),
				)
			)
		);
		$this->separate();
		$this->executeInner();
		if ($this->isMainCommand)
		{
			$this->finalReport();
		}
	}

	abstract public function executeInner();

	/**
	 * @var string[]
	 */
	protected static $reports = array();
	/**
	 * @var int[]
	 */
	protected static $reportTypeCounter = array();

	protected static function checkFiles()
	{
		if (!Directory::isDirectoryExists(INTERVOLGA_MIGRATO_DIRECTORY))
		{
			Directory::createDirectory(INTERVOLGA_MIGRATO_DIRECTORY);
			CopyDirFiles(dirname(dirname(dirname(__DIR__))) . "/install/public", INTERVOLGA_MIGRATO_DIRECTORY);
		}
		if (!Config::isExists())
		{
			throw new \Exception(Loc::getMessage("INTERVOLGA_MIGRATO.CONFIG_NOT_FOUND"));
		}
	}

	public function finalReport()
	{
		if (static::$reportTypeCounter["fail"])
		{
			$report = Loc::getMessage(
				'INTERVOLGA_MIGRATO.PROCESS_COMPLETED_ERRORS',
				array(
					'#CMD#' => $this->getDescription(),
					'#CNT#' => static::$reportTypeCounter["fail"],
				)
			);
			$this->output->writeln($report);
		}
		else
		{
			$report = Loc::getMessage(
				'INTERVOLGA_MIGRATO.PROCESS_COMPLETED_OK',
				array(
					'#CMD#' => $this->getDescription(),
				)
			);
			$this->output->writeln($report);
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
	 * @param \Intervolga\Migrato\Data\BaseData[] $dataClasses
	 *
	 * @return \Intervolga\Migrato\Data\BaseData[]
	 */
	protected function recursiveGetDependentDataClasses(array $dataClasses)
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
			return $this->recursiveGetDependentDataClasses($dataClasses);
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
	protected function startStep($step)
	{
		$this->step = $step;
		$this->output->writeln(Loc::getMessage(
				'INTERVOLGA_MIGRATO.STEP',
				array(
					'#STEP#' => $this->step,
				)
			),
			OutputInterface::VERBOSITY_VERBOSE
		);
	}

	/**
	 * @param string $message
	 * @param string $type
	 * @param int $count
	 * @param int $option
	 */
	protected function report($message, $type = "", $count = 1, $option = 0)
	{
		if ($type)
		{
			static::$reportTypeCounter[$type] += $count;
		}
		if ($type == static::REPORT_TYPE_FAIL)
		{
			$message = '<fail>[fail]</fail> ' . $message;
		}
		if ($type == static::REPORT_TYPE_OK)
		{
			$message = '<ok>[ok]</ok> ' . $message;
		}
		if ($type == static::REPORT_TYPE_INFO)
		{
			$message = '<info>[info]</info> ' . $message;
		}
		$this->output->writeln($message, $option);
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
						"#MODULE#" => self::getModuleMessage($logs["MODULE_NAME"]),
						"#ENTITY#" => self::getEntityMessage($logs["ENTITY_NAME"]),
						"#OPERATION#" => $logs["OPERATION"],
						"#COUNT#" => $logs["CNT"],
					)
				),
				$logs["RESULT"] ? "ok" : "fail",
				$logs["CNT"]
			);
		}
	}

	protected static function getModuleMessage($moduleName)
	{
		$name = Loc::getMessage("INTERVOLGA_MIGRATO.MODULE_" . strtoupper($moduleName));
		if (!Loc::getMessage("INTERVOLGA_MIGRATO.MODULE_" . strtoupper($moduleName)))
		{
			$name = Loc::getMessage(
				"INTERVOLGA_MIGRATO.MODULE_UNKNOWN",
				array(
					"#MODULE#" => $moduleName,
				)
			);
		}
		return $name;
	}

	protected static function getEntityMessage($entityName)
	{
		return Loc::getMessage("INTERVOLGA_MIGRATO.ENTITY_" . strtoupper($entityName));
	}

	protected static function getStepMessage($stepName)
	{
		return Loc::getMessage("INTERVOLGA_MIGRATO.STEP_" . strtoupper(preg_replace("/\s\d+/", "", $stepName)));
	}
}