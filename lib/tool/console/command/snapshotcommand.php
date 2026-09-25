<?php namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataList;
use Intervolga\Migrato\Tool\Orm\OptionTable;
use Intervolga\Migrato\Tool\Web\Helper;
use Intervolga\Migrato\Tool\Web\Overview;
use Intervolga\Migrato\Tool\XmlHelper;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

/**
 * Слепок структуры БД: перечень записей с ID и внешними кодами плюс опции.
 * Нужен, чтобы сравнивать две системы обычным файловым diff,
 * даже когда экспорт недоступен.
 */
class SnapshotCommand extends BaseCommand
{
	protected $totalRecords = 0;
	protected $totalOptions = 0;

	/**
	 * Файл слепка
	 *
	 * @return string
	 */
	public static function getFilePath()
	{
		return Helper::getSnapshotPath();
	}

	protected function configure()
	{
		$this->setName('snapshot');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.SNAPSHOT_DESCRIPTION'));
		$this->addOption(
			'all',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.SNAPSHOT_OPTION_ALL')
		);
	}

	public function executeInner()
	{
		$content = XmlHelper::xmlHeader();
		$content .= "<snapshot>\n";
		$content .= $this->getDataXml();
		$content .= $this->getOptionsXml();
		$content .= "</snapshot>\n";

		$this->writeFile($content);
		$this->addResult();
	}

	/**
	 * Записи всех сущностей
	 *
	 * @return string
	 */
	protected function getDataXml()
	{
		$content = "\t<data>\n";
		foreach ($this->getModules() as $module => $dataClasses)
		{
			$content .= "\t\t<module name=\"" . $this->escape($module) . "\">\n";
			foreach ($dataClasses as $entity => $dataClass)
			{
				$content .= $this->getEntityXml($entity, $dataClass);
			}
			$content .= "\t\t</module>\n";
		}
		$content .= "\t</data>\n";

		return $content;
	}

	/**
	 * @param string $entity
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return string
	 */
	protected function getEntityXml($entity, BaseData $dataClass)
	{
		$this->logger->startStep($dataClass->getModule() . ':' . $entity);
		$rows = array();
		try
		{
			$filter = $this->input->getOption('all')
				? array()
				: Config::getInstance()->getDataClassFilter($dataClass);
			foreach ($dataClass->getList($filter) as $record)
			{
				$rows[] = array(
					'xml_id' => (string)$record->getXmlId(),
					'name' => Overview::getRecordName($record),
				);
			}
		}
		catch (\Throwable $throwable)
		{
			$this->logger->add(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.SNAPSHOT_ENTITY_FAIL',
					array(
						'#ENTITY#' => $dataClass->getModule() . ':' . $entity,
						'#MESSAGE#' => $throwable->getMessage(),
					)
				),
				Logger::LEVEL_NORMAL,
				Logger::TYPE_FAIL
			);

			return "\t\t\t<entity name=\"" . $this->escape($entity) . "\" error=\"true\"/>\n";
		}

		usort(
			$rows,
			function(array $first, array $second)
			{
				return strcmp($first['xml_id'] . $first['name'], $second['xml_id'] . $second['name']);
			}
		);
		$this->totalRecords += count($rows);
		$this->logger->add(
			Loc::getMessage(
				'INTERVOLGA_MIGRATO.SNAPSHOT_ENTITY_COUNT',
				array(
					'#ENTITY#' => $dataClass->getModule() . ':' . $entity,
					'#COUNT#' => count($rows),
				)
			),
			Logger::LEVEL_SHORT,
			Logger::TYPE_OK
		);

		$content = "\t\t\t<entity name=\"" . $this->escape($entity) . "\">\n";
		foreach ($rows as $row)
		{
			$content .= "\t\t\t\t<record xml_id=\"" . $this->escape($row['xml_id']) . "\""
				. " name=\"" . $this->escape($row['name']) . "\"/>\n";
		}
		$content .= "\t\t\t</entity>\n";

		return $content;
	}

	/**
	 * Опции, которые попадают в миграцию
	 *
	 * @return string
	 */
	protected function getOptionsXml()
	{
		$options = array();
		$getList = OptionTable::getList(array(
			'select' => array('MODULE_ID', 'NAME', 'VALUE', 'SITE_ID'),
			'order' => array(
				'MODULE_ID' => 'ASC',
				'NAME' => 'ASC',
				'SITE_ID' => 'ASC',
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
			$options[$option['MODULE_ID']][] = $option;
			$this->totalOptions++;
		}
		ksort($options);

		$content = "\t<options>\n";
		foreach ($options as $module => $moduleOptions)
		{
			$content .= "\t\t<module name=\"" . $this->escape($module) . "\">\n";
			foreach ($moduleOptions as $option)
			{
				$site = (string)$option['SITE_ID'];
				$content .= "\t\t\t<option name=\"" . $this->escape($option['NAME']) . "\""
					. ($site === '' ? '' : ' site="' . $this->escape($site) . '"')
					. '>' . $this->escape((string)$option['VALUE']) . "</option>\n";
			}
			$content .= "\t\t</module>\n";
		}
		$content .= "\t</options>\n";

		$this->logger->add(
			Loc::getMessage(
				'INTERVOLGA_MIGRATO.SNAPSHOT_OPTIONS_COUNT',
				array('#COUNT#' => $this->totalOptions)
			),
			Logger::LEVEL_SHORT,
			Logger::TYPE_OK
		);

		return $content;
	}

	/**
	 * Сущности, попадающие в слепок, сгруппированные по модулям
	 *
	 * @return \Intervolga\Migrato\Data\BaseData[][]
	 */
	protected function getModules()
	{
		$dataClasses = $this->input->getOption('all')
			? DataList::getAll()
			: Config::getInstance()->getDataClasses();

		$modules = array();
		foreach ($dataClasses as $dataClass)
		{
			$modules[$dataClass->getModule()][$dataClass->getEntityName()] = $dataClass;
		}
		foreach ($modules as $module => $entities)
		{
			ksort($entities);
			$modules[$module] = $entities;
		}
		ksort($modules);

		return $modules;
	}

	/**
	 * @param string $value
	 *
	 * @return string
	 */
	protected function escape($value)
	{
		return htmlspecialcharsbx((string)$value);
	}

	/**
	 * @param string $content
	 *
	 * @throws \Exception
	 */
	protected function writeFile($content)
	{
		$path = static::getFilePath();
		CheckDirPath($path);
		if (file_put_contents($path, $content) === false)
		{
			throw new \Exception(Loc::getMessage(
				'INTERVOLGA_MIGRATO.SNAPSHOT_WRITE_FAIL',
				array('#PATH#' => $path)
			));
		}
	}

	protected function addResult()
	{
		$this->logger->separate();
		$this->logger->add(
			Loc::getMessage(
				'INTERVOLGA_MIGRATO.SNAPSHOT_DONE',
				array(
					'#PATH#' => static::getFilePath(),
					'#COUNT#' => $this->totalRecords,
					'#OPTIONS#' => $this->totalOptions,
				)
			),
			Logger::LEVEL_NORMAL,
			Logger::TYPE_INFO
		);
	}
}
