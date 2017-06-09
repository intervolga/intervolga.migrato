<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

/**
 * @field \Symfony\Component\Console\Input\InputInterface $input
 */
abstract class BaseCommand extends Command
{
	protected static $mainCommand = '';

	/**
	 * @var InputInterface $input
	 */
	protected $input = null;
	protected $output = null;
	protected $clearLogs = true;
	/**
	 * @var \Intervolga\Migrato\Tool\Console\Logger $logger
	 */
	protected $logger;

	abstract public function executeInner();

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->input = $input;
		$this->output = $output;
		$this->logger = new Logger($this, $output);
		if ($this->isMainCommand())
		{
			$this->checkFiles();
			if ($this->clearLogs)
			{
				$this->logger->clearLogs();
			}
			$this->logger->startCommand();
		}
		else
		{
			$this->logger->startSubcommand();
		}
		try
		{
			$this->executeInner();
		}
		catch (\Throwable $throwable)
		{
			$this->logger->handle($throwable);
		}
		catch (\Exception $exception)
		{
			$this->logger->handle($exception);
		}
		$this->logger->addShortSummary();
		if ($this->isMainCommand())
		{
			if (true === $input->hasParameterOption(array('--fails', '-F'), true))
			{
				if (!$this instanceof LogCommand)
				{
					$this->runSubcommand('log', array('--fails' => true));
				}
			}
			$this->logger->endCommand();
		}
	}

	/**
	 * @return bool
	 */
	protected function isMainCommand()
	{
		if (!static::$mainCommand)
		{
			static::$mainCommand = get_called_class();
			return true;
		}
		return (static::$mainCommand == get_called_class());
	}

	/**
	 * @throws \Exception
	 */
	protected function checkFiles()
	{
		if (!Directory::isDirectoryExists(INTERVOLGA_MIGRATO_DIRECTORY))
		{
			Directory::createDirectory(INTERVOLGA_MIGRATO_DIRECTORY);
			CopyDirFiles(dirname(dirname(dirname(__DIR__))) . '/install/public', INTERVOLGA_MIGRATO_DIRECTORY);
		}
		if (!Config::isExists())
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.CONFIG_NOT_FOUND'));
		}
	}

	/**
	 * @param string $name
	 * @param array $arguments
	 *
	 * @return \Symfony\Component\Console\Command\Command
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function runSubcommand($name, array $arguments = array())
	{
		$command = $this->getApplication()->find($name);
		if ($command)
		{
			$command->run(new ArrayInput($arguments), $this->output);
			if ($command instanceof BaseCommand)
			{
				$this->logger->mergeTypesCounter($command->logger);
			}
		}

		return $command;
	}
}