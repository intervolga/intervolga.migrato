<?php

namespace Intervolga\Migrato\Utils;

use Bitrix\Main\Application;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Fields\Relations\ManyToMany;
use Bitrix\Main\SystemException;
use Bitrix\Perfmon\Sql\Column;
use Bitrix\Perfmon\Sql\Table;
use Bitrix\Perfmon\Sql\Tokenizer;
use Intervolga\Migrato\Tool\Console\Logger;

/**
 * Класс для миграции таблиц ORM-сущностей
 */
class OrmTableMigration
{
	/**
	 * @var array
	 */
	protected $ormClasses = [];
	/** @var bool Режим без удаления данных */
	protected $isSafeDeleteMode = false;

	protected Logger $logger;

	public function __construct(array $classes, ?Logger $logger = null)
	{
		if (!Loader::includeModule('perfmon'))
		{
			throw new SystemException('Can not load module perfmon');
		}
		foreach ($classes as $class)
		{
			$this->addClass($class);
		}
		$this->logger = $logger;
	}

	/**
	 * @param bool $val
	 *
	 * @return $this
	 */
	public function setSafeDeleteMode(bool $val): self
	{
		$this->isSafeDeleteMode = $val;
		return $this;
	}

	/**
	 * @param $class
	 */
	public function addClass($class): void
	{
		if (
			!in_array($class, $this->ormClasses)
			&& class_exists($class)
			&& (
				is_subclass_of($class, DataManager::class)
				|| is_subclass_of($class, \Bitrix\Main\ORM\Data\DataManager::class)
			)
		)
		{
			$this->ormClasses[] = $class;
		}
	}

	/**
	 * Ищет в указанной директории ORM классы с атрибутом UseMigrations
	 *
	 * @param string $dir
	 *
	 * @return $this
	 * @throws \ReflectionException
	 */
	public function loadFromDir(string $dir): static
	{
		if (!str_starts_with($dir, $_SERVER['DOCUMENT_ROOT']))
		{
			$dir = $_SERVER['DOCUMENT_ROOT'] . $dir;
		}
		foreach (
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
				\RecursiveIteratorIterator::SELF_FIRST) as $item
		)
		{
			/** @var $iterator \RecursiveDirectoryIterator */
			/** @var $item \SplFileInfo */
			if (
				$item->isFile()
				&& $item->isReadable()
				&& mb_substr($item->getFilename(), -4) == '.php'
				&& !str_contains($item->getPathname(), 'vendor')
			)
			{
				$classes = $this->getClassNamesFromFilePath($item->getPathname());

				foreach ($classes as $class)
				{
					if (!class_exists($class))
					{
						continue;
					}

					$rClass = new \ReflectionClass($class);
					$migrationAttr = $rClass->getAttributes(\Intervolga\Migrato\Utils\UseMigrations::class)[0]?->newInstance();
					if (
						!is_subclass_of($class, \Bitrix\Main\ORM\Data\DataManager::class)
						|| !$migrationAttr
						|| $rClass->isAbstract()
						|| in_array($class, $this->ormClasses)
					)
					{
						continue;
					}
					$this->ormClasses[] = $class;
				}
			}
		}

