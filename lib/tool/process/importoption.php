<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\OptionFileViewXml;

class ImportOption extends BaseProcess
{
	public static function run()
	{
		parent::run();

		$directory = new Directory(INTERVOLGA_MIGRATO_DIRECTORY . "options/");
		if ($directory->isExists())
		{
			foreach ($directory->getChildren() as $dirOrFile)
			{
				if ($dirOrFile instanceof File)
				{
					$count = 0;
					$file = $dirOrFile;
					$module = str_replace(".xml", "", $file->getName());
					$options = OptionFileViewXml::readFromFileSystem($file->getPath());
					foreach ($options as $option)
					{
						if (Config::getInstance()->isOptionIncluded($option['NAME']))
						{
							Option::set($module, $option['NAME'], $option['VALUE'], $option['SITE_ID']);
							$count++;
						}
					}
					static::report("Module $module import $count option(s)");
				}
			}
		}
		parent::finalReport();
	}
}