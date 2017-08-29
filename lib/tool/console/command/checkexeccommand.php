<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class CheckExecCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('checkexec');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.CHECK_EXEC_DESCRIPTION'));
	}

	public function executeInner()
	{

	}
}