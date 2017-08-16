<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

class ImportXmlIdCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('importxmlid');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_XMLID_DESCRIPTION'));
		$this->addArgument(
			'module',
			InputArgument::REQUIRED,
			Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_XMLID_MODULE')
		);
		$this->addArgument(
			'data',
			InputArgument::REQUIRED,
			Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_XMLID_DATA')
		);
		$this->addArgument(
			'xmlid',
			InputArgument::REQUIRED,
			Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_XMLID_XMLID')
		);

		$this->addOption(
			'quick',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.NO_AFTER_CLEAR_DESCRIPTION')
		);
	}

	public function executeInner()
	{
	}
}