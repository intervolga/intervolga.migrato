<?php
namespace Intervolga\Migrato\Tool\Console;

use Bitrix\Main\Application;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Console\Command\BaseCommand;
use Intervolga\Migrato\Tool\DataList;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

class Logger
{
	const TYPE_INFO = 'info';
	const TYPE_OK = 'ok';
	const TYPE_FAIL = 'fail';

	const LEVEL_NORMAL = 0;
	const LEVEL_SHORT = 1;
	const LEVEL_DETAIL = 2;

	protected $shownDetailSummary = false;
	protected $shownShortSummary = false;
	protected $typesCounter = array();
	protected $finalMessages = array();

	protected $command;
	protected $output;
	protected $stepNumber = 0;
	protected $step = '';

	public function __construct(BaseCommand $command, OutputInterface $output)
	{
		$this->command = $command;
		$this->output = $output;
	}

	public function separate()
	{
		$this->add(str_repeat('-', 80));
	}

	public function clearLogs()
	{
		LogTable::deleteAll();
	}

	public function startCommand()
	{
		$this->add(Loc::getMessage(
			'INTERVOLGA_MIGRATO.COMMAND_STARTED',
			array(
				'#COMMAND#' => $this->command->getDescription(),
			)
		));
	}

	public function startSubcommand()
	{
		$this->separate();
		$this->add(Loc::getMessage(
			'INTERVOLGA_MIGRATO.SUBCOMMAND_STARTED',
			array(
				'#COMMAND#' => $this->command->getDescription(),
			)
		));
	}

	public function endCommand()
	{
		$this->separate();
		$this->add(Loc::getMessage(
			'INTERVOLGA_MIGRATO.COMMAND_COMPLETED',
			array(
				'#COMMAND#' => $this->command->getDescription(),
			)
		));
		if ($this->hasFinal())
		{
			$this->addFinal();
		}
		else
		{
			$this->addErrorsCount();
		}
	}

	/**
	 * @param string $message
	 * @param int $level
	 * @param string $type
	 */
	public function add($message, $level = 0, $type = '')
	{
		$option = OutputInterface::OUTPUT_NORMAL;
		if ($level == static::LEVEL_SHORT)
		{
			$option = OutputInterface::VERBOSITY_VERBOSE;
		}
		elseif ($level == static::LEVEL_DETAIL)
		{
			$option = OutputInterface::VERBOSITY_VERY_VERBOSE;
		}
		if ($type)
		{
			if ($type == static::TYPE_FAIL)
			{
				$message = '<fail>[fail]</fail> ' . $message;
			}
			if ($type == static::TYPE_OK)
			{
				$message = '<ok>[ ok ]</ok> ' . $message;
			}
			if ($type == static::TYPE_INFO)
			{
				$message = '<info>[info]</info> ' . $message;
			}
			$this->typesCounter[$type]++;
		}

		$this->output->writeln($message, $option);
	}

	/**
	 * @param array $dbLog
	 * @param string $type
	 *
	 * @return \Bitrix\Main\Entity\AddResult
	 */
	public function addDb(array $dbLog, $type = '')
	{
		$this->detailSummaryStart();
		if (!$dbLog['COMMAND'])
		{
			$dbLog['COMMAND'] = $this->command->getDescription();
		}
		if (!$dbLog['STEP'])
		{
			$dbLog['STEP'] = $this->step;
		}
		if (!$dbLog['STEP_NUMBER'])
		{
			$dbLog['STEP_NUMBER'] = $this->stepNumber;
		}
		if (!$type)
		{
			$type = static::TYPE_INFO;
		}
		if (!$dbLog['RESULT'])
		{
			$dbLog['RESULT'] = $type;
		}
		$result = LogTable::add($dbLog);
		$this->add(
			$this->getDbMessage($dbLog),
			static::LEVEL_DETAIL,
			$type
		);
		return $result;
	}

