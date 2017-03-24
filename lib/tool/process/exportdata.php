<? namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\Orm\LogTable;

class ExportData extends BaseProcess
{
	public static function run()
	{
		parent::run();

		$errors = static::validate();
		if ($errors)
		{
			static::fixErrors($errors);
			$errors = static::validate();
		}
		if ($errors)
		{
			throw new \Exception("Validated with errors (" . count($errors) . ")");
		}

		$configDataClasses = Config::getInstance()->getDataClasses();
		foreach ($configDataClasses as $data)
		{
			static::exportData($data);
		}
		static::reportStep("Export");
		static::report("Process completed");
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	protected static function exportData(BaseData $dataClass)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . "/";
		checkDirPath($path);
		DataFileViewXml::markDataDeleted($path);

		$filter = Config::getInstance()->getDataClassFilter($dataClass);
		$records = $dataClass->getList($filter);
		foreach ($records as $record)
		{
			try
			{
				DataFileViewXml::writeToFileSystem($record, $path);
				LogTable::add(array(
					"RECORD" => $record,
					"OPERATION" => "export",
					"STEP" => "Export",
				));
			}
			catch (\Exception $exception)
			{
				LogTable::add(array(
					"RECORD" => $record,
					"EXCEPTION" => $exception,
					"OPERATION" => "export",
					"STEP" => "Export",
				));
			}
		}
	}
}