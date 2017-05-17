<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\Orm\LogTable;

Loc::loadMessages(__FILE__);

class WarnDeleteCommand extends BaseCommand
{
	/**
	 * @var int
	 */
	protected $willDelete;

	protected function configure()
	{
		parent::configure();
		$this
			->setName('warndelete')
			->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.WARN_DELETE_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->willDelete = 0;
		$configDataClasses = Config::getInstance()->getDataClasses();
		foreach ($configDataClasses as $dataClass)
		{
			$this->checkData($dataClass);
		}
		if ($this->willDelete)
		{
			$this->report(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.SOME_WILL_BE_DELETED',
					array(
						'#COUNT#' => $this->willDelete,
					)
				),
				static::REPORT_TYPE_INFO,
				0
			);
		}
		else
		{
			$this->report(
				Loc::getMessage('INTERVOLGA_MIGRATO.NOTHING_WILL_BE_DELETED'),
				static::REPORT_TYPE_INFO,
				0
			);
		}
		$this->reportShortSummary();
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
			$fileRecordsXmlIds[] = $record->getXmlId();
		}

		return $fileRecordsXmlIds;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	protected function checkData(BaseData $dataClass)
	{
		$fileRecordsXmlIds = $this->getFileRecordsXmlIds($dataClass);
		$databaseRecords = $dataClass->getList();
		foreach ($databaseRecords as $databaseRecord)
		{
			$this->checkRecord($databaseRecord, $fileRecordsXmlIds);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $databaseRecord
	 * @param array $fileRecordsXmlIds
	 */
	protected function checkRecord(Record $databaseRecord, array $fileRecordsXmlIds)
	{
		if (!in_array($databaseRecord->getXmlId(), $fileRecordsXmlIds))
		{
			$this->willDelete++;
			$this->logRecord(array(
				'RECORD' => $databaseRecord,
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_REMOVED'),
			));
		}
		else
		{
			$this->logRecord(array(
				'RECORD' => $databaseRecord,
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_SAVED'),
			));
		}
	}

}