<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Logger;
use Symfony\Component\Console\Input\InputOption;
use Intervolga\Migrato\Tool\Console\DiffCounter;

Loc::loadMessages(__FILE__);

class DiffCommand extends BaseCommand
{
	const MAX_ACTUAL_BACKUP_TIME_SECONDS = 7200;

	protected function configure()
	{
		$this->setName('diff');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_DESCRIPTION'));
		$this->addOption(
			'quick',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.NO_AFTER_CLEAR_DESCRIPTION')
		);
		$this->addOption(
			'force',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.IGNORE_NEW_BACKUPS')
		);
		$this->addOption(
			'safe-delete',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.OPTION_SAFE_DELETE')
		);
	}

	public function executeInner()
	{
		if ($this->hasBackupProblem())
		{
			return;
		}

		try
		{
			ReIndexFacetCommand::saveActiveFacet();
			if ($this->input->getOption('safe-delete'))
			{
				$args['--safe-delete'] = true;
				$this->runSubcommand('diffdata', array('--safe-delete' => true));
			}
			else
			{
				$this->runSubcommand('diffdata');
			}
			if (!$this->input->getOption('quick'))
			{
				$this->runSubcommand('clearcache');
				$this->runSubcommand('urlrewrite');
				$this->runSubcommand('reindex');
				$this->runSubcommand('reindexfacet');
			}
		}
		catch (\Throwable $throwable)
		{
			throw $throwable;
		}
		catch (\Exception $exception)
		{
			throw $exception;
		}
	}

	/**
	 * @return bool
	 */
	protected function hasBackupProblem()
	{
		$result = false;
		if (!$this->input->getOption('force'))
		{
			$lastDate = $this->getLastFullBackupDate();
			if ((time() - $lastDate) >= static::MAX_ACTUAL_BACKUP_TIME_SECONDS)
			{
				if ($lastDate)
				{
					$msg = Loc::getMessage('INTERVOLGA_MIGRATO.NOT_ACTUAL_BACKUP', array('#LAST_DATE#' => date('d.m.Y H:i:s', $lastDate)));
				}
				else
				{
					$msg = Loc::getMessage('INTERVOLGA_MIGRATO.NO_ACTUAL_BACKUP');
				}

				$this->logger->separate();
				$this->logger->add($msg, 0, Logger::TYPE_FAIL);
				$result = true;
			}
			else
			{
				$msg = Loc::getMessage('INTERVOLGA_MIGRATO.FOUND_ACTUAL_BACKUP', array('#LAST_DATE#' => date('d.m.Y H:i:s', $lastDate)));
				$this->logger->separate();
				$this->logger->add($msg, 0, Logger::TYPE_INFO);
			}
		}

		return $result;
	}

	/**
	 * @return int
	 */
	protected function getLastFullBackupDate()
	{
		$backupDates = array(0);
		$directory = new Directory(Application::getDocumentRoot() . '/bitrix/backup');
		foreach ($directory->getChildren() as $fileSystemEntry)
		{
			if ($fileSystemEntry instanceof File)
			{
				$fileSystemEntry->getName();
				if (substr_count($fileSystemEntry->getName(), '.tar.gz') && substr_count($fileSystemEntry->getName(), '_full_'))
				{
					$backupDates[] = $fileSystemEntry->getModificationTime();
				}
			}
		}

		return max($backupDates);
	}
}
