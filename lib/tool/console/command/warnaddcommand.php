<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Data\RecordId;

Loc::loadMessages(__FILE__);

class WarnAddCommand extends BaseCommand
{
	protected $willAdd = 0;
	protected $totalRecords = 0;
	protected $filesInDatabase = array();
	protected $filesInDirectories = array();

	protected function configure()
	{
		$this->setHidden(true);
		$this->setName('warnadd');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.WARN_ADD_DESCRIPTION'));
	}

	public function executeInner()
	{
		$configDataClasses = Config::getInstance()->getDataClasses();

		foreach ($configDataClasses as $dataClass)
		{
			$this->checkData($dataClass);
		}

		$this->addResult();
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	protected function checkData(BaseData $dataClass)
	{
		$recordsInFiles = self::getRecordsInFiles($dataClass);
		$recordsInDatabase = self::getRecordsInDatabase($dataClass);

		// вычислить расхождение между файлами и БД
		$recordsWillAdd = array_diff($recordsInFiles[$dataClass->getModule()][$dataClass->getEntityName()], $recordsInDatabase[$dataClass->getModule()][$dataClass->getEntityName()]);


		foreach ($recordsWillAdd as $record)
		{
			$this->willAdd++;
			$this->logger->addDb(array(
				'MODULE_NAME' => $dataClass->getModule(),
				'ENTITY_NAME' => $dataClass->getEntityName(),
				'XML_ID' => $record,
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_ADD'),
			));
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * return array
	 */
	protected function getRecordsInFiles(BaseData $dataClass)
	{
		$records = array();
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . '/';
		$fileRecords = DataFileViewXml::readFromFileSystem($path);

		foreach ($fileRecords as $record)
		{
			if (!$record->getDeleteMark())
			{
				$this->totalRecords++;
				$records[$dataClass->getModule()][$dataClass->getEntityName()][] = $record->getXmlId();
			}
		}

		return $records;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * return array
	 */
	protected function getRecordsInDatabase(BaseData $dataClass)
	{
		$records = array();
		$databaseRecords = $dataClass->getList();

		foreach ($databaseRecords as $databaseRecord)
		{
			$records[$dataClass->getModule()][$dataClass->getEntityName()][] = $databaseRecord->getXmlId();
		}

		return $records;
	}

	protected function addResult()
	{
		if ($this->willAdd)
		{
			if ($this->willAdd == $this->totalRecords)
			{
				$this->logger->add(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.ALL_WILL_BE_ADD',
						array(
							'#TOTAL#' => $this->totalRecords,
						)
					),
					0,
					Logger::TYPE_INFO
				);
			}
			else
			{
				$this->logger->add(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.SOME_WILL_BE_ADD',
						array(
							'#COUNT#' => $this->willAdd,
							'#TOTAL#' => $this->totalRecords,
						)
					),
					0,
					Logger::TYPE_INFO
				);
			}
		}
		else
		{
			$this->logger->add(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.NOTHING_WILL_BE_ADD',
					array(
						'#TOTAL#' => $this->totalRecords,
					)
				),
				0,
				Logger::TYPE_OK
			);
		}
	}
}