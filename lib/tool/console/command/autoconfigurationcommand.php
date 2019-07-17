<?namespace Intervolga\Migrato\Tool\Console\Command;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class AutoconfigurationCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('autoconfiguration');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.AUTOCONFIG_DESCRIPTION'));
	}

	public function executeInner()
	{
		self::getConfigXML();
	}

	protected static function getConfigXML()
	{
		$configXML =  file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/local/migrato/config.xml");
	}
}