<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataFileViewXml;

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
		$this->willAdd = 0;
		$addedRecords = array();
		$configDataClasses = Config::getInstance()->getDataClasses();

		foreach ($configDataClasses as $dataClass)
		{
			$this->checkData($dataClass);
		}

		foreach ($this->filesInDirectories as $dirFiles => $files)
		{
			foreach ($this->filesInDatabase as $dirDatabase => $records)
			{
				if ($dirFiles == $dirDatabase) {
					$result = array_diff($files, $records);
					$addedRecords[] = array_values($result);
					$this->willAdd += count($result);
				}
			}
		}

		foreach ($configDataClasses as $dataClass)
		{
			$this->checkAddedRecords($dataClass, $addedRecords);
		}

//		foreach ($addedRecords as $records)
//		{
//			foreach ($records as $record)
//			{
//				$this->logger->addDb(array(
//					//'RECORD' => $databaseRecord,
//					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_ADD'),
//				));
//			}
//		}

		$this->addResult();
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	protected function checkData(BaseData $dataClass)
	{
		$this->getFileRecordsXmlIds($dataClass);
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . '/';

		$databaseRecords = $dataClass->getList();
		foreach ($databaseRecords as $databaseRecord)
		{
			$this->totalRecords++;
			$this->filesInDatabase[$path][] = $databaseRecord->getXmlId();
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param array $addedRecords
	 */
	protected function checkAddedRecords(BaseData $dataClass, $addedRecords)
	{
		$databaseRecords = $dataClass->getList();
		foreach ($databaseRecords as $key => $databaseRecord)
		{
			if ($addedRecords[$key])
			{
//				foreach ($addedRecords[$key] as $file)
//				{
//					$this->logger->addDb(array(
//						'RECORD' => $databaseRecord,
//						'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_ADD'),
//					));
//				}


			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return string[]
	 */
	protected function getFileRecordsXmlIds(BaseData $dataClass)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . '/';
		$fileRecords = DataFileViewXml::readFromFileSystem($path);
		$fileRecordsXmlIds = array();
		foreach ($fileRecords as $record)
		{
			if (!$record->getDeleteMark())
			{
				$fileRecordsXmlIds[] = $record->getXmlId();
			}
		}

		$this->filesInDirectories[$path] = $fileRecordsXmlIds;

		return $fileRecordsXmlIds;
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