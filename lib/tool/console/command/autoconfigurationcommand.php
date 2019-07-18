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
		self::createFile();
	}

	protected static function getPathConfigXML()
	{
		return $_SERVER["DOCUMENT_ROOT"] . "/local/migrato/config.xml";
	}

	protected static function getConfigData()
	{
		$configData = array();

		$configXML = file_get_contents(self::getPathConfigXML());
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

	protected static function getAvailableModules()
	{
		$installedModules = self::getInstalledModules();
		$configData = self::getConfigData();
		$availableModules = array_intersect_key(array_flip($installedModules), $configData);

		return array_keys($availableModules);
	}

	protected static function getVersionMainModule()
	{
		if (defined("SM_VERSION"))
		{
			return SM_VERSION;
		}
		else
		{
			return false;
		}
	}

	protected static function createFile()
	{
		$configXML = file_get_contents(self::getPathConfigXML());
		$xml = new \CDataXML();
		$xml->LoadString($configXML);
		$arDataXML = $xml->GetArray();
		//$workerFileName = 'config_' . md5(rand(5, 15)) . '.xml';
		$workerFileName = 'config_update.xml';

		$export = new \Bitrix\Main\XmlWriter(array(
			'file' => "/local/migrato/$workerFileName",
			'create_file' => true,
			'charset' => 'utf-8',
			'lowercase' => false
		));

		$export->openFile();
		$export->writeBeginTag('config');

		$export->writeBeginTag('options');
		// options
		foreach ($arDataXML['config']['#']['options'][0]['#']['exclude'] as $option)
		{
			if ($option['@']['module'])
			{
				$data = "    	<exclude module=" . '"' . $option['@']['module'] . '"' . ">" . $option['#'] . "</exclude>\n";
			}
			else
			{
				$data = "    	<exclude>" . $option['#'] . "</exclude>\n";
			}

			File::putFileContents($_SERVER["DOCUMENT_ROOT"] . "/local/migrato/$workerFileName", $data, true);
		}
		$export->writeEndTag('options');

		$availableModules = self::getAvailableModules();
		// modules
		foreach ($arDataXML['config']['#']['module'] as $module)
		{
			$moduleName = $module['#']['name'][0]['#'];

			if (in_array($moduleName, $availableModules))
			{
				$export->writeBeginTag('module');
				$export->writeItem(array('name' => $moduleName));

				// entities
				foreach ($module['#']['entity'] as $entity)
				{
//					if (version_compare(self::getVersionMainModule(), \Intervolga\Migrato\Data\Module\Main\Culture::getMinVersion(), '>='))
//					{
//
//					}

					$export->writeBeginTag('entity');
					$export->writeItem(array('name' => $entity['#']['name'][0]['#']));
					$export->writeEndTag('entity');
				}

				$export->writeEndTag('module');
			}
		}


		$export->writeEndTag('config');
		$export->closeFile();

		// delete config.xml
		//unlink(self::getPathConfigXML());

		// rename $workerFileName to config.xml
		//rename($_SERVER["DOCUMENT_ROOT"] . "/local/migrato/$workerFileName", self::getPathConfigXML());
	}
}