		return $this;
	}

	public function getClassNamesFromFilePath($filePath) {
		$classNames = [];
		$tokens = token_get_all(file_get_contents($filePath));
		$count = count($tokens);
		$namespace = '';
		$inNamespace = false;
		$inClass = false;

		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];

			if (is_array($token)) {
				list($id, $text) = $token;

				// Обработка объявления namespace
				if (T_NAMESPACE === $id) {
					$inNamespace = true;
					continue;
				}

				if ($inNamespace && T_NAME_QUALIFIED === $id) {
					$namespace = $text;
					$inNamespace = false;
				}

				// Обработка объявления class или interface
				if ((T_CLASS === $id || T_INTERFACE === $id) && !$inClass) {
					$inClass = true;
					continue;
				}

				if ($inClass && T_STRING === $id) {
					$className = $text;
					$fullClassName = ltrim($namespace . '\\' . $className, '\\');
					$classNames[] = $fullClassName;
					$inClass = false;
				}
			} else {
				if ('{' === $token) {
					$inClass = false;
				}
			}
		}

		return $classNames;
	}

	/**
	 * @throws SystemException
	 * @throws \Bitrix\Main\DB\SqlQueryException
	 * @throws \Bitrix\Main\NotSupportedException
	 */
	public function run(): void
	{
		$this->logger->addDb([
			'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.MIGRATE_ORM_IN_PROGRESS',
				['#COUNT#' => count($this->ormClasses)]),
			'RESULT' => implode(', ', $this->ormClasses),
		],
			Logger::TYPE_INFO
		);
		$this->findMediatorEntities();
		foreach ($this->ormClasses as $class)
		{
			try
			{
				$this->logger->add(
					Loc::getMessage('INTERVOLGA_MIGRATO.START_MIGRATE_ORM', ['#CLASS#' => $class]),
					Logger::LEVEL_NORMAL,
					Logger::TYPE_INFO
				);
				$this->processClass($class);
				$this->logger->add(
					Loc::getMessage('INTERVOLGA_MIGRATO.FINISH_MIGRATE_ORM', ['#CLASS#' => $class]),
					Logger::LEVEL_NORMAL,
					Logger::TYPE_OK
				);
			}
			catch (\Exception $exception)
			{
				$this->logger->addDb(
					array(
						'EXCEPTION' => $exception,
						'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.START_MIGRATE_ORM', ['#CLASS#' => $class]),
					),
					Logger::TYPE_FAIL
				);
			}
		}
	}

	/**
	 * @param class-string<\Bitrix\Main\ORM\Data\DataManager> $class
	 * @throws SystemException
	 * @throws \Bitrix\Main\DB\SqlQueryException
	 * @throws \Bitrix\Main\NotSupportedException
	 */
	protected function processClass($class): void
	{
		$connection = Application::getConnection($class::getConnectionName());
		/** @var Entity $entity */
		$entity = $class::getEntity();
		$tableName = $class::getTableName();

		if (!$connection->isTableExists($tableName))
		{
			//Хак для переопределения полей некоторых таблиц
			$ormClassReflection = new \ReflectionClass($class);
			if ($ormClassReflection->hasMethod('getCreateTableSql'))
			{
				$ormCreateTableSql = $class::getCreateTableSql();
			}
			else
			{
				$ormCreateTableSql = $entity->compileDbTableStructureDump()[0];
			}
			$connection->executeSqlBatch($ormCreateTableSql);
			$this->logger->add(
				Loc::getMessage('INTERVOLGA_MIGRATO.MIGRATE_ORM_TABLE_CREATED', ['#TABLE#' => $tableName]),
				Logger::LEVEL_SHORT,
				Logger::TYPE_OK
			);
		}
		else
		{
			//Хак для переопределения полей некоторых таблиц
			$ormClassReflection = new \ReflectionClass($class);
			if ($ormClassReflection->hasMethod('getCreateTableSql'))
			{
				$ormCreateTableSql = $class::getCreateTableSql();
			}
			else
			{
				$ormCreateTableSql = $entity->compileDbTableStructureDump()[0];
			}
			$existTable = $this->getTableObject($connection->query('SHOW CREATE TABLE '.$tableName)->fetch()['Create Table']);
			$ormTable = $this->getTableObject($ormCreateTableSql);

			$diffQueries = $this->getDiffQueries($existTable, $ormTable, mb_strtoupper($connection->getType()));
			if (count($diffQueries))
			{
				$errors = $connection->executeSqlBatch(implode(';'.PHP_EOL, $diffQueries));
				if (count($errors))
				{
					throw new SystemException($errors[0]);
				}
			}
		}
	}

	/**
	 * @param string $sqlStr
	 * @return Table
	 * @throws \Bitrix\Main\NotSupportedException
	 */
	protected function getTableObject(string $sqlStr): Table
	{
		$existTableToken = Tokenizer::createFromString($sqlStr);
		$existTableToken->nextToken();
		$existTableToken->nextToken();
		$existTableToken->nextToken();
		return Table::create($existTableToken);
	}

	/**
	 * @param Table $existTable
	 * @param Table $ormTable
	 * @param string $connectionType
	 * @return array
	 */
	protected function getDiffQueries(Table $existTable, Table $ormTable, string $connectionType): array
	{
		$queries = [];
		foreach ($ormTable->columns->compare($existTable->columns) as $pair)
		{
			/** @var Column $ormColumn */
			$ormColumn = $pair[0];
			/** @var Column $existsColumn */
			$existsColumn = $pair[1];
			if (!($ormColumn instanceof Column))
			{
				if (!$this->isSafeDeleteMode)
				{
					$queries[] = $existsColumn->getDropDdl($connectionType);
					$this->logger->add(
						Loc::getMessage('INTERVOLGA_MIGRATO.MIGRATE_ORM_COLUMN_DELETED',
							['#COLUMN#' => $existsColumn->name, '#TABLE#' => $existTable->name]),
						Logger::LEVEL_SHORT,
						Logger::TYPE_OK
					);
				}
			}
			elseif (!($existsColumn instanceof Column))
			{
				$queries[] = $ormColumn->getCreateDdl($connectionType);
				$this->logger->add(
					Loc::getMessage('INTERVOLGA_MIGRATO.MIGRATE_ORM_COLUMN_CREATED',
						['#COLUMN#' => $existsColumn->name, '#TABLE#' => $existTable->name]),
					Logger::LEVEL_SHORT,
					Logger::TYPE_OK
				);
			}
			else
			{
				$exDefault = $existsColumn->default == 'NULL' ? null : $existsColumn->default;
				$ormDefault = $ormDefault->default == 'NULL' ? null : $ormDefault->default;
				if (
					$existsColumn->type !== $ormColumn->type
					|| $existsColumn->nullable !== $ormColumn->nullable
					|| $exDefault !== $ormDefault->default
					//Меняем длину колонки только если в БД она меньше чем в orm-классе
					|| ($existsColumn->length && $ormColumn->length && $existsColumn->length < $ormColumn->length)
				)
				{
					$queries[] = $existsColumn->getModifyDdl($ormColumn, $connectionType);
					$this->logger->add(
						Loc::getMessage('INTERVOLGA_MIGRATO.MIGRATE_ORM_COLUMN_CHANGED',
							['#COLUMN#' => $existsColumn->name, '#TABLE#' => $existTable->name]),
						Logger::LEVEL_SHORT,
						Logger::TYPE_OK
					);
				}
			}
		}

		return $queries;
	}

	/**
	 * Ищет классы промежуточных сущностей в связях ManyToMany
	 *
	 * @throws SystemException
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function findMediatorEntities(): void
	{
		foreach ($this->ormClasses as $ormClass)
		{
			foreach ($ormClass::getEntity()->getFields() as $field)
			{
				if ($field instanceof ManyToMany)
				{
					$this->addClass($field->getMediatorEntity()->getDataClass());
				}
			}
		}
	}
}