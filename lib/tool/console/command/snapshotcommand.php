<?php namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataList;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

/**
 * Слепок структуры БД: перечень записей с ID и внешними кодами.
 * Нужен, чтобы сравнивать две системы обычным файловым diff,
 * даже когда экспорт недоступен.
 */
class SnapshotCommand extends BaseCommand
{
	const FORMAT_TXT = 'txt';
	const FORMAT_CSV = 'csv';
	const FORMAT_JSON = 'json';

	const CSV_DELIMITER = ';';

	/**
	 * Колонки слепка
	 */
	const COLUMNS = array('module', 'entity', 'id', 'xml_id');

	protected $totalRecords = 0;

	protected function configure()
	{
		$this->setName('snapshot');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.SNAPSHOT_DESCRIPTION'));
		$this->addOption(
			'file',
			null,
			InputOption::VALUE_REQUIRED,
			Loc::getMessage('INTERVOLGA_MIGRATO.SNAPSHOT_OPTION_FILE')
		);
		$this->addOption(
			'format',
			null,
			InputOption::VALUE_REQUIRED,
			Loc::getMessage('INTERVOLGA_MIGRATO.SNAPSHOT_OPTION_FORMAT'),
			static::FORMAT_TXT
		);
		$this->addOption(
			'all',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.SNAPSHOT_OPTION_ALL')
		);
	}

