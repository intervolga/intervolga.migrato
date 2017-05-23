<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\OptionFileViewXml;
use Intervolga\Migrato\Tool\Orm\OptionTable;

Loc::loadMessages(__FILE__);

class ExportOptionCommand extends BaseCommand
{
	const NO_SITE = '00';
	protected $options = array();
	
	protected function configure()
	{
		$this->setName('exportoptions');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.EXPORT_OPTIONS_DESCRIPTION'));
	}

	public function executeInner()
	{
		foreach ($this->getDbOptions() as $module => $moduleOptions)
		{
			if ($moduleOptions)
			{
				OptionFileViewXml::write($moduleOptions, INTERVOLGA_MIGRATO_DIRECTORY . 'options/' , $module);
				foreach ($moduleOptions as $moduleOption)
				{
					$this->logger->addDb(
						array(
							'MODULE_NAME' => $module,
							'ENTITY_NAME' => 'option',
							'ID' => RecordId::createComplexId(array(
								'SITE_ID' => $moduleOption['SITE_ID'],
								'NAME' => $moduleOption['NAME'],
							)),
							'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.EXPORT_OPTION'),
						),
						Logger::TYPE_OK
					);
				}
			}
		}
	}

	/**
	 * @param bool $force
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function loadDbOptions($force = false)
	{
		if (!$this->options || $force)
		{
			$this->options = array();
			$getList = OptionTable::getList(array(
				'select' => array(
					'MODULE_ID',
					'NAME',
					'VALUE',
					'SITE_ID',
				),
			));
			while ($option = $getList->fetch())
			{
				if (!$option['SITE_ID'])
				{
					$option['SITE_ID'] = static::NO_SITE;
				}
				$this->options[$option['MODULE_ID']][$option['NAME']][$option['SITE_ID']] = $option;
			}
		}
	}

	/**
	 * @return array
	 */
	protected function getDbOptions()
	{
		$this->loadDbOptions();
		$options = array();
		foreach ($this->options as $moduleId => $moduleOptions)
		{
			foreach ($moduleOptions as $name => $sameOptions)
			{
				if (Config::getInstance()->isOptionIncluded($name))
				{
					foreach ($sameOptions as $siteId => $option)
					{
						if ($option['NAME'])
						{
							$siteId = $option['SITE_ID'];
							if ($option['SITE_ID'] == static::NO_SITE)
							{
								unset($option['SITE_ID']);
							}
							$options[$moduleId][$option['NAME'] . '.' . $siteId] = $option;
						}
					}
				}
				ksort($options[$moduleId]);
			}
		}

		return $options;
	}
}