<?php namespace Intervolga\Migrato\Tool\Web;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\Orm\OptionTable;

Loc::loadMessages(__FILE__);

/**
 * Обзор того, что попадает в миграцию: модули, сущности, записи и опции.
 * Разделы открываются как папки в разделе "Файлы и папки":
 * '' - корень, '/iblock' - сущности модуля, '/iblock/type' - записи сущности,
 * '/options' - модули с опциями, '/options/main' - имена опций модуля.
 */
class Overview
{
	const TYPE_MODULE = 'module';
	const TYPE_ENTITY = 'entity';
	const TYPE_RECORD = 'record';
	const TYPE_OPTIONS = 'options';
	const TYPE_OPTION = 'option';
	const TYPE_UP = 'up';

	const OPTIONS_PATH = '/options';

	/**
	 * Состояние записи относительно выгруженного XML-файла
	 */
	const FILE_MATCH = 'match';
	const FILE_DIFFERS = 'differs';
	const FILE_ABSENT = 'absent';

	/**
	 * Приводит путь к виду '', '/iblock' или '/iblock/type'
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
	 * @param string $path
	 *
	 * @return string[] части пути
	 */
	public static function getPathParts($path)
	{
		$path = static::normalizePath($path);

		return $path === '' ? array() : explode('/', ltrim($path, '/'));
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
		$parts = static::getPathParts($path);
		if (!$parts)
		{
			return true;
		}
		if ($parts[0] === 'options')
		{
			return (count($parts) === 1) || array_key_exists($parts[1], static::getIncludedOptions());
		}
		$modules = static::getConfigModules();
		if (!array_key_exists($parts[0], $modules))
		{
			return false;
		}

		return (count($parts) === 1) || array_key_exists($parts[1], $modules[$parts[0]]);
	}

