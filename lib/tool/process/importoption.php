<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Config\Option;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\OptionFileViewXml;

class ImportOption extends BaseProcess
{
	public static function run()
	{
		parent::run();
		$options = Config::getInstance()->getModulesOptions();
		foreach ($options as $module => $moduleOptions)
		{
			if ($moduleOptions)
			{
				$count = 0;
				$path = static::getModuleOptionsDirectory($module);
				$options = OptionFileViewXml::readFromFileSystem($path);
				foreach ($options as $name => $value)
				{
					if (in_array($name, $moduleOptions))
					{
						Option::set($module, $name, $value);
						$count++;
					}
				}
				static::report("Module $module import $count option(s)");
			}
		}
		static::report("Process completed");
	}
}