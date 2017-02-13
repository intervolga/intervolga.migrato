<? namespace Intervolga\Migrato\Tool;

class Option
{
	public static function export()
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

	public static function import()
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