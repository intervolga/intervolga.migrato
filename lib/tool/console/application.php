<?namespace Intervolga\Migrato\Tool\Console;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Command\AutofixCommand;
use Intervolga\Migrato\Tool\Console\Command\ClearCacheCommand;
use Intervolga\Migrato\Tool\Console\Command\ExportCommand;
use Intervolga\Migrato\Tool\Console\Command\ExportDataCommand;
use Intervolga\Migrato\Tool\Console\Command\ExportOptionCommand;
use Intervolga\Migrato\Tool\Console\Command\GenerateCommand;
use Intervolga\Migrato\Tool\Console\Command\ImportCommand;
use Intervolga\Migrato\Tool\Console\Command\ImportDataCommand;
use Intervolga\Migrato\Tool\Console\Command\ImportOptionCommand;
use Intervolga\Migrato\Tool\Console\Command\ImportXmlIdCommand;
use Intervolga\Migrato\Tool\Console\Command\LogCommand;
use Intervolga\Migrato\Tool\Console\Command\ReIndexCommand;
use Intervolga\Migrato\Tool\Console\Command\UnitTestCommand;
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
			new GenerateCommand(),
			new ExportOptionCommand(),
			new ImportOptionCommand(),
			new ExportDataCommand(),
			new ImportDataCommand(),
			new UnitTestCommand(),
			new ExportCommand(),
			new ImportCommand(),
			new ImportXmlIdCommand(),
			new LogCommand(),
		));
	}

	protected function configureIO(InputInterface $input, OutputInterface $output)
	{
		$output->setDecorated(true);
		parent::configureIO($input, $output);
		if (true === $input->hasParameterOption(array('--win', '-W'), true))
		{
			$formatter = $output->getFormatter();
			if ($formatter instanceof Formatter)
			{
				$formatter->setWindowsCharset(true);
			}
		}
		if (true === $input->hasParameterOption(array('--utf', '-U'), true))
		{
			$formatter = $output->getFormatter();
			if ($formatter instanceof Formatter)
			{
				$formatter->setUnicodeCharset(true);
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

		$option = new InputOption(
			'--utf',
			'-U',
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.CONVERT_TO_UTF_8')
		);
		$inputDefinition->addOption($option);

		$option = new InputOption(
			'--fails',
			'-F',
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.SHOW_LOGS')
		);
		$inputDefinition->addOption($option);
		return $inputDefinition;
	}
}