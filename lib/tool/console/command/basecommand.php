<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

/**
 * @field \Symfony\Component\Console\Input\InputInterface $input
 */
abstract class BaseCommand extends Command
{
	const REPORT_TYPE_FAIL = 'fail';
	const REPORT_TYPE_OK = 'ok';
	const REPORT_TYPE_INFO = 'info';

	protected static $mainCommand = '';

	protected $step = '';
	protected $shownDetailSummary = false;
	protected $shownShortSummary = false;
	protected $customFinalReport = '';
	protected $reportTypeCounter = array();

	/**
	 * @var \Symfony\Component\Console\Output\OutputInterface $output
	 */
	protected $output = null;

	abstract public function executeInner();

	/**
	 * @return int[]
	 */
	public function getReportTypesCounter()
	{
		return $this->reportTypeCounter;
	}

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->output = $output;
		if ($this->isMainCommand())
		{
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
		$this->executeInner();
		if ($this->isMainCommand())
		{
			$this->separate();
			$this->finalReport();
		}
	}

	/**
	 * @return bool
	 */
	protected function isMainCommand()
	{
		if (!static::$mainCommand)
		{
			static::$mainCommand = get_called_class();
			return true;
		}
		return (static::$mainCommand == get_called_class());
	}

	/**
	 * @throws \Exception
	 */
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

	/**
	 * @param int $options
	 */
	protected function separate($options = 0)
	{
		$this->output->writeln(str_repeat('-', 80), $options);
	}

	protected function finalReport()
	{
		$this->output->writeln(Loc::getMessage(
			'INTERVOLGA_MIGRATO.COMMAND_COMPLETED',
			array(
				'#COMMAND#' => $this->getDescription(),
			)
		));
		if ($this->customFinalReport)
		{
			$this->output->writeln($this->customFinalReport);
		}
		else
		{
			if ($this->reportTypeCounter[static::REPORT_TYPE_FAIL])
			{
				$this->output->writeln(Loc::getMessage(
					'INTERVOLGA_MIGRATO.COMPLETED_ERRORS',
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
	}

	/**
	 * @param array $log
	 *
	 * @return \Bitrix\Main\Entity\AddResult
	 */
	protected function logRecord(array $log)
	{
		$log['STEP'] = $log['STEP'] ? $log['STEP'] : $this->getDescription();
		$result = LogTable::add($log);
		$this->detailSummaryStart();
		$this->report(
			$this->getLogReportMessage($log),
			$this->getLogReportType($log),
			1,
			OutputInterface::VERBOSITY_VERY_VERBOSE
		);

		return $result;
	}

	protected function detailSummaryStart()
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
	}

	/**
	 * @param array $log
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function getLogReportMessage(array $log)
	{
		$replaces = $this->prepareLogReportMessageReplaces($log);
		if ($replaces['#ENTITY#'])
		{
			return Loc::getMessage('INTERVOLGA_MIGRATO.STATISTIC_ONE_RECORD', $replaces);
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.INVALID_LOG_FORMAT'));
		}
	}

	/**
	 * @param array $log
	 *
	 * @return string[]
	 */
	protected function prepareLogReportMessageReplaces(array $log)
	{
		$replaces = array(
			'#OPERATION#' => $log['OPERATION'],
			'#IDS#' => '',
		);
		$data = null;
		if ($log['RECORD'])
		{
			/**
			 * @var Record $record
			 */
			$record = $log['RECORD'];
			$replaces['#IDS#'] = $this->getIdsString($record->getXmlId(), $record->getId());
			$data = $record->getData();
		}
		elseif ($log['XML_ID_ERROR'])
		{
			/**
			 * @var \Intervolga\Migrato\Tool\XmlIdValidateError $error
			 */
			$error = $log['XML_ID_ERROR'];
			$replaces['#IDS#'] = $this->getIdsString($error->getXmlId(), $error->getId());
			$data = $error->getDataClass();
		}
		if ($data)
		{
			$replaces['#MODULE#'] = static::getModuleMessage($data->getModule());
			$replaces['#ENTITY#'] = static::getEntityMessage($data->getEntityName());
		}
		return $replaces;
	}

	/**
	 * @param string $xmlId
	 * @param \Intervolga\Migrato\Data\RecordId|null $id
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function getIdsString($xmlId = '', RecordId $id = null)
	{
		$ids = array();
		if ($xmlId)
		{
			$ids[] = Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_XML_ID', array(
				'#XML_ID#' => $xmlId,
			));
		}
		if ($id)
		{
			$ids[] = Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_ID', array(
				'#ID#' => (string) $id->getValue(),
			));
		}
		return implode(', ', $ids);
	}

	/**
	 * @param array $log
	 *
	 * @return string
	 */
	protected function getLogReportType(array $log)
	{
		if (!array_key_exists('RESULT', $log))
		{
			$type = static::REPORT_TYPE_INFO;
		}
		else
		{
			$type = $log['RESULT'] ? static::REPORT_TYPE_OK : static::REPORT_TYPE_FAIL;
		}
		return $type;
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
			$this->shortSummaryStart();
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

	protected function shortSummaryStart()
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
	 * @return \Symfony\Component\Console\Command\Command
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function runSubcommand($name)
	{
		$command = $this->getApplication()->find($name);
		if ($command)
		{
			$command->run(new ArrayInput(array()), $this->output);
			if ($command instanceof BaseCommand)
			{
				foreach ($command->getReportTypesCounter() as $type => $counter)
				{
					if ($counter)
					{
						$this->reportTypeCounter[$type] += $counter;
					}
				}
			}
		}

		return $command;
	}
}