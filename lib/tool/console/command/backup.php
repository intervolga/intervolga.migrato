<?
namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\IO\File;
use Intervolga\Migrato\Data\Module;

Loc::loadMessages(__FILE__);

class Backup extends BaseCommand
{
	protected function configure()
	{
		$this->setName('backup');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.BACKUP_DESCRIPTION'));
	}
	public function executeInner()
	{

	}
}