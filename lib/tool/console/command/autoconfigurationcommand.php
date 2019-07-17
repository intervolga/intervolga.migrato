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
		//self::getConfigData();
		//self::getInstalledModules();
	}

	protected static function getConfigData()
	{
		$configData = array();

		$configXML = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/local/migrato/config.xml");
		$xml = new \CDataXML();
		$xml->LoadString($configXML);
		$arDataXML = $xml->GetArray();

		foreach ($arDataXML['config']['#']['module'] as $module)
		{
			$arEntities = array();

			foreach ($module['#']['entity'] as $entity)
			{
				$arEntities[] = $entity['#']['name'][0]['#'];
			}

			$configData[$module['#']['name'][0]['#']] = $arEntities;
		}

		return $configData;
	}

	protected static function getInstalledModules()
	{
		$installedModules = array();

		$rsInstalledModules = \CModule::GetList();
		while ($module = $rsInstalledModules->Fetch())
		{
			$installedModules[] = $module['ID'];
		}

		return $installedModules;
	}

	protected static function deleteFromConfigNonExistentModules()
	{
		$installedModules = self::getInstalledModules();
		$configData = self::getConfigData();

		$configXML = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/local/migrato/config.xml");
		$xml = new \CDataXML();
		$xml->LoadString($configXML);
	}
}