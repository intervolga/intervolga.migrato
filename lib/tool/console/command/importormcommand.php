<?php

namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Utils\OrmTableMigration;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class ImportOrmCommand extends BaseCommand
{
	protected OrmTableMigration $migrator;

	protected function configure()
	{
		$this
			->setName('import-orm')
			->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_ORM_DESCRIPTION'))
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
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_ORM'),
				],
				Logger::TYPE_FAIL
			);
			throw $e;
		}
	}
}