	protected function detailSummaryStart()
	{
		if (!$this->shownDetailSummary)
		{
			$this->add(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.DETAIL_SUMMARY',
					array(
						'#COMMAND#' => $this->command->getDescription(),
					)
				),
				static::LEVEL_DETAIL
			);
			$this->shownDetailSummary = true;
		}
	}

	/**
	 * @param array $dbLog
	 *
	 * @return string
	 */
	protected function getDbMessage(array $dbLog)
	{
		$replaces = $this->prepareDbMessageReplaces($dbLog);
		return Loc::getMessage('INTERVOLGA_MIGRATO.STATISTIC_DETAIL', $replaces);
	}

	/**
	 * @param array $dbLog
	 *
	 * @return array
	 */
	protected function prepareDbMessageReplaces(array $dbLog)
	{
		$replaces = array(
			'#OPERATION#' => $dbLog['OPERATION'],
			'#IDS#' => '',
			'#MODULE#' => $this->getModuleNameLoc($dbLog['MODULE_NAME']),
			'#ENTITY#' => $this->getEntityNameLoc($dbLog['MODULE_NAME'], $dbLog['ENTITY_NAME']),
		);
		$data = null;
		if ($dbLog['RECORD'])
		{
			/**
			 * @var \Intervolga\Migrato\Data\Record $record
			 */
			$record = $dbLog['RECORD'];
			$replaces['#IDS#'] = $this->getIdsString($record->getXmlId(), $record->getId());
			$data = $record->getData();
		}
		elseif ($dbLog['XML_ID_ERROR'])
		{
			/**
			 * @var \Intervolga\Migrato\Tool\XmlIdValidateError $error
			 */
			$error = $dbLog['XML_ID_ERROR'];
			$replaces['#IDS#'] = $this->getIdsString($error->getXmlId(), $error->getId());
			$data = $error->getDataClass();
		}
		elseif ($dbLog['ID'])
		{
			$replaces['#IDS#'] = $this->getIdsString('', $dbLog['ID']);
		}
		if ($data)
		{
			$replaces['#MODULE#'] = $this->getModuleNameLoc($data->getModule());
			$replaces['#ENTITY#'] = $this->getEntityNameLoc($data->getModule(), $data->getEntityName());
		}
		return $replaces;
	}

	/**
	 * @param string $xmlId
	 * @param \Intervolga\Migrato\Data\RecordId $id
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
			if (is_array($id->getValue()))
			{
				$begin = Loc::getMessage('INTERVOLGA_MIGRATO.COMPLEX_ID_BEGIN');
				$end = Loc::getMessage('INTERVOLGA_MIGRATO.COMPLEX_ID_END');
				$separator = Loc::getMessage('INTERVOLGA_MIGRATO.COMPLEX_ID_SEPARATOR');
				$stringId = $begin . implode($separator, $id->getValue()) . $end;
			}
			else
			{
				$stringId = $id->getValue();
			}
			$ids[] = Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_ID', array(
				'#ID#' => $stringId,
			));
		}
		return implode(', ', $ids);
	}

	/**
	 * @param string $message
	 * @param string $type
	 */
	public function registerFinal($message, $type = '')
	{
		$this->finalMessages[] = array(
			'message' => $message,
			'type' => $type,
		);
	}

	public function addErrorsCount()
	{
		if ($errors = $this->typesCounter[static::TYPE_FAIL])
		{
			$this->add(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.COMPLETED_ERRORS',
					array(
						'#CNT#' => $errors,
					)
				),
				0,
				static::TYPE_FAIL
			);
		}
		else
		{
			$this->add(
				Loc::getMessage('INTERVOLGA_MIGRATO.COMPLETED_OK'),
				0,
				static::TYPE_OK
			);
		}
	}

	/**
	 * @return bool
	 */
	public function hasFinal()
	{
		return count($this->finalMessages) > 0;
	}

	public function addFinal()
	{
		foreach ($this->finalMessages as $final)
		{
			$this->add($final['message'], 0, $final['type']);
		}
	}

	public function mergeTypesCounter(Logger $other)
	{
		foreach ($other->typesCounter as $type => $counter)
		{
			if ($counter)
			{
				$this->typesCounter[$type] += $counter;
			}
		}
	}

	public function resetTypesCounter()
	{
		$this->typesCounter = array();
	}

	/**
	 * @param string $moduleName
	 *
	 * @return string
	 */
	public function getModuleNameLoc($moduleName)
	{
		$modulesNames = $this->getModulesNamesLoc();
		if (array_key_exists($moduleName, $modulesNames))
		{
			$name = $modulesNames[$moduleName];
		}
		else
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
	 * @return string[]
	 */
	protected function getModulesNamesLoc()
	{
		static $result = array();
		if (!$result)
		{
			$result = $this->loadModulesNamesLoc();
		}

		return $result;
	}

	/**
	 * @return string[]
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	protected function loadModulesNamesLoc()
	{
		$result = array();
		$folders = array(
			'/local/modules/',
			'/bitrix/modules/',
		);
		foreach ($folders as $folder)
		{
			$modulesDirectory = new Directory(Application::getDocumentRoot() . $folder);
			if ($modulesDirectory->isExists())
			{
				foreach ($modulesDirectory->getChildren() as $moduleDirectory)
				{
					if ($moduleDirectory instanceof Directory)
					{
						if ($info = \CModule::createModuleObject($moduleDirectory->getName()))
						{
							$result[$info->MODULE_ID] = $info->MODULE_NAME;
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @param string $module
	 * @param string $entityName
	 *
	 * @return string
	 */
	public function getEntityNameLoc($module, $entityName)
	{
		$dataClass = DataList::get($module, $entityName);
		if ($dataClass instanceof BaseData)
		{
			$langName = $dataClass->getEntityNameLoc();
		}
		else
		{
			$langName = $entityName;
		}

		return $langName;
	}

	public function addShortSummary()
	{
		$getList = $this->getLogs();
		while ($logs = $getList->fetch())
		{
			$this->shortSummaryStart();
			$this->add(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.STATISTIC_SHORT',
					array(
						'#MODULE#' => $this->getModuleNameLoc($logs['MODULE_NAME']),
						'#ENTITY#' => $this->getEntityNameLoc($logs['MODULE_NAME'], $logs['ENTITY_NAME']),
						'#OPERATION#' => $logs['OPERATION'],
						'#COUNT#' => $logs['CNT'],
					)
				),
				static::LEVEL_SHORT,
				$logs['RESULT']
			);
			$this->typesCounter[$logs['RESULT']]--;
		}
	}

	/**
	 * @return \Bitrix\Main\DB\Result
	 */
	protected function getLogs()
	{
		return LogTable::getList(array(
			'filter' => array(
				'=COMMAND' => $this->command->getDescription(),
			),
			'order' => array(
				'STEP_NUMBER' => 'ASC',
				'MODULE_NAME' => 'ASC',
				'ENTITY_NAME' => 'ASC',
			),
			'select' => array(
				'MODULE_NAME',
				'ENTITY_NAME',
				'OPERATION',
				'RESULT',
				'STEP',
				new ExpressionField('CNT', 'COUNT(*)')
			),
			'group' => array(
				'MODULE_NAME',
				'ENTITY_NAME',
				'OPERATION',
				'RESULT',
				'STEP',
				'STEP_NUMBER',
			),
		));
	}

	protected function shortSummaryStart()
	{
		if (!$this->shownShortSummary)
		{
			$this->add(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.SHORT_SUMMARY',
					array(
						'#COMMAND#' => $this->command->getDescription(),
					)
				),
				static::LEVEL_SHORT
			);
			$this->shownShortSummary = true;
		}
	}

	/**
	 * @param string $step
	 */
	public function startStep($step)
	{
		$this->step = $step;
		$this->stepNumber++;
	}

	/**
	 * @param \Exception|\Throwable $error
	 */
	public function handle($error)
	{
		$formattedName = Loc::getMessage(
			'INTERVOLGA_MIGRATO.ERROR',
			array(
				'#CLASS#' => get_class($error)
			)
		);
		$formattedMessage = Loc::getMessage(
			'INTERVOLGA_MIGRATO.ERROR_MESSAGE_CODE',
			array(
				'#MESSAGE#' => $error->getMessage(),
				'#CODE#' => $error->getCode(),
			)
		);
		$this->add($formattedName, static::LEVEL_NORMAL);
		$this->add($formattedMessage, static::LEVEL_SHORT);
		$this->add(Loc::getMessage('INTERVOLGA_MIGRATO.BACKTRACE'), static::LEVEL_DETAIL);
		$this->add('## ' . $error->getFile() . '(' . $error->getLine() . ')', static::LEVEL_SHORT);
		$this->add($error->getTraceAsString(), static::LEVEL_DETAIL);
	}
}