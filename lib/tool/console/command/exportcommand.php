<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ExportCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('export');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.EXPORT_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->runSubcommand('exportdata');
		$this->runSubcommand('exportoptions');
	}
}