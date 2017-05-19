<?namespace Intervolga\Migrato\Tool\Console;

use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Console\Command\BaseCommand;
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

	public function __construct(BaseCommand $command, OutputInterface $output)
	{
		$this->command = $command;
		$this->output = $output;
	}

	public function separate()
	{
		$this->add(str_repeat('-', 80));
	}

	public function startCommand()
	{
		LogTable::deleteAll();
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
	 * @throws \Exception
	 */
	public function addDb(array $dbLog, $type = '')
	{
		$this->detailSummaryStart();
		if (!$dbLog['STEP'])
		{
			$dbLog['STEP'] = $this->command->getDescription();
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
	 * @throws \Exception
	 */
	protected function getDbMessage(array $dbLog)
	{
		$replaces = $this->prepareDbMessageReplaces($dbLog);
		if ($replaces['#ENTITY#'])
		{
			return Loc::getMessage('INTERVOLGA_MIGRATO.STATISTIC_DETAIL', $replaces);
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.INVALID_LOG_FORMAT'));
		}
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
		if ($data)
		{
			$replaces['#MODULE#'] = $this->getModuleMessage($data->getModule());
			$replaces['#ENTITY#'] = $this->getEntityMessage($data->getEntityName());
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
				$stringId = implode(';', $id->getValue());
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
	public function getModuleMessage($moduleName)
	{
		$name = Loc::getMessage('INTERVOLGA_MIGRATO.MODULE_' . strtoupper($moduleName));
		if (!$name)
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
	public function getEntityMessage($entityName)
	{
		$langName = Loc::getMessage('INTERVOLGA_MIGRATO.ENTITY_' . strtoupper($entityName));
		return $langName ? $langName : $entityName;
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
						'#MODULE#' => self::getModuleMessage($logs['MODULE_NAME']),
						'#ENTITY#' => self::getEntityMessage($logs['ENTITY_NAME']),
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
				'=STEP' => $this->command->getDescription(),
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
}