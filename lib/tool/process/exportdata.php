<? namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;

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
		static::report("Process completed");
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	protected static function exportData(BaseData $dataClass)
	{
		try
		{
			$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . "/";
			checkDirPath($path);
			DataFileViewXml::markDataDeleted($path);

			$filter = Config::getInstance()->getDataClassFilter($dataClass);
			$records = $dataClass->getList($filter);
			foreach ($records as $record)
			{
				DataFileViewXml::writeToFileSystem($record, $path);
			}

			static::reportData($dataClass, "exported (" . count($records) . ")");
		}
		catch (\Exception $exception)
		{
			static::reportDataException($dataClass, $exception, "export");
		}
	}
}