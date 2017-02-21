<? namespace Intervolga\Migrato;

use Bitrix\Main\IO\Directory;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;

class MigratoExportData extends Migrato
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
			$filter = Config::getInstance()->getDataClassFilter($data);
			try
			{
				static::exportToFile($data, $filter);
				static::reportData($data, "exported");
			}
			catch (\Exception $exception)
			{
				static::reportDataException($data, $exception, "export");
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param array|string[] $filter
	 */
	protected static function exportToFile(BaseData $dataClass, array $filter = array())
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . "/";
		Directory::deleteDirectory($path);
		checkDirPath($path);

		$records = $dataClass->getList($filter);
		foreach ($records as $record)
		{
			DataFileViewXml::writeToFileSystem($record, $path);
		}
	}
}