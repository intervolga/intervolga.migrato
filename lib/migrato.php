<?namespace Intervolga\Migrato;

use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\OptionFileViewXml;

class Migrato
{
	public static function exportData()
	{
		foreach (Config::getInstance()->getDataClasses() as $data)
		{
			if (!$data->isXmlIdFieldExists())
			{
				$data->createXmlIdField();
			}
			$errors = $data->validateXmlIds();
			if ($errors)
			{
				$data->fixErrors($errors);
				$errors = $data->validateXmlIds();
				if ($errors)
				{
					$data->fixErrors($errors);
				}
			}
			$data->exportToFile();
		}
	}

	public static function importData()
	{
		$dataRecords = array();
		foreach (Config::getInstance()->getDataClasses() as $data)
		{
			$dataRecords = array_merge($dataRecords, $data->readFromFile());
		}
		$dataArray = array();
		foreach ($dataRecords as $dataRecord)
		{
			$data = array();
			/**
			 * @var DataRecord $dataRecord
			 */
			$data["XML_ID"] = $dataRecord->getXmlId();
			$data["DEPENDENCIES"] = $dataRecord->getDependencies();
			$data["DATA"] = $dataRecord->getData();
			$dataArray[] = $data;
		}
		/*foreach (Foo::getResolvedDependencyData() as $dataPack)
		{
			foreach ($dataPack as $data)
			{
				Foo::save($data);
			}
			Foo::updateDependencies();
		}*/
		// create references
		return $dataArray;
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