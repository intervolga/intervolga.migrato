<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\OptionFileViewXml;
use Intervolga\Migrato\Tool\Orm\OptionTable;

Loc::loadMessages(__FILE__);

class ExportOption extends BaseProcess
{
	const NO_SITE = '00';
	protected static $options = array();

	public static function run()
	{
		parent::run();
		foreach (static::getDbOptions() as $module => $moduleOptions)
		{
			OptionFileViewXml::write($moduleOptions, INTERVOLGA_MIGRATO_DIRECTORY . 'options/' , $module);
			static::report(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.STATISTICS_RECORD',
					array(
						'#MODULE#' => static::getModuleMessage($module),
						'#ENTITY#' => Loc::getMessage('INTERVOLGA_MIGRATO.ENTITY_NAME_OPTIONS'),
						'#OPERATION#' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_EXPORT_OPTIONS'),
						'#COUNT#' => count($moduleOptions),
					)
				),
				'ok',
				count($moduleOptions)
			);
		}
		parent::finalReport();
	}

	/**
	 * @param bool $force
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected static function loadDbOptions($force = false)
	{
		if (!static::$options || $force)
		{
			static::$options = array();
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
				static::$options[$option['MODULE_ID']][$option['NAME']][$option['SITE_ID']] = $option;
			}
		}
	}

	/**
	 * @return array
	 */
	protected static function getDbOptions()
	{
		static::loadDbOptions();
		$options = array();
		foreach (static::$options as $moduleId => $moduleOptions)
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