	/**
	 * Уровень пути: modules, entities, records, options, option-names
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public static function getLevel($path)
	{
		$parts = static::getPathParts($path);
		if (!$parts)
		{
			return 'modules';
		}
		if ($parts[0] === 'options')
		{
			return count($parts) === 1 ? 'option-modules' : 'option-names';
		}

		return count($parts) === 1 ? 'entities' : 'records';
	}

	/**
	 * Строки для вывода в списке
	 *
	 * @param string $path
	 *
	 * @return array[]
	 */
	public static function getRows($path)
	{
		$parts = static::getPathParts($path);
		switch (static::getLevel($path))
		{
			case 'entities':
				return static::getEntityRows($parts[0]);
			case 'records':
				return static::getRecordRows($parts[0], $parts[1]);
			case 'option-modules':
				return static::getOptionModuleRows();
			case 'option-names':
				return static::getOptionRows($parts[1]);
		}

		return static::getRootRows();
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
				'NAME' => $module,
				'PATH' => '/' . $module,
				'TYPE' => static::TYPE_MODULE,
				'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_MODULE'),
				'ENTITIES' => count($entities),
				'RECORDS' => $hasError ? '' : $records,
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
			'NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_OPTIONS'),
			'PATH' => static::OPTIONS_PATH,
			'TYPE' => static::TYPE_OPTIONS,
			'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_OPTIONS'),
			'ENTITIES' => count($options),
			'RECORDS' => $total,
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
				'NAME' => $entity,
				'PATH' => '/' . $module . '/' . $entity,
				'TYPE' => static::TYPE_ENTITY,
				'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_ENTITY'),
				'ENTITIES' => '',
				'RECORDS' => $count['count'] === null ? '' : $count['count'],
				'DESCRIPTION' => $count['error'] ? $count['error'] : $dataClass->getEntityNameLoc(),
				'IS_ERROR' => (bool)$count['error'],
			);
		}

		return $rows;
	}

	/**
	 * Записи сущности
	 *
	 * @param string $module
	 * @param string $entity
	 *
	 * @return array[]
	 */
	protected static function getRecordRows($module, $entity)
	{
		$rows = array();
		foreach (static::getRecords($module, $entity) as $record)
		{
			$rows[] = array(
				'NAME' => $record->getXmlId(),
				'PATH' => '',
				'TYPE' => static::TYPE_RECORD,
				'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_RECORD'),
				'XML_ID' => $record->getXmlId(),
				'RECORD_ID' => static::getIdString($record->getId()),
				'ATTRIBUTES' => static::getAttributesCount($record),
				'DESCRIPTION' => '',
				'IS_ERROR' => false,
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
				'NAME' => $module,
				'PATH' => static::OPTIONS_PATH . '/' . $module,
				'TYPE' => static::TYPE_MODULE,
				'TYPE_NAME' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_MODULE'),
				'ENTITIES' => '',
				'RECORDS' => count($names),
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
				'XML_ID' => $name,
				'RECORD_ID' => '',
				'ATTRIBUTES' => '',
				'DESCRIPTION' => '',
				'IS_ERROR' => false,
			);
		}

		return $rows;
	}

	/**
	 * @param string $module
	 * @param string $entity
	 *
	 * @return \Intervolga\Migrato\Data\BaseData|null
	 */
	public static function getDataClass($module, $entity)
	{
		$modules = static::getConfigModules();

		return $modules[$module][$entity] ?? null;
	}

	/**
	 * Записи сущности
	 *
	 * @param string $module
	 * @param string $entity
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public static function getRecords($module, $entity)
	{
		static $cache = array();
		$key = $module . ':' . $entity;
		if (!array_key_exists($key, $cache))
		{
			$cache[$key] = array();
			$dataClass = static::getDataClass($module, $entity);
			if ($dataClass)
			{
				try
				{
					$filter = Config::getInstance()->getDataClassFilter($dataClass);
					$cache[$key] = $dataClass->getList($filter);
				}
				catch (\Throwable $throwable)
				{
					$cache[$key] = array();
				}
			}
		}

		return $cache[$key];
	}

	/**
	 * @param string $module
	 * @param string $entity
	 * @param string $xmlId
	 *
	 * @return \Intervolga\Migrato\Data\Record|null
	 */
	public static function getRecord($module, $entity, $xmlId)
	{
		foreach (static::getRecords($module, $entity) as $record)
		{
			if ($record->getXmlId() === $xmlId)
			{
				return $record;
			}
		}

		return null;
	}

	/**
	 * Количество известных о записи атрибутов: поля, зависимости и ссылки
	 *
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return int
	 */
	public static function getAttributesCount(Record $record)
	{
		return count($record->getFields())
			+ count($record->getDependencies())
			+ count($record->getReferences());
	}

	/**
	 * Поля записи в виде name => печатное значение
	 *
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array[] array(array('NAME' => , 'VALUE' => , 'DESCRIPTION' => , 'IS_MULTIPLE' => ))
	 */
	public static function getFieldsInfo(Record $record)
	{
		$result = array();
		foreach ($record->getFields() as $name => $value)
		{
			$result[] = array(
				'NAME' => $name,
				'VALUES' => static::getValues($value),
				'DESCRIPTION' => $value->isDescriptionSet() ? $value->getDescription() : '',
				'IS_MULTIPLE' => $value->isMultiple(),
			);
		}

		return $result;
	}

	/**
	 * Связи записи с указанием, куда они ведут
	 *
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array[]
	 */
	public static function getLinksInfo(Record $record)
	{
		$result = array();
		foreach ($record->getDependencies() as $name => $link)
		{
			$result[] = static::getLinkInfo($name, $link, 'dependency');
		}
		foreach ($record->getReferences() as $name => $link)
		{
			$result[] = static::getLinkInfo($name, $link, 'reference');
		}

		return $result;
	}

	/**
	 * @param string $name
	 * @param \Intervolga\Migrato\Data\Link $link
	 * @param string $kind
	 *
	 * @return array
	 */
	protected static function getLinkInfo($name, Link $link, $kind)
	{
		$target = $link->getTargetData();

		return array(
			'NAME' => $name,
			'KIND' => $kind,
			'KIND_NAME' => $kind === 'dependency'
				? Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_DEPENDENCY')
				: Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_REFERENCE'),
			'MODULE' => $target instanceof BaseData ? $target->getModule() : '',
			'ENTITY' => $target instanceof BaseData ? $target->getEntityName() : '',
			'VALUES' => static::getValues($link),
			'DESCRIPTION' => $link->isDescriptionSet() ? $link->getDescription() : '',
		);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Value $value
	 *
	 * @return string[]
	 */
	protected static function getValues(Value $value)
	{
		if ($value->isMultiple())
		{
			$result = array();
			foreach ($value->getValues() as $item)
			{
				$result[] = (string)$item;
			}

			return $result;
		}

		return array((string)$value->getValue());
	}

	/**
	 * Информация о выгруженном XML-файле записи
	 *
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param string $xmlId
	 *
	 * @return array array('EXISTS' => bool, 'PATH' => абсолютный путь, 'RELATIVE_PATH' => путь от корня сайта)
	 */
	public static function getFileInfo(BaseData $dataClass, $xmlId)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY
			. $dataClass->getModule()
			. $dataClass->getFilesSubdir()
			. $dataClass->getEntityName()
			. '/data-' . $xmlId . '.xml';
		$documentRoot = str_replace('\\', '/', \Bitrix\Main\Application::getDocumentRoot());
		$normalized = str_replace('\\', '/', $path);

		return array(
			'EXISTS' => file_exists($path),
			'PATH' => $path,
			'RELATIVE_PATH' => str_replace($documentRoot, '', $normalized),
		);
	}

	/**
	 * Сравнивает запись в БД с выгруженным XML-файлом
	 *
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return array array('STATUS' => match|differs|absent, 'DIFFERENCES' => string[])
	 */
	public static function compareWithFile(Record $record, BaseData $dataClass)
	{
		$file = static::getFileInfo($dataClass, $record->getXmlId());
		if (!$file['EXISTS'])
		{
			return array(
				'STATUS' => static::FILE_ABSENT,
				'DIFFERENCES' => array(),
			);
		}

		try
		{
			$fileRecord = DataFileViewXml::parseFile(new \Bitrix\Main\IO\File($file['PATH']));
		}
		catch (\Throwable $throwable)
		{
			return array(
				'STATUS' => static::FILE_DIFFERS,
				'DIFFERENCES' => array($throwable->getMessage()),
			);
		}

		if ($fileRecord->getDeleteMark())
		{
			return array(
				'STATUS' => static::FILE_DIFFERS,
				'DIFFERENCES' => array(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_DELETED')),
			);
		}

		$differences = array_merge(
			static::compareValues(
				Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FIELD'),
				$record->getFields(),
				$fileRecord->getFields()
			),
			static::compareValues(
				Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_DEPENDENCY'),
				$record->getDependencies(),
				$fileRecord->getDependencies()
			),
			static::compareValues(
				Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_REFERENCE'),
				$record->getReferences(),
				$fileRecord->getReferences()
			)
		);

		return array(
			'STATUS' => $differences ? static::FILE_DIFFERS : static::FILE_MATCH,
			'DIFFERENCES' => $differences,
		);
	}

	/**
	 * @param string $kind
	 * @param \Intervolga\Migrato\Data\Value[] $database
	 * @param \Intervolga\Migrato\Data\Value[] $file
	 *
	 * @return string[]
	 */
	protected static function compareValues($kind, array $database, array $file)
	{
		$differences = array();
		$names = array_unique(array_merge(array_keys($database), array_keys($file)));
		sort($names);
		foreach ($names as $name)
		{
			$inDatabase = isset($database[$name]) ? static::getValues($database[$name]) : null;
			$inFile = isset($file[$name]) ? static::getValues($file[$name]) : null;
			if ($inDatabase === null)
			{
				$differences[] = Loc::getMessage(
					'INTERVOLGA_MIGRATO.WEB_OVERVIEW_DIFF_NO_DB',
					array('#KIND#' => $kind, '#NAME#' => $name)
				);
			}
			elseif ($inFile === null)
			{
				$differences[] = Loc::getMessage(
					'INTERVOLGA_MIGRATO.WEB_OVERVIEW_DIFF_NO_FILE',
					array('#KIND#' => $kind, '#NAME#' => $name)
				);
			}
			elseif ($inDatabase !== $inFile)
			{
				$differences[] = Loc::getMessage(
					'INTERVOLGA_MIGRATO.WEB_OVERVIEW_DIFF_VALUE',
					array(
						'#KIND#' => $kind,
						'#NAME#' => $name,
						'#DB#' => implode(', ', $inDatabase),
						'#FILE#' => implode(', ', $inFile),
					)
				);
			}
		}

		return $differences;
	}

	/**
	 * Идентификатор записи в виде строки
	 *
	 * @param \Intervolga\Migrato\Data\RecordId|null $id
	 *
	 * @return string
	 */
	public static function getIdString($id)
	{
		if (!($id instanceof RecordId))
		{
			return '';
		}
		$value = $id->getValue();
		if (is_array($value))
		{
			$parts = array();
			foreach ($value as $key => $item)
			{
				$parts[] = $key . '=' . $item;
			}

			return implode('; ', $parts);
		}

		return (string)$value;
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
