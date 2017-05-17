<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

abstract class BaseCommand extends Command
{
	const REPORT_TYPE_FAIL = 'fail';
	const REPORT_TYPE_OK = 'ok';
	const REPORT_TYPE_INFO = 'info';

	protected static $mainCommand = '';

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

	protected $shownDetailSummary = false;
	protected $shownShortSummary = false;

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
	protected function logRecord(array $log)
	{
		if (!array_key_exists('STEP', $log))
		{
			$log['STEP'] = $this->getDescription();
		}
		$result = LogTable::add($log);
		if (!array_key_exists('RESULT', $log))
		{
			$type = static::REPORT_TYPE_INFO;
		}
		else
		{
			if ($log['RESULT'])
			{
				$type = static::REPORT_TYPE_OK;
			}
			else
			{
				$type = static::REPORT_TYPE_FAIL;
			}
		}
		/**
		 * @var Record $record
		 */
		if ($record = $log['RECORD'])
		{
			if (!$this->shownDetailSummary)
			{
				$this->output->writeln(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.DETAIL_SUMMARY',
						array(
							'#COMMAND#' => $this->getDescription(),
						)
					),
					OutputInterface::VERBOSITY_VERY_VERBOSE
				);
				$this->shownDetailSummary = true;
			}
			$this->report(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.STATISTIC_ONE_RECORD',
					array(
						'#OPERATION#' => $log['OPERATION'],
						'#MODULE#' => self::getModuleMessage($record->getData()->getModule()),
						'#ENTITY#' => self::getEntityMessage($record->getData()->getEntityName()),
						'#DATA_XML_ID#' => $record->getXmlId(),
					)
				),
				$type,
				1,
				OutputInterface::VERBOSITY_VERY_VERBOSE
			);
		}
		else
		{
			die(__FILE__ . ':' . __LINE__);
		}
		return $result;
	}

	/**
	 * @return bool
	 */
	protected function isMainCommand()
	{
		return static::$mainCommand == get_called_class();
	}

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->input = $input;
		$this->output = $output;
		if (!static::$mainCommand)
		{
			static::$mainCommand = get_called_class();
		}

		if ($this->isMainCommand())
		{
			$this->reportTypeCounter = array();
			LogTable::deleteAll();
			$this->checkFiles();
			$this->output->writeln(Loc::getMessage(
				'INTERVOLGA_MIGRATO.COMMAND_STARTED',
				array(
					'#COMMAND#' => $this->getDescription(),
				)
			));
			$this->separate();
		}
		else
		{
			$this->output->writeln(Loc::getMessage(
				'INTERVOLGA_MIGRATO.SUBCOMMAND_STARTED',
				array(
					'#COMMAND#' => $this->getDescription(),
				)
			));
		}

		if ($this->isMainCommand())
		{

		}
		$this->executeInner();
		if ($this->isMainCommand())
		{
			$this->separate();
			$this->finalReport();
		}
	}

	abstract public function executeInner();

	/**
	 * @var int[]
	 */
	protected $reportTypeCounter = array();

	protected function checkFiles()
	{
		if (!Directory::isDirectoryExists(INTERVOLGA_MIGRATO_DIRECTORY))
		{
			Directory::createDirectory(INTERVOLGA_MIGRATO_DIRECTORY);
			CopyDirFiles(dirname(dirname(dirname(__DIR__))) . '/install/public', INTERVOLGA_MIGRATO_DIRECTORY);
		}
		if (!Config::isExists())
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.CONFIG_NOT_FOUND'));
		}
	}

	public function finalReport()
	{
		$this->output->writeln(
			Loc::getMessage(
				'INTERVOLGA_MIGRATO.COMMAND_COMPLETED',
				array(
					'#COMMAND#' => $this->getDescription(),
				)
			)
		);
		if ($this->reportTypeCounter[static::REPORT_TYPE_FAIL])
		{
			$this->output->writeln(Loc::getMessage(
				'INTERVOLGA_MIGRATO.PROCESS_COMPLETED_ERRORS',
				array(
					'#CNT#' => $this->reportTypeCounter[static::REPORT_TYPE_FAIL],
				)
			));
		}
		else
		{
			$this->output->writeln(Loc::getMessage('INTERVOLGA_MIGRATO.COMPLETED_OK'));
		}
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
	protected function getModuleOptionsDirectory($module)
	{
		return INTERVOLGA_MIGRATO_DIRECTORY . $module . '/';
	}

	/**
	 * @param string $message
	 * @param string $type
	 * @param int $count
	 * @param int $option
	 */
	protected function report($message, $type = '', $count = 1, $option = 0)
	{
		if ($type)
		{
			$this->reportTypeCounter[$type] += $count;
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

	protected function reportShortSummary()
	{
		$getList = LogTable::getList(array(
			'filter' => array(
				'=STEP' => $this->getDescription(),
			),
			'select' => array(
				'MODULE_NAME',
				'ENTITY_NAME',
				'OPERATION',
				'RESULT',
				new ExpressionField('CNT', 'COUNT(*)')
			),
			'group' => array(
				'MODULE_NAME',
				'ENTITY_NAME',
				'OPERATION',
				'RESULT',
			),
		));
		while ($logs = $getList->fetch())
		{
			if (!$this->shownShortSummary)
			{
				$this->output->writeln(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.LOGS_SUMMARY',
						array(
							'#COMMAND#' => $this->getDescription(),
						)
					),
					OutputInterface::VERBOSITY_VERBOSE
				);
				$this->shownShortSummary = true;
			}
			$this->report(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.STATISTICS_RECORD',
					array(
						'#MODULE#' => self::getModuleMessage($logs['MODULE_NAME']),
						'#ENTITY#' => self::getEntityMessage($logs['ENTITY_NAME']),
						'#OPERATION#' => $logs['OPERATION'],
						'#COUNT#' => $logs['CNT'],
					)
				),
				$logs['RESULT'] ? static::REPORT_TYPE_OK : static::REPORT_TYPE_FAIL,
				0,
				OutputInterface::VERBOSITY_VERBOSE
			);
		}
	}

	/**
	 * @param string $moduleName
	 *
	 * @return string
	 */
	protected function getModuleMessage($moduleName)
	{
		$name = Loc::getMessage('INTERVOLGA_MIGRATO.MODULE_' . strtoupper($moduleName));
		if (!Loc::getMessage('INTERVOLGA_MIGRATO.MODULE_' . strtoupper($moduleName)))
		{
			$name = Loc::getMessage(
				'INTERVOLGA_MIGRATO.MODULE_UNKNOWN',
				array(
					'#MODULE#' => $moduleName,
				)
			);
		}
		return $name;
	}

	/**
	 * @param string $entityName
	 *
	 * @return string
	 */
	protected function getEntityMessage($entityName)
	{
		$langName = Loc::getMessage('INTERVOLGA_MIGRATO.ENTITY_' . strtoupper($entityName));
		return $langName ? $langName : $entityName;
	}

	/**
	 * @param string $name
	 *
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function runSubcommand($name)
	{
		$command = $this->getApplication()->find($name);
		if ($command)
		{
			$command->run(new ArrayInput(array()), $this->output);
		}
	}
}