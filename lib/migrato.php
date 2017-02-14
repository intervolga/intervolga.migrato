<?namespace Intervolga\Migrato;

use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataRecordsResolveList;
use Intervolga\Migrato\Tool\OptionFileViewXml;

class Migrato
{
	/**
	 * @return array|string[]
	 */
	public static function exportData()
	{
		$result = array();
		foreach (Config::getInstance()->getDataClasses() as $data)
		{
			try
			{
				if (!$data->getXmlIdProvider()->isXmlIdFieldExists())
				{
					$data->getXmlIdProvider()->createXmlIdField();
				}
				$errors = $data->validateXmlIds();
				if ($errors)
				{
					$data->fixErrors($errors);
				}
				$errors = $data->validateXmlIds();
				if (!$errors)
				{
					$data->exportToFile();
					$result[] = "Data " . $data->getModule() . "/" . $data->getEntityName() . " exported to files";
				}
				else
				{
					$result[] = "Data " . $data->getModule() . "/" . $data->getEntityName() . " exported with errors (" . count($errors) . ")";
				}
			}
			catch (\Exception $exception)
			{
				$result[] = "Data " . $data->getModule() . "/" . $data->getEntityName() . " exported with exception: " . $exception->getMessage();
			}
		}
		return $result;
	}

	public static function importData()
	{
		$list = new DataRecordsResolveList();
		foreach (Config::getInstance()->getDataClasses() as $data)
		{
			$list->addDataRecords($data->readFromFile());
		}

		for ($i = 0; $i < count(Config::getInstance()->getDataClasses()) * 2; $i++)
		{
			$creatableDataRecords = $list->getCreatableDataRecords();
			if ($creatableDataRecords)
			{
				foreach ($creatableDataRecords as $dataRecord)
				{
					// TODO real resolve
					$list->setCreated($dataRecord);
				}
			}
			else
			{
				break;
			}
		}

		// TODO delete old records
	}

	public static function exportOptions()
	{
		$options = Config::getInstance()->getModulesOptions();
		foreach ($options as $module => $moduleOptions)
		{
			$export = array();
			foreach ($moduleOptions as $option)
			{
				$optionValue = \Bitrix\Main\Config\Option::get($module, $option);
				$export[$option] = $optionValue;
			}
			ksort($export);

			$path = static::getModuleOptionsDirectory($module);
			OptionFileViewXml::writeToFileSystem($export, $path);
		}
	}

	/**
	 * @param string $module
	 * @return string
	 */
	protected static function getModuleOptionsDirectory($module)
	{
		return INTERVOLGA_MIGRATO_DIRECTORY . $module . "/";
	}

	public static function importOptions()
	{
		$modules = Config::getInstance()->getModules();
		foreach ($modules as $module)
		{
			$path = static::getModuleOptionsDirectory($module);
			$options = OptionFileViewXml::readFromFileSystem($path);
			foreach ($options as $name => $value)
			{
				\Bitrix\Main\Config\Option::set($module, $name, $value);
			}
		}
	}
}