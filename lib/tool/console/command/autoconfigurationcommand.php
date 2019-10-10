<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\IO\File;
use Intervolga\Migrato\Data\Module;
use Intervolga\Migrato\Tool\Console\Logger;


Loc::loadMessages(__FILE__);

class AutoconfigurationCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('autoconfig');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.AUTOCONFIG_DESCRIPTION'));
	}

	public function executeInner()
	{
		self::createFile();

		$this->logger->add(
			Loc::getMessage('INTERVOLGA_MIGRATO.ENTITY_DELETED',
				array(
					"#COUNT#" => 7
				)
			),
			Logger::LEVEL_NORMAL,
			Logger::TYPE_INFO
		);

//		$this->logger->add(
//			"LEVEL_NORMAL",
//			Logger::LEVEL_NORMAL,
//			Logger::TYPE_INFO);
//
//		$this->logger->add(
//			"LEVEL_SHORT",
//			Logger::LEVEL_SHORT,
//			Logger::TYPE_INFO);
//
//		$this->logger->add(
//			"LEVEL_DETAIL",
//			Logger::LEVEL_DETAIL,
//			Logger::TYPE_INFO);

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

		return array_flip($installedModules);
	}

	protected static function getAvailableModules()
	{
		$installedModules = self::getInstalledModules();
		$configData = self::getConfigData();
		$availableModules = array_intersect_key($installedModules, $configData);

		return array_keys($availableModules);
	}

	protected static function getDeletedModules()
	{
		$deleteData = array();

		$installedModules = array_keys(self::getInstalledModules());
		$configData = self::getConfigData();

		// 1. Узнать какие сущности будут удалены из config.xml  +++
		// 2. Вложить удаляемые сущности в модули +++
		// 3. В каждом ключе модуля иметь: ключ с кол-вом, название модуля, код модуля
		// 4. Сущности вложены в модули, ключом сущности выступает ее код, значение - название на русском

		foreach ($configData as $module => $entities)
		{
			if (!in_array($module, $installedModules))
			{
				$deleteData[$module] = $entities;
			}
		}

	}

	protected static function createFile()
	{
		$configXML = file_get_contents(self::getPathConfigXML());
		$xml = new \CDataXML();
		$xml->LoadString($configXML);
		$arDataXML = $xml->GetArray();
		$workerFileName = 'config_' . md5(rand(5, 15)) . '.xml';

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

		self::getDeletedModules();

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
					$class = '\Intervolga\Migrato\Data\Module\\' . $moduleName . '\\' . $entity['#']['name'][0]['#'];
					$minVersion = $class::getMinVersion();

					if (version_compare(SM_VERSION, $minVersion, '>='))
					{
						$export->writeBeginTag('entity');
						$export->writeItem(array('name' => $entity['#']['name'][0]['#']));
						$export->writeEndTag('entity');
					}
				}

				$export->writeEndTag('module');
			}
		}

		$export->writeEndTag('config');
		$export->closeFile();

		// delete config.xml
		unlink(self::getPathConfigXML());

		// rename $workerFileName to config.xml
		rename($_SERVER["DOCUMENT_ROOT"] . "/local/migrato/$workerFileName", self::getPathConfigXML());
	}
}