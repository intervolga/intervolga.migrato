<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ImportCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('import');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->runSubcommand('importdata');
		$this->runSubcommand('importoptions');
		$this->runSubcommand('clearcache');
		$this->runSubcommand('urlrewrite');
		$this->runSubcommand('reindex');
	}
}