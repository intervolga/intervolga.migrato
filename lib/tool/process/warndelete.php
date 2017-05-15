<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\Orm\LogTable;

Loc::loadMessages(__FILE__);

class WarnDelete extends BaseProcess
{
	/**
	 * @var bool
	 */
	protected static $willDelete;

	public static function run()
	{
		parent::run();
		static::warnDelete();
		parent::finalReport();
	}

	public static function warnDelete()
	{
		static::startStep(Loc::getMessage('INTERVOLGA_MIGRATO.WARN_DELETE'));
		static::$willDelete = false;
		$configDataClasses = Config::getInstance()->getDataClasses();
		foreach ($configDataClasses as $dataClass)
		{
			static::checkData($dataClass);
		}
		if (!static::$willDelete)
		{
			static::report(Loc::getMessage('INTERVOLGA_MIGRATO.NOTHING_WILL_BE_DELETED'));
		}
		static::reportStepLogs();
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return string[]
	 */
	protected static function getFileRecordsXmlIds(BaseData $dataClass)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . "/";
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
	protected static function checkData(BaseData $dataClass)
	{
		$fileRecordsXmlIds = static::getFileRecordsXmlIds($dataClass);
		$databaseRecords = $dataClass->getList();
		foreach ($databaseRecords as $databaseRecord)
		{
			static::checkRecord($databaseRecord, $fileRecordsXmlIds);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $databaseRecord
	 * @param array $fileRecordsXmlIds
	 */
	protected static function checkRecord(Record $databaseRecord, array $fileRecordsXmlIds)
	{
		if (!in_array($databaseRecord->getXmlId(), $fileRecordsXmlIds))
		{
			static::$willDelete = true;
			LogTable::add(array(
				"RECORD" => $databaseRecord,
				"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_WILL_BE_REMOVED'),
				"STEP" => static::$step,
			));
		}
	}
}