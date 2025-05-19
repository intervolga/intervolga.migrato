<?php namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Logger;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

class ImportCommand extends BaseCommand
{
	const MAX_ACTUAL_BACKUP_TIME_SECONDS = 7200;

	protected $wasClosedAtStart = false;

	protected function configure()
	{
		$this->setName('import');
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

		$this->wasClosedAtStart = $this->isSiteClosed();
		$this->closeSite();

		try
		{
			ReIndexFacetCommand::saveActiveFacet();
			if ($this->input->getOption('safe-delete'))
			{
				$args['--safe-delete'] = true;
				$this->runSubcommand('importdata', array('--safe-delete' => true));
				$this->runSubcommand('import-orm', array('--safe-delete' => true));
			}
			else
			{
				$this->runSubcommand('importdata');
				$this->runSubcommand('import-orm');
			}
			$this->runSubcommand('importoptions');
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
			$this->openSite();
			throw $throwable;
		}
		catch (\Exception $exception)
		{
			$this->openSite();
			throw $exception;
		}

		$this->openSite();
	}

	protected function closeSite()
	{
		if (!$this->wasClosedAtStart)
		{
			$this->logger->separate();
			$this->logger->add(
				Loc::getMessage('INTERVOLGA_MIGRATO.SITE_CLOSED'),
				0,
				Logger::TYPE_INFO
			);
			Option::set("main", "site_stopped", "Y");
		}
	}

	protected function openSite()
	{
		if (!$this->wasClosedAtStart)
		{
			Option::set("main", "site_stopped", "N");
			$this->logger->separate();
			$this->logger->add(
				Loc::getMessage('INTERVOLGA_MIGRATO.SITE_OPENED'),
				0,
				Logger::TYPE_INFO
			);
		}
	}

	/**
	 * @return bool
	 * @throws \Bitrix\Main\ArgumentNullException
	 */
	protected function isSiteClosed()
	{
		return (Option::get('main', 'site_stopped') === 'Y');
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