<?php

namespace Intervolga\Custom\Utils\Orm;

use Bitrix\Main\Application;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Fields\Relations\ManyToMany;
use Bitrix\Main\SystemException;
use Bitrix\Perfmon\Sql\Column;
use Bitrix\Perfmon\Sql\Table;
use Bitrix\Perfmon\Sql\Tokenizer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

	protected LoggerInterface $logger;

	public function __construct(array $classes, ?LoggerInterface $logger = null)
	{
		if (!Loader::includeModule('perfmon'))
		{
			throw new SystemException('Can not load module perfmon');
		}
		foreach ($classes as $class)
		{
			$this->addClass($class);
		}
		$this->logger = $logger ?? new NullLogger();
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
	public function loadFromDir(string $dir = '/local/modules/intervolga.custom/lib/orm'): static
	{
		$dir = $_SERVER['DOCUMENT_ROOT'].$dir;
		foreach (
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
				\RecursiveIteratorIterator::SELF_FIRST) as $item
		)
		{
			/** @var $iterator \RecursiveDirectoryIterator */
			/** @var $item \SplFileInfo */
			if ($item->isFile() && $item->isReadable() && mb_substr($item->getFilename(), -4) == '.php')
			{
				$file = $item->getPathname();
				$class = '\\';
				$content = file_get_contents($file);
				if (preg_match('/namespace\s([^\s;]+);?\s/', $content, $matches)) {
					$class .= $matches[1] . '\\';
				}
				if (preg_match('/\s?class\s([^\s]+)?\s/', $content, $matches)) {
					$class .= $matches[1];
				}

				if (!class_exists($class)) {
					continue;
				}

				$rClass = new \ReflectionClass($class);
				$migrationAttr = $rClass->getAttributes(\Intervolga\Custom\Utils\Orm\UseMigrations::class)[0]?->newInstance();
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

		return $this;
	}

	/**
	 * @throws SystemException
	 * @throws \Bitrix\Main\DB\SqlQueryException
	 * @throws \Bitrix\Main\NotSupportedException
	 */
	public function run(): void
	{
		$this->findMediatorEntities();
		foreach ($this->ormClasses as $class)
		{
			$this->processClass($class);
		}
	}

	/**
	 * @param $class
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
			$this->logger->info('Создана таблица {table}', ['table' => $tableName]);
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
					$this->logger->info('Удалена колонка {column} в {table}', ['table' => $existTable->name, 'column' => $existsColumn->name]);
				}
			}
			elseif (!($existsColumn instanceof Column))
			{
				$queries[] = $ormColumn->getCreateDdl($connectionType);
				$this->logger->info('Добавлена колонка {column} в {table}', ['table' => $existTable->name, 'column' => $existsColumn->name]);
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
					$this->logger->info('Изменена колонка {column} в {table}', ['table' => $existTable->name, 'column' => $existsColumn->name]);
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