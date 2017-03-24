<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\IO\Directory;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;

class ClearData extends BaseProcess
{
	protected static $cleared;

	public static function run()
	{
		parent::run();
		static::$cleared = array();

		$configDataClasses = Config::getInstance()->getDataClasses();
		foreach ($configDataClasses as $data)
		{
			static::clearData($data);
		}
		static::report("Process completed");
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	protected static function clearData(BaseData $dataClass)
	{
		if (!static::$cleared[$dataClass->getModule()])
		{
			$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . "/";
			$moduleDirectory = new Directory($path);
			$hasFiles = false;
			foreach ($moduleDirectory->getChildren() as $child)
			{
				if ($child->isDirectory())
				{
					$child->delete();
				}
				if ($child->isFile())
				{
					$hasFiles = true;
				}
			}
			if (!$hasFiles)
			{
				$moduleDirectory->delete();
			}
			static::report("Clear for module " . $dataClass->getModule());
			static::$cleared[$dataClass->getModule()] = true;
		}
	}
}