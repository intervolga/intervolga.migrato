<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Logger;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

class ImportCommand extends BaseCommand
{
	const TWO_HOURS = 7200;

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
		if (!$this->input->getOption('force')
			&& !$this->checkSiteBackup()
		)
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
			}
			else
			{
				$this->runSubcommand('importdata');
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
	protected function checkSiteBackup()
	{
		$backupDates = array();
		$hasFreshBackup = false;
		$backupDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/backup';

		$files = scandir($backupDir);
		foreach ($files as $file)
		{
			if (stripos($file, '.tar.gz') !== false
				&& stripos($file, '_full_') !== false
			)
			{
				$fileModificationTime = filemtime($backupDir . '/' . $file);
				$fileLifeTime = time() - $fileModificationTime;
				$backupDates[] = $fileModificationTime;

				if (!$hasFreshBackup && $fileLifeTime <= self::TWO_HOURS)
				{
					$hasFreshBackup = true;
				}
			}
		}

		$msg = Loc::getMessage('INTERVOLGA_MIGRATO.FOUND_ACTUAL_BACKUP');
		if (!$hasFreshBackup)
		{
			$msg = Loc::getMessage('INTERVOLGA_MIGRATO.BACKUP_WARN') . PHP_EOL;
			if ($backupDates)
			{
				$msg .= Loc::getMessage(
						'INTERVOLGA_MIGRATO.LAST_BACKUP_DATE',
						array('#DATE_LAST_BACKUP#' => date('Y-m-d H:i:s', max($backupDates)))
					) . PHP_EOL;
			}
			$msg .= Loc::getMessage('INTERVOLGA_MIGRATO.BACKUP_WARN_HINT');
		}

		$this->logger->separate();
		$this->logger->add($msg, 0, $hasFreshBackup ? Logger::TYPE_INFO : Logger::TYPE_FAIL);

		return $hasFreshBackup;
	}
}