<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\IO\File;

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
		//self::deleteFromConfigNonExistentModules();
		//self::createFile();
		self::getAvailableModules();
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

//	protected static function deleteFromConfigNonExistentModules()
//	{
//		$installedModules = self::getInstalledModules();
//		$configData = self::getConfigData();
//
//		$configXML = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/local/migrato/config.xml");
//		$xml = new \CDataXML();
//		$xml->LoadString($configXML);
//
//		$file = $_SERVER["DOCUMENT_ROOT"] . "/local/migrato/config_update.xml";
//		if (!file_exists($file)) {
//			$fp = fopen($file, "w");
//			fwrite($fp, $configData);
//			fclose($fp);
//		}
//	}

	protected static function getAvailableModules()
	{
		$installedModules = self::getInstalledModules();
		$configData = self::getConfigData();
		$availableModules = array_intersect_key(array_flip($installedModules), $configData);

		return array_keys($availableModules);
	}



	protected static function createFile()
	{
		// TO DO
		// ($path, $data)


		$path = $_SERVER["DOCUMENT_ROOT"] . "/local/migrato/config_update.xml";
		File::putFileContents($path, 'daad');
	}
}