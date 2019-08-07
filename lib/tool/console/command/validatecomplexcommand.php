<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ValidateComplexCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('validate');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.VALIDATE_COMPLEX_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->runSubcommand('validatexmlid');
		$this->runSubcommand('unused');
		$this->runSubcommand('warndelete');
		$this->runSubcommand('warnadd');
	}
}