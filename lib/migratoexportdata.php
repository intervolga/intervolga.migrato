<?namespace Intervolga\Migrato;

use Bitrix\Main\IO\Directory;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;

class MigratoExportData extends Migrato
{
	public static function run()
	{
		$result = array();
		$configDataClasses = Config::getInstance()->getDataClasses();
		$dataClasses = static::recursiveGetDependentDataClasses($configDataClasses);
		foreach ($dataClasses as $data)
		{
			$filter = Config::getInstance()->getDataClassFilter($data);
			try
			{
				if (!$data->getXmlIdProvider()->isXmlIdFieldExists())
				{
					$data->getXmlIdProvider()->createXmlIdField();
				}
				$errors = static::validateXmlIds($data, $filter);
				if ($errors)
				{
					static::fixErrors($data, $errors);
				}
				$errors = static::validateXmlIds($data, $filter);
				if ($errors)
				{
					throw new \Exception("Validated with errors (" . count($errors) . ")");
				}
			}
			catch (\Exception $exception)
			{
				$result[] = "Data " . $data->getModule() . "/" . $data->getEntityName() . " exported with exception: " . $exception->getMessage();
			}
		}

		foreach ($configDataClasses as $data)
		{
			$filter = Config::getInstance()->getDataClassFilter($data);
			try
			{
				static::exportToFile($data, $filter);
				$result[] = "Data " . $data->getModule() . "/" . $data->getEntityName() . " exported to files";
			}
			catch (\Exception $exception)
			{
				$result[] = "Data " . $data->getModule() . "/" . $data->getEntityName() . " exported with exception: " . $exception->getMessage();
			}
		}
		return $result;
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