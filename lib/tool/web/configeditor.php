<?php namespace Intervolga\Migrato\Tool\Web;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\DataList;

Loc::loadMessages(__FILE__);

/**
 * Чтение и запись config.xml в виде структуры, пригодной для формы настроек модуля
 */
class ConfigEditor
{
	const INDENT = '    ';

	/**
	 * Разбирает config.xml
	 *
	 * Возвращает массив вида:
	 * array(
	 *     'modules' => array(array('name' => 'iblock', 'entities' => array(array('name' => 'type', 'filters' => array())))),
	 *     'options' => array(array('module' => 'main', 'name' => '^dump_.*')),
	 *     'orm' => array('modules' => array(), 'entities' => array()),
	 * )
	 *
	 * @return array
	 */
	public static function read()
	{
		$config = static::getEmptyConfig();
		if (!Helper::isConfigExists())
		{
			return $config;
		}

		$previous = libxml_use_internal_errors(true);
		$xml = simplexml_load_string((string)file_get_contents(Helper::getConfigPath()));
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$xml)
		{
			return $config;
		}

		foreach ($xml->module as $moduleNode)
		{
			$moduleName = trim((string)$moduleNode->name);
			if (!$moduleName)
			{
				continue;
			}
			$entities = array();
			foreach ($moduleNode->entity as $entityNode)
			{
				$entityName = trim((string)$entityNode->name);
				if (!$entityName)
				{
					continue;
				}
				$filters = array();
				foreach ($entityNode->filter as $filterNode)
				{
					$filter = trim((string)$filterNode);
					if ($filter !== '')
					{
						$filters[] = $filter;
					}
				}
				$entities[] = array(
					'name' => $entityName,
					'filters' => $filters,
				);
			}
			$config['modules'][] = array(
				'name' => $moduleName,
				'entities' => $entities,
			);
		}

		if ($xml->options)
		{
			foreach ($xml->options->exclude as $excludeNode)
			{
				$name = trim((string)$excludeNode);
				if ($name === '')
				{
					continue;
				}
				$config['options'][] = array(
					'module' => (string)($excludeNode->attributes()->module ?? ''),
					'name' => $name,
				);
			}
		}

		if ($xml->orm)
		{
			foreach ($xml->orm->module as $ormModule)
			{
				$value = trim((string)$ormModule);
				if ($value !== '')
				{
					$config['orm']['modules'][] = $value;
				}
			}
			foreach ($xml->orm->entity as $ormEntity)
			{
				$value = trim((string)$ormEntity);
				if ($value !== '')
				{
					$config['orm']['entities'][] = $value;
				}
			}
		}

