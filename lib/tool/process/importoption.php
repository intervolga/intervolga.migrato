<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Config\Option;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\OptionFileViewXml;

class ImportOption extends BaseProcess
{
	public static function run()
	{
		parent::run();
		$modules = Config::getInstance()->getModules();
		foreach ($modules as $module)
		{
			$path = static::getModuleOptionsDirectory($module);
			$options = OptionFileViewXml::readFromFileSystem($path);
			if ($options)
			{
				foreach ($options as $name => $value)
				{
					Option::set($module, $name, $value);
				}
				static::report("Module $module options imported");
			}
		}
	}
}