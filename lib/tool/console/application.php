<?namespace Intervolga\Migrato\Tool\Console;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Command\AutofixCommand;
use Intervolga\Migrato\Tool\Console\Command\ClearCacheCommand;
use Intervolga\Migrato\Tool\Console\Command\ReIndexCommand;
use Intervolga\Migrato\Tool\Console\Command\UnusedConfigCommand;
use Intervolga\Migrato\Tool\Console\Command\UrlRewriteCommand;
use Intervolga\Migrato\Tool\Console\Command\ValidateCommand;
use Intervolga\Migrato\Tool\Console\Command\ValidateComplexCommand;
use Intervolga\Migrato\Tool\Console\Command\WarnDeleteCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

class Application extends \Symfony\Component\Console\Application
{
	public function __construct()
	{
		$moduleDir = dirname(dirname(dirname(__DIR__)));
		$moduleName = basename($moduleDir);
		$arModuleVersion = array('VERSION' => '');
		include $moduleDir . '/install/version.php';
		parent::__construct($moduleName, $arModuleVersion['VERSION']);

		$this->addCommands(array(
			new ValidateComplexCommand(),
			new ValidateCommand(),
			new UnusedConfigCommand(),
			new WarnDeleteCommand(),
			new AutofixCommand(),
			new ClearCacheCommand(),
			new ReIndexCommand(),
			new UrlRewriteCommand(),
		));
	}

	protected function configureIO(InputInterface $input, OutputInterface $output)
	{
		parent::configureIO($input, $output);
		if (true === $input->hasParameterOption(array('--win', '-W'), true))
		{
			$formatter = $output->getFormatter();
			if ($formatter instanceof Formatter)
			{
				$formatter->setWindowsCharset(true);
			}
		}
	}

	protected function getDefaultInputDefinition()
	{
		$inputDefinition = parent::getDefaultInputDefinition();
		$option = new InputOption(
			'--win',
			'-W',
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.CONVERT_TO_WIN_1251')
		);
		$inputDefinition->addOption($option);
		return $inputDefinition;
	}
}