		return $config;
	}

	/**
	 * @return array
	 */
	public static function getEmptyConfig()
	{
		return array(
			'modules' => array(),
			'options' => array(),
			'orm' => array(
				'modules' => array(),
				'entities' => array(),
			),
		);
	}

	/**
	 * Сущности, доступные для миграции, сгруппированные по модулям
	 *
	 * @return \Intervolga\Migrato\Data\BaseData[][] array('iblock' => array('type' => BaseData))
	 */
	public static function getAvailableEntities()
	{
		$result = array();
		foreach (DataList::getAll() as $dataClass)
		{
			if ($dataClass instanceof BaseData)
			{
				$result[$dataClass->getModule()][$dataClass->getEntityName()] = $dataClass;
			}
		}
		foreach ($result as $module => $entities)
		{
			ksort($entities);
			$result[$module] = $entities;
		}
		ksort($result);

		return $result;
	}

	/**
	 * Строки формы: все доступные сущности плюс те, что есть в конфиге,
	 * но недоступны сейчас (например, модуль не установлен)
	 *
	 * @param array $config результат read()
	 *
	 * @return array array('iblock' => array('type' => array('data' => BaseData|null, 'checked' => bool, 'filters' => string)))
	 */
	public static function getFormRows(array $config)
	{
		$rows = array();
		foreach (static::getAvailableEntities() as $module => $entities)
		{
			foreach ($entities as $entity => $dataClass)
			{
				$rows[$module][$entity] = array(
					'data' => $dataClass,
					'checked' => false,
					'filters' => '',
				);
			}
		}
		foreach ($config['modules'] as $module)
		{
			foreach ($module['entities'] as $entity)
			{
				if (!isset($rows[$module['name']][$entity['name']]))
				{
					$rows[$module['name']][$entity['name']] = array(
						'data' => null,
						'checked' => true,
						'filters' => '',
					);
				}
				$rows[$module['name']][$entity['name']]['checked'] = true;
				$rows[$module['name']][$entity['name']]['filters'] = implode("\n", $entity['filters']);
			}
		}
		ksort($rows);

		return $rows;
	}

	/**
	 * Собирает конфиг из данных формы, сохраняя порядок модулей и сущностей из текущего файла
	 *
	 * @param array $request
	 * @param array $current результат read(), нужен для сохранения порядка
	 *
	 * @return array
	 */
	public static function fromRequest(array $request, array $current)
	{
		$checked = array();
		$entityRequest = isset($request['ENTITY']) && is_array($request['ENTITY']) ? $request['ENTITY'] : array();
		$filterRequest = isset($request['FILTER']) && is_array($request['FILTER']) ? $request['FILTER'] : array();
		foreach ($entityRequest as $module => $entities)
		{
			if (!is_array($entities))
			{
				continue;
			}
			foreach ($entities as $entity => $value)
			{
				if ($value === 'Y')
				{
					$checked[$module][$entity] = static::splitLines(
						(string)($filterRequest[$module][$entity] ?? '')
					);
				}
			}
		}

		$config = static::getEmptyConfig();
		foreach ($current['modules'] as $currentModule)
		{
			$moduleName = $currentModule['name'];
			if (!isset($checked[$moduleName]))
			{
				continue;
			}
			$entities = array();
			foreach ($currentModule['entities'] as $currentEntity)
			{
				$entityName = $currentEntity['name'];
				if (isset($checked[$moduleName][$entityName]))
				{
					$entities[] = array(
						'name' => $entityName,
						'filters' => $checked[$moduleName][$entityName],
					);
					unset($checked[$moduleName][$entityName]);
				}
			}
			foreach ($checked[$moduleName] as $entityName => $filters)
			{
				$entities[] = array(
					'name' => $entityName,
					'filters' => $filters,
				);
			}
			unset($checked[$moduleName]);
			if ($entities)
			{
				$config['modules'][] = array(
					'name' => $moduleName,
					'entities' => $entities,
				);
			}
		}
		foreach ($checked as $moduleName => $entitiesFilters)
		{
			$entities = array();
			foreach ($entitiesFilters as $entityName => $filters)
			{
				$entities[] = array(
					'name' => $entityName,
					'filters' => $filters,
				);
			}
			if ($entities)
			{
				$config['modules'][] = array(
					'name' => $moduleName,
					'entities' => $entities,
				);
			}
		}

		$optionModules = isset($request['OPTION_MODULE']) && is_array($request['OPTION_MODULE'])
			? $request['OPTION_MODULE']
			: array();
		$optionNames = isset($request['OPTION_NAME']) && is_array($request['OPTION_NAME'])
			? $request['OPTION_NAME']
			: array();
		foreach ($optionNames as $key => $name)
		{
			$name = trim((string)$name);
			if ($name === '')
			{
				continue;
			}
			$config['options'][] = array(
				'module' => trim((string)($optionModules[$key] ?? '')),
				'name' => $name,
			);
		}

		$config['orm']['modules'] = static::splitLines((string)($request['ORM_MODULES'] ?? ''));
		$config['orm']['entities'] = static::splitLines((string)($request['ORM_ENTITIES'] ?? ''));

		return $config;
	}

	/**
	 * @param string $text
	 *
	 * @return string[]
	 */
	protected static function splitLines($text)
	{
		$result = array();
		foreach (preg_split('/[\r\n]+/', $text) as $line)
		{
			$line = trim($line);
			if ($line !== '')
			{
				$result[] = $line;
			}
		}

		return $result;
	}

	/**
	 * Формирует содержимое config.xml
	 *
	 * @param array $config
	 *
	 * @return string
	 */
	public static function buildXml(array $config)
	{
		$indent = static::INDENT;
		$lines = array();
		$lines[] = '<?xml version="1.0" encoding="utf-8"?>';
		$lines[] = '<config>';

		if ($config['orm']['modules'] || $config['orm']['entities'])
		{
			$lines[] = $indent . '<orm>';
			foreach ($config['orm']['modules'] as $module)
			{
				$lines[] = $indent . $indent . '<module>' . static::escape($module) . '</module>';
			}
			foreach ($config['orm']['entities'] as $entity)
			{
				$lines[] = $indent . $indent . '<entity>' . static::escape($entity) . '</entity>';
			}
			$lines[] = $indent . '</orm>';
		}

		if ($config['options'])
		{
			$lines[] = $indent . '<options>';
			foreach ($config['options'] as $option)
			{
				$attribute = '';
				if ($option['module'] !== '')
				{
					$attribute = ' module="' . static::escape($option['module']) . '"';
				}
				$lines[] = $indent . $indent . '<exclude' . $attribute . '>'
					. static::escape($option['name']) . '</exclude>';
			}
			$lines[] = $indent . '</options>';
		}

		foreach ($config['modules'] as $module)
		{
			$lines[] = $indent . '<module>';
			$lines[] = $indent . $indent . '<name>' . static::escape($module['name']) . '</name>';
			foreach ($module['entities'] as $entity)
			{
				$lines[] = $indent . $indent . '<entity>';
				$lines[] = $indent . $indent . $indent . '<name>' . static::escape($entity['name']) . '</name>';
				foreach ($entity['filters'] as $filter)
				{
					$lines[] = $indent . $indent . $indent . '<filter>' . static::escape($filter) . '</filter>';
				}
				$lines[] = $indent . $indent . '</entity>';
			}
			$lines[] = $indent . '</module>';
		}

		$lines[] = '</config>';

		return implode("\n", $lines) . "\n";
	}

	/**
	 * @param string $value
	 *
	 * @return string
	 */
	protected static function escape($value)
	{
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Записывает конфиг в файл
	 *
	 * @param array $config
	 *
	 * @return string[] список ошибок
	 */
	public static function save(array $config)
	{
		$errors = array();
		$xml = static::buildXml($config);
		$xmlError = Helper::getXmlError($xml);
		if ($xmlError)
		{
			$errors[] = Loc::getMessage(
				'INTERVOLGA_MIGRATO.WEB_CONFIG_XML_ERROR',
				array('#ERROR#' => $xmlError)
			);

			return $errors;
		}

		$path = Helper::getConfigPath();
		CheckDirPath($path);
		if (file_put_contents($path, $xml) === false)
		{
			$errors[] = Loc::getMessage(
				'INTERVOLGA_MIGRATO.WEB_CONFIG_SAVE_ERROR',
				array('#PATH#' => Helper::getConfigRelativePath())
			);
		}

		return $errors;
	}
}
