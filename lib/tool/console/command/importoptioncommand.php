<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\OptionFileViewXml;

Loc::loadMessages(__FILE__);

class ImportOptionCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('importoptions');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_OPTIONS_DESCRIPTION'));
	}

	public function executeInner()
	{
		foreach ($this->getOptionFiles() as $file)
		{
			$module = str_replace(".xml", "", $file->getName());
			$options = OptionFileViewXml::readFromFileSystem($file->getPath());
			$this->import($module, $options);
		}
	}

	/**
	 * @return \Bitrix\Main\IO\File[]
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	protected function getOptionFiles()
	{
		$result = array();
		$directory = new Directory(INTERVOLGA_MIGRATO_DIRECTORY . "options/");
		if ($directory->isExists())
		{
			foreach ($directory->getChildren() as $dirOrFile)
			{
				if ($dirOrFile instanceof File)
				{
					$result[] = $dirOrFile;
				}
			}
		}

		return $result;
	}

	/**
	 * @param string $module
	 * @param array $options
	 */
	protected function import($module, array $options)
	{
		foreach ($options as $option)
		{
			if (Config::getInstance()->isOptionIncluded($module, $option['NAME']))
			{
				$this->importOption($module, $option);
			}
		}
	}

	/**
	 * @param string $module
	 * @param array $option
	 */
	protected function importOption($module, array $option)
	{
		Option::set($module, $option['NAME'], $option['VALUE'], $option['SITE_ID']);
		$this->logger->addDb(
			array(
				'MODULE_NAME' => $module,
				'ENTITY_NAME' => 'option',
				'ID' => RecordId::createComplexId(array(
					'SITE_ID' => $option['SITE_ID'],
					'NAME' => $option['NAME'],
				)),
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_OPTION'),
			),
			Logger::TYPE_OK
		);
	}
}