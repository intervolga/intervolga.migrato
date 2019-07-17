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
		$arResult = array();

		$configXML = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/local/migrato/config.xml");
		$xml = new \CDataXML();
		$xml->LoadString($configXML);
		$arData = $xml->GetArray();

		foreach ($arData['config']['#']['module'] as $module)
		{
			$arEntities = array();

			foreach ($module['#']['entity'] as $entity)
			{
				$arEntities[] = $entity['#']['name'][0]['#'];
			}

			$arResult[$module['#']['name'][0]['#']] = $arEntities;
		}

		return $arResult;
	}

	protected static function getInstalledModules()
	{

	}
}