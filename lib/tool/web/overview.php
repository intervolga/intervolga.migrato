<?php namespace Intervolga\Migrato\Tool\Web;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Orm\OptionTable;

Loc::loadMessages(__FILE__);

/**
 * Обзор того, что попадает в миграцию: модули, сущности и опции.
 * Разделы открываются как папки в разделе "Файлы и папки":
 * '' - корень, '/iblock' - сущности модуля, '/options' - модули с опциями,
 * '/options/main' - имена опций модуля.
 */
class Overview
{
	const TYPE_MODULE = 'module';
	const TYPE_ENTITY = 'entity';
	const TYPE_OPTIONS = 'options';
	const TYPE_OPTION = 'option';

	const OPTIONS_PATH = '/options';

	/**
	 * Приводит путь к виду '' или '/module' или '/options/main'
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public static function normalizePath($path)
	{
		$parts = array();
		foreach (explode('/', (string)$path) as $part)
		{
			$part = trim($part);
			if ($part !== '' && $part !== '.' && $part !== '..')
			{
				$parts[] = $part;
			}
		}
		$parts = array_slice($parts, 0, 2);

		return $parts ? '/' . implode('/', $parts) : '';
	}

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	public static function getParentPath($path)
	{
		$path = static::normalizePath($path);
		$position = strrpos($path, '/');

		return $position === false ? '' : substr($path, 0, $position);
	}

	/**
	 * Существует ли такой раздел
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public static function isPathExists($path)
	{
		$path = static::normalizePath($path);
		if ($path === '' || $path === static::OPTIONS_PATH)
		{
			return true;
		}
		$parts = explode('/', ltrim($path, '/'));
		if (count($parts) === 1)
		{
			return array_key_exists($parts[0], static::getConfigModules());
		}

		return ($parts[0] === 'options') && array_key_exists($parts[1], static::getIncludedOptions());
	}

	/**
	 * Строки для вывода в списке
	 *
	 * Каждая строка: NAME, PATH (если в нее можно провалиться), TYPE, TYPE_NAME,
	 * DESCRIPTION, IS_ERROR
	 *
	 * @param string $path
	 *
	 * @return array[]
	 */
	public static function getRows($path)
	{
		$path = static::normalizePath($path);
		if ($path === '')
		{
			return static::getRootRows();
		}
		if ($path === static::OPTIONS_PATH)
		{
			return static::getOptionModuleRows();
		}
		$parts = explode('/', ltrim($path, '/'));
		if ($parts[0] === 'options')
		{
			return static::getOptionRows($parts[1]);
		}

		return static::getEntityRows($parts[0]);
	}

	/**
	 * Модули из config.xml и раздел опций
	 *
	 * @return array[]
	 */
	protected static function getRootRows()
	{
		$rows = array();
		foreach (static::getConfigModules() as $module => $entities)
		{
			$records = 0;
			$hasError = false;
			foreach ($entities as $dataClass)
			{
				$count = static::getRecordsCount($dataClass);
				if ($count['count'] === null)
				{
					$hasError = true;
				}
				else
				{
					$records += $count['count'];
				}
			}
			$rows[] = array(
				'NAME' => $module . ' (' . count($entities) . ' / ' . ($hasError ? '?' : $records) . ')',
				'PATH' => '/' . $module,
				'TYPE' => static::TYPE_MODULE,
				'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_MODULE'),
				'DESCRIPTION' => static::getModuleNameLoc($module),
				'IS_ERROR' => $hasError,
			);
		}

