<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Config\Option;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\OptionFileViewXml;

class ExportOption extends BaseProcess
{
	public static function run()
	{
		parent::run();
		$options = Config::getInstance()->getModulesOptions();
		foreach ($options as $module => $moduleOptions)
		{
			$count = 0;
			$export = array();
			foreach ($moduleOptions as $option)
			{
				$optionValue = Option::get($module, $option);
				$export[$option] = $optionValue;
				$count++;
			}
			ksort($export);

			$path = static::getModuleOptionsDirectory($module);
			OptionFileViewXml::writeToFileSystem($export, $path);
			static::report("Module $module export $count option(s)");
		}
		static::report("Process completed");
	}
}