<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataFileViewXml;

Loc::loadMessages(__FILE__);

class WarnDeleteCommand extends BaseCommand
{
	protected $willDelete = 0;
	protected $willAdd = 0;
	protected $totalRecords = 0;
	protected $databaseRecords = array();

	protected function configure()
	{
		$this->setHidden(true);
		$this->setName('warndelete');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.WARN_DELETE_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->willDelete = 0;
		$this->willAdd = 0;
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
		$fileRecordsXmlIds = $this->getFileRecordsXmlIds($dataClass);
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . '/';

		$databaseRecords = $dataClass->getList();
		foreach ($databaseRecords as $databaseRecord)
		{
			$this->totalRecords++;
			$this->checkRecord($databaseRecord, $fileRecordsXmlIds, $path);
		}


//		$tempFile = fopen($_SERVER['DOCUMENT_ROOT'] . '/log/__bx_log2.log', 'a');
//		fwrite(
//			$tempFile,
//			__FILE__ . ':' . __LINE__ . PHP_EOL . '(' . date('Y-m-d H:i:s').')' . PHP_EOL
//			. print_r($this->databaseRecords, TRUE)
//			. PHP_EOL . PHP_EOL
//		);
//		fclose($tempFile);
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

		return $fileRecordsXmlIds;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $databaseRecord
	 * @param array $fileRecordsXmlIds
	 * @param string $path
	 */
	protected function checkRecord(Record $databaseRecord, array $fileRecordsXmlIds, $path)
	{
		//$this->databaseRecords[$path][] = $databaseRecord->getXmlId();

		if (!in_array($databaseRecord->getXmlId(), $fileRecordsXmlIds))
		{
			$this->willDelete++;
			$this->logger->addDb(array(
				'RECORD' => $databaseRecord,
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_REMOVED'),
			));
		}
//		elseif (!in_array($databaseRecord->getXmlId(), $fileRecordsXmlIds))
//		{
//			$this->willAdd++;
//			$this->logger->addDb(array(
//				//'RECORD' => $databaseRecord,
//				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_ADD'),
//			));
//		}
		else
		{
			$this->logger->addDb(
				array(
					'RECORD' => $databaseRecord,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_SAVED'),
				),
				Logger::TYPE_OK
			);
		}
	}

	protected function addResult()
	{
		if ($this->willDelete)
		{
			if ($this->willDelete == $this->totalRecords)
			{
				$this->logger->add(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.ALL_WILL_BE_DELETED',
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
						'INTERVOLGA_MIGRATO.SOME_WILL_BE_DELETED',
						array(
							'#COUNT#' => $this->willDelete,
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
					'INTERVOLGA_MIGRATO.NOTHING_WILL_BE_DELETED',
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