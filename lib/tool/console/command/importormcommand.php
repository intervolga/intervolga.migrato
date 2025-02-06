<?php

namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Utils\OrmTableMigration;
use Intervolga\Migrato\Tool\Console\Logger;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Exception\RuntimeException;

class ImportOrmCommand extends BaseCommand
{
	protected OrmTableMigration $migrator;

	protected function configure()
	{
		$this
			->setName('import-orm')
			->setDescription('Актуализирует структуру таблиц в соответствии с их ORM-классами')
			->setDefinition(
				new InputDefinition([
					new InputOption(
						'safe-delete',
						null,
						InputOption::VALUE_NONE,
						'Режим без удаления столбцов'
					)
				])
			);
	}

	protected function init()
	{
		$this->migrator = new OrmTableMigration(Config::getInstance()->getOrmEntities(), $this->logger);

		$modules = Config::getInstance()->getOrmModules();
		foreach ($modules as $module)
		{
			$moduleDirPath = Loader::getLocal("modules/$module/");
			$this->migrator->loadFromDir($moduleDirPath);
		}
		$this->migrator->setSafeDeleteMode((bool)$this->input->getOption('safe-delete'));
	}

	public function executeInner()
	{
		try {
			$this->init();
			$this->migrator->run();
		} catch (RuntimeException $e) {
			$this->logger->addDb(
				[
					'EXCEPTION' => $e,
					'OPERATION' => 'Migration process',
				],
				Logger::TYPE_FAIL
			);
			throw $e;
		}
	}
}