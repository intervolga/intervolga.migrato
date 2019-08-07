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


		// найди расходящиеся сущности, чтобы потом применить $this->logger->addDb
		foreach ($this->filesInDirectories as $module => $entity)
		{
			foreach ($entity as $record)
			{
				//"\Intervolga\Migrato\Data\\" . $module->findRecord($record);
			}
		}




//		foreach ($this->filesInDirectories as $dirFiles => $files)
//		{
//			foreach ($this->filesInDatabase as $dirDatabase => $records)
//			{
//				if ($dirFiles == $dirDatabase) {
//					$result = array_diff($files, $records);
//					$addedRecords[$dirFiles] = array_values($result);
//					$this->willAdd += count($result);
//				}
//			}
//		}


//		foreach ($addedRecords as $dir => $records)
//		{
//			if(count($records) > 0)
//			{
//				$this->logger->add(
//					Loc::getMessage(
//						'INTERVOLGA_MIGRATO.RECORDS_WILL_BE_ADD',
//						array(
//							'#PATH#' => $dir,
//							'#TOTAL#' => count($records),
//						)
//					),
//					0,
//					Logger::TYPE_INFO
//				);
//			}
//
////			foreach ($configDataClasses as $dataClass)
////			{
////				$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . '/';
////
////				if($path == $dir)
////				{
////					$this->checkAddedRecords($dataClass, $records);
////				}
////			}
//
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
			$this->filesInDatabase[$dataClass->getModule()][$dataClass->getEntityName()][] = $databaseRecord->getXmlId();
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param array $records
	 */
	protected function checkAddedRecords(BaseData $dataClass, $records)
	{
		$databaseRecords = $dataClass->getList();


//		foreach ($databaseRecords as $databaseRecord)
//		{
//			if (!in_array($databaseRecord->getXmlId(), $records))
//			{
//				$this->logger->addDb(array(
//					'RECORD' => $databaseRecord,
//					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORDS_WILL_BE_ADD'),
//				));
//			}
//		}


//		foreach ($records as $records)
//		{
//
//			$this->logger->addDb(array(
//				//'RECORD' => $databaseRecord,
//				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_ADD'),
//			));
//
//		}

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
		$this->filesInDirectories[$dataClass->getModule()][$dataClass->getEntityName()] = $fileRecordsXmlIds;

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