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
		$this->createReport();
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

		return array_flip($installedModules);
	}

	protected static function getAvailableModules()
	{
		$installedModules = self::getInstalledModules();
		$configData = self::getConfigData();
		$availableModules = array_intersect_key($installedModules, $configData);

		return array_keys($availableModules);
	}

	protected static function getDeletedData()
	{
		$deleteData = array();
		$deleteData['ALL_COUNT_ENTITIES'] = 0;

		$installedModules = array_keys(self::getInstalledModules());
		$configData = self::getConfigData();

		foreach ($configData as $module => $entities)
		{
			if (!in_array($module, $installedModules))
			{
				$deleteData['MODULES'][$module] = $entities;
				$deleteData['ALL_COUNT_ENTITIES'] += count($entities);
			}
		}

		return $deleteData;
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

	protected function createReport()
	{
		$data = self::getDeletedData();

		// with -vv
		foreach ($data['MODULES'] as $module => $entities)
		{
			foreach ($entities as $entity)
			{
				$this->logger->add(
					Loc::getMessage('INTERVOLGA_MIGRATO.ENTITY_DELETED_VV',
						array(
							'#MODULE#' => $this->logger->getModuleNameLoc($module),
							'#ENTITY#' => $this->logger->getEntityNameLoc($module, $entity),
							'#MODULE_CODE#' => $module
						)
					),
					Logger::LEVEL_DETAIL,
					Logger::TYPE_INFO);
			}
		}

		// with -v
		foreach ($data['MODULES'] as $module => $entities)
		{
			$this->logger->add(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.ENTITY_DELETED_V',
					array(
						'#MODULE#' => $this->logger->getModuleNameLoc($module),
						'#COUNT#' => count($entities),
					)
				),
				Logger::LEVEL_SHORT,
				Logger::TYPE_INFO
			);
		}

		// without -v or -vv
		$this->logger->add(
			Loc::getMessage(
				'INTERVOLGA_MIGRATO.ENTITY_DELETED',
				array(
					'#COUNT#' => $data['ALL_COUNT_ENTITIES'],
				)
			),
			Logger::LEVEL_NORMAL,
			Logger::TYPE_INFO
		);
	}
}