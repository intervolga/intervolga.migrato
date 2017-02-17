<?namespace Intervolga\Migrato;

use Bitrix\Main\Config\Option;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\OptionFileViewXml;

class MigratoExportOption extends Migrato
{
	public static function run()
	{
		$result = array();
		$options = Config::getInstance()->getModulesOptions();
		foreach ($options as $module => $moduleOptions)
		{
			$export = array();
			foreach ($moduleOptions as $option)
			{
				$optionValue = Option::get($module, $option);
				$export[$option] = $optionValue;
			}
			ksort($export);

			$path = static::getModuleOptionsDirectory($module);
			OptionFileViewXml::writeToFileSystem($export, $path);
			$result[] = "Module $module options exported";
		}

		return $result;
	}
}