<?php

namespace Intervolga\Custom\Cli\Command;

use Intervolga\Custom\Utils\Orm\OrmTableMigration;
use Intervolga\Migrato\Tool\Console\Command\BaseCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class OrmMigrateTablesCommand extends BaseCommand
{
	protected function configure()
	{
		$this
			->setName('orm:migrate-tables')
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

	public function execute(InputInterface $input, OutputInterface $output) : int
	{
		$output->writeln('Start migrating');
		$logger = new ConsoleLogger($output);
		$migrator = new OrmTableMigration([
			//table classes here...
		], $logger);

		$migrator
			->loadFromDir()
			->loadFromDir('/local/modules/intervolga.queue/lib/orm')
			->setSafeDeleteMode((bool)$input->getOption('safe-delete'))
			->run();

		$output->writeln('Command finished');
		return 0;
	}

	public function executeInner()
	{
		// TODO: Implement executeInner() method.
	}
}