	public function executeInner()
	{
		$format = $this->getFormat();
		$rows = $this->collectRows($this->getDataClasses());
		$path = $this->getFilePath($format);
		$this->writeFile($path, $format, $rows);
		$this->addResult($path);
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	protected function getFormat()
	{
		$format = strtolower(trim((string)$this->input->getOption('format')));
		if (!$format)
		{
			$format = static::FORMAT_TXT;
		}
		$allowed = array(static::FORMAT_TXT, static::FORMAT_CSV, static::FORMAT_JSON);
		if (!in_array($format, $allowed, true))
		{
			throw new \Exception(Loc::getMessage(
				'INTERVOLGA_MIGRATO.SNAPSHOT_UNKNOWN_FORMAT',
				array(
					'#FORMAT#' => $format,
					'#ALLOWED#' => implode(', ', $allowed),
				)
			));
		}

		return $format;
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData[]
	 */
	protected function getDataClasses()
	{
		if ($this->input->getOption('all'))
		{
			$dataClasses = DataList::getAll();
		}
		else
		{
			$dataClasses = Config::getInstance()->getDataClasses();
		}

		usort(
			$dataClasses,
			function(BaseData $first, BaseData $second)
			{
				return strcmp(
					$first->getModule() . ':' . $first->getEntityName(),
					$second->getModule() . ':' . $second->getEntityName()
				);
			}
		);

		return $dataClasses;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData[] $dataClasses
	 *
	 * @return array[] строки слепка
	 */
	protected function collectRows(array $dataClasses)
	{
		$rows = array();
		foreach ($dataClasses as $dataClass)
		{
			$this->logger->startStep($dataClass->getModule() . ':' . $dataClass->getEntityName());
			$rows = array_merge($rows, $this->collectDataRows($dataClass));
		}

		return $rows;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return array[]
	 */
	protected function collectDataRows(BaseData $dataClass)
	{
		$rows = array();
		try
		{
			$filter = $this->input->getOption('all')
				? array()
				: Config::getInstance()->getDataClassFilter($dataClass);
			foreach ($dataClass->getList($filter) as $record)
			{
				$rows[] = array(
					'module' => $dataClass->getModule(),
					'entity' => $dataClass->getEntityName(),
					'id' => $this->getIdString($record),
					'xml_id' => (string)$record->getXmlId(),
				);
			}
		}
		catch (\Throwable $throwable)
		{
			$this->logger->add(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.SNAPSHOT_ENTITY_FAIL',
					array(
						'#ENTITY#' => $dataClass->getModule() . ':' . $dataClass->getEntityName(),
						'#MESSAGE#' => $throwable->getMessage(),
					)
				),
				Logger::LEVEL_NORMAL,
				Logger::TYPE_FAIL
			);

			return array();
		}

		usort(
			$rows,
			function(array $first, array $second)
			{
				return strcmp($first['xml_id'] . $first['id'], $second['xml_id'] . $second['id']);
			}
		);
		$this->totalRecords += count($rows);
		$this->logger->add(
			Loc::getMessage(
				'INTERVOLGA_MIGRATO.SNAPSHOT_ENTITY_COUNT',
				array(
					'#ENTITY#' => $dataClass->getModule() . ':' . $dataClass->getEntityName(),
					'#COUNT#' => count($rows),
				)
			),
			Logger::LEVEL_SHORT,
			Logger::TYPE_OK
		);

		return $rows;
	}

	/**
	 * Идентификатор записи в виде строки. Составной идентификатор
	 * сортируется по ключам, чтобы слепки двух систем совпадали.
	 *
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return string
	 */
	protected function getIdString(Record $record)
	{
		$id = $record->getId();
		if (!$id)
		{
			return '';
		}
		$value = $id->getValue();
		if (is_array($value))
		{
			ksort($value);
			$parts = array();
			foreach ($value as $key => $item)
			{
				$parts[] = $key . '=' . $item;
			}

			return implode(',', $parts);
		}

		return (string)$value;
	}

	/**
	 * @param string $format
	 *
	 * @return string
	 */
	protected function getFilePath($format)
	{
		$file = trim((string)$this->input->getOption('file'));
		if (!$file)
		{
			return INTERVOLGA_MIGRATO_DIRECTORY . 'snapshot.' . $format;
		}
		$file = str_replace('\\', '/', $file);
		$isAbsolute = (substr($file, 0, 1) === '/' || preg_match('/^[a-zA-Z]:\//', $file));

		return $isAbsolute ? $file : INTERVOLGA_MIGRATO_DIRECTORY . $file;
	}

	/**
	 * @param string $path
	 * @param string $format
	 * @param array[] $rows
	 *
	 * @throws \Exception
	 */
	protected function writeFile($path, $format, array $rows)
	{
		CheckDirPath($path);
		if (file_put_contents($path, $this->render($format, $rows)) === false)
		{
			throw new \Exception(Loc::getMessage(
				'INTERVOLGA_MIGRATO.SNAPSHOT_WRITE_FAIL',
				array('#PATH#' => $path)
			));
		}
	}

	/**
	 * @param string $format
	 * @param array[] $rows
	 *
	 * @return string
	 */
	protected function render($format, array $rows)
	{
		if ($format === static::FORMAT_JSON)
		{
			return $this->renderJson($rows);
		}
		if ($format === static::FORMAT_CSV)
		{
			return $this->renderCsv($rows);
		}

		return $this->renderTxt($rows);
	}

	/**
	 * @param array[] $rows
	 *
	 * @return string
	 */
	protected function renderTxt(array $rows)
	{
		$lines = array('# ' . implode("\t", static::COLUMNS));
		foreach ($rows as $row)
		{
			$lines[] = $row['module'] . "\t" . $row['entity'] . "\t" . $row['id'] . "\t" . $row['xml_id'];
		}

		return implode("\n", $lines) . "\n";
	}

	/**
	 * @param array[] $rows
	 *
	 * @return string
	 */
	protected function renderCsv(array $rows)
	{
		$lines = array(implode(static::CSV_DELIMITER, static::COLUMNS));
		foreach ($rows as $row)
		{
			$cells = array();
			foreach (static::COLUMNS as $column)
			{
				$cells[] = $this->escapeCsv($row[$column]);
			}
			$lines[] = implode(static::CSV_DELIMITER, $cells);
		}

		return implode("\n", $lines) . "\n";
	}

	/**
	 * @param string $value
	 *
	 * @return string
	 */
	protected function escapeCsv($value)
	{
		$value = (string)$value;
		if (strpbrk($value, static::CSV_DELIMITER . "\"\n\r") !== false)
		{
			$value = '"' . str_replace('"', '""', $value) . '"';
		}

		return $value;
	}

	/**
	 * @param array[] $rows
	 *
	 * @return string
	 */
	protected function renderJson(array $rows)
	{
		$tree = array();
		foreach ($rows as $row)
		{
			$tree[$row['module']][$row['entity']][] = array(
				'id' => $row['id'],
				'xml_id' => $row['xml_id'],
			);
		}

		return json_encode(
			$tree,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		) . "\n";
	}

	/**
	 * @param string $path
	 */
	protected function addResult($path)
	{
		$this->logger->separate();
		$this->logger->add(
			Loc::getMessage(
				'INTERVOLGA_MIGRATO.SNAPSHOT_DONE',
				array(
					'#PATH#' => $path,
					'#COUNT#' => $this->totalRecords,
				)
			),
			Logger::LEVEL_NORMAL,
			Logger::TYPE_INFO
		);
	}
}
