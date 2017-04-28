<? namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\OptionFileViewXml;
use Intervolga\Migrato\Tool\Orm\OptionTable;

class ExportOption extends BaseProcess
{
	const NO_SITE = '00';
	protected static $options = array();

	public static function run()
	{
		parent::run();
		$optionsRules = Config::getInstance()->getOptions();
		$options = static::getDbOptions($optionsRules);
		foreach ($options as $module => $moduleOptions)
		{
			OptionFileViewXml::write($moduleOptions, INTERVOLGA_MIGRATO_DIRECTORY . "options/" , $module);
			static::report("Module $module export " . count($moduleOptions) . " option(s)");
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
	 * @param array $rules
	 *
	 * @return array
	 */
	protected static function getDbOptions($rules = array())
	{
		static::loadDbOptions();
		$options = array();
		foreach (static::$options as $moduleId => $moduleOptions)
		{
			foreach ($moduleOptions as $name => $sameOptions)
			{
				$isExcluded = false;
				foreach ($rules as $rule)
				{
					$pattern = static::ruleToPattern($rule);
					$matches = array();
					if (preg_match_all($pattern, $name, $matches))
					{
						$isExcluded = true;
					}
				}
				if (!$isExcluded)
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

	/**
	 * @param string $rule
	 *
	 * @return string
	 */
	protected static function ruleToPattern($rule)
	{
		$pattern = $rule;
		if ($pattern[0] != '/')
		{
			$pattern = '/' . $rule . '/';
		}

		return $pattern;
	}
}