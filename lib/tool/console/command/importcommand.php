<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Command\ReIndexFacetCommand;
use Intervolga\Migrato\Tool\Console\Logger;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

class ImportCommand extends BaseCommand
{
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
	}

	public function executeInner()
	{
		$this->wasClosedAtStart = $this->isSiteClosed();
		$this->closeSite();
		try
		{
			ReIndexFacetCommand::saveActiveFacet();
			$this->runSubcommand('importdata');
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
		return (Option::get("main", "site_stopped") == 'Y');
	}
}