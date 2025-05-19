<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

class ExportCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('export');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.EXPORT_DESCRIPTION'));
		$this->addOption(
			'clean-deleted',
			null,
			InputOption::VALUE_NONE,
			'Удалять файлы, а не проставлять deleted="true"'
		);
	}

	public function executeInner()
	{
		$this->runSubcommand('exportdata');
		$this->runSubcommand('exportoptions');
		if ($this->input->getOption('clean-deleted'))
		{
			$this->runSubcommand('cleandeletedxml');
		}
	}
}