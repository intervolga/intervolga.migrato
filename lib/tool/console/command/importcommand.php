<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

class ImportCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('import');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_DESCRIPTION'));
		$this->addOption(
			'quick',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.NO_AFTER_CLEAR_DESCRIPTION')
		);
	}

	public function executeInner()
	{
		$this->runSubcommand('importdata');
		$this->runSubcommand('importoptions');
		if (!$this->input->getOption('quick'))
		{
			$this->runSubcommand('clearcache');
			$this->runSubcommand('urlrewrite');
			$this->runSubcommand('reindex');
		}
	}
}