		$options = static::getIncludedOptions();
		$total = 0;
		foreach ($options as $names)
		{
			$total += count($names);
		}
		$rows[] = array(
			'NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_OPTIONS')
				. ' (' . count($options) . ' / ' . $total . ')',
			'PATH' => static::OPTIONS_PATH,
			'TYPE' => static::TYPE_OPTIONS,
			'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_OPTIONS'),
			'DESCRIPTION' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_OPTIONS_NOTE'),
			'IS_ERROR' => false,
		);

		return $rows;
	}

	/**
	 * Сущности модуля
	 *
	 * @param string $module
	 *
	 * @return array[]
	 */
	protected static function getEntityRows($module)
	{
		$modules = static::getConfigModules();
		$rows = array();
		foreach ($modules[$module] ?? array() as $entity => $dataClass)
		{
			$count = static::getRecordsCount($dataClass);
			$rows[] = array(
				'NAME' => $entity . ' (' . ($count['count'] === null ? '?' : $count['count']) . ')',
				'PATH' => '',
				'TYPE' => static::TYPE_ENTITY,
				'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_ENTITY'),
				'DESCRIPTION' => $count['error'] ? $count['error'] : $dataClass->getEntityNameLoc(),
				'IS_ERROR' => (bool)$count['error'],
			);
		}

		return $rows;
	}

	/**
	 * Модули, опции которых попадают в миграцию
	 *
	 * @return array[]
	 */
	protected static function getOptionModuleRows()
	{
		$rows = array();
		foreach (static::getIncludedOptions() as $module => $names)
		{
			$rows[] = array(
				'NAME' => $module . ' (' . count($names) . ')',
				'PATH' => static::OPTIONS_PATH . '/' . $module,
				'TYPE' => static::TYPE_MODULE,
				'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_MODULE'),
				'DESCRIPTION' => static::getModuleNameLoc($module),
				'IS_ERROR' => false,
			);
		}

		return $rows;
	}

	/**
	 * Имена опций модуля
	 *
	 * @param string $module
	 *
	 * @return array[]
	 */
	protected static function getOptionRows($module)
	{
		$options = static::getIncludedOptions();
		$rows = array();
		foreach ($options[$module] ?? array() as $name)
		{
			$rows[] = array(
				'NAME' => $name,
				'PATH' => '',
				'TYPE' => static::TYPE_OPTION,
				'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_OPTION'),
				'DESCRIPTION' => '',
				'IS_ERROR' => false,
			);
		}

		return $rows;
	}

	/**
	 * Сущности из config.xml, сгруппированные по модулям
	 *
	 * @return \Intervolga\Migrato\Data\BaseData[][]
	 */
	protected static function getConfigModules()
	{
		static $modules = null;
		if ($modules === null)
		{
			$modules = array();
			foreach (Config::getInstance()->getDataClasses() as $dataClass)
			{
				$modules[$dataClass->getModule()][$dataClass->getEntityName()] = $dataClass;
			}
			foreach ($modules as $module => $entities)
			{
				ksort($entities);
				$modules[$module] = $entities;
			}
			ksort($modules);
		}

		return $modules;
	}

	/**
	 * Имена опций по модулям с учетом правил исключения
	 *
	 * @return array[] array('main' => array('option_name'))
	 */
	protected static function getIncludedOptions()
	{
		static $result = null;
		if ($result === null)
		{
			$result = array();
			$getList = OptionTable::getList(array(
				'select' => array('MODULE_ID', 'NAME'),
				'order' => array(
					'MODULE_ID' => 'ASC',
					'NAME' => 'ASC',
				),
			));
			while ($option = $getList->fetch())
			{
				if (!$option['NAME'])
				{
					continue;
				}
				if (!Config::isOptionIncluded($option['MODULE_ID'], $option['NAME']))
				{
					continue;
				}
				$result[$option['MODULE_ID']][$option['NAME']] = $option['NAME'];
			}
			foreach ($result as $module => $names)
			{
				$result[$module] = array_values($names);
			}
			ksort($result);
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return array array('count' => int|null, 'error' => string)
	 */
	protected static function getRecordsCount(BaseData $dataClass)
	{
		static $counts = array();
		$key = $dataClass->getModule() . ':' . $dataClass->getEntityName();
		if (!array_key_exists($key, $counts))
		{
			try
			{
				$filter = Config::getInstance()->getDataClassFilter($dataClass);
				$counts[$key] = array(
					'count' => count($dataClass->getList($filter)),
					'error' => '',
				);
			}
			catch (\Throwable $throwable)
			{
				$counts[$key] = array(
					'count' => null,
					'error' => $throwable->getMessage(),
				);
			}
		}

		return $counts[$key];
	}

	/**
	 * @param string $module
	 *
	 * @return string
	 */
	public static function getModuleNameLoc($module)
	{
		static $names = array();
		if (!array_key_exists($module, $names))
		{
			$names[$module] = '';
			if ($info = \CModule::createModuleObject($module))
			{
				$names[$module] = (string)$info->MODULE_NAME;
			}
		}

		return $names[$module];
	}
}
