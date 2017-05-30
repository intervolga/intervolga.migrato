<?namespace Intervolga\Migrato\Tool;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Application;
use Intervolga\Migrato\Tool\Console\Formatter;
use Symfony\Component\Console\Output\ConsoleOutput;

Loc::loadMessages(__FILE__);

class Page
{
	public static function run()
	{
		Page::checkRights();
		$application = new Application();
		$application->run(null, new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, null, new Formatter()));
	}

	/**
	 * @throws \Exception
	 */
	protected static function checkRights()
	{
		global $USER;
		if (static::isCli())
		{
			$USER->Authorize(1);
		}
		else
		{
			die(Loc::getMessage('INTERVOLGA_MIGRATO.NEED_CLI'));
		}
	}

	/**
	 * @return bool
	 */
	protected static function isCli()
	{
		return php_sapi_name() == 'cli';
	}
}