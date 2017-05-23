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

	protected $output = null;
	/**
	 * @var \Intervolga\Migrato\Tool\Console\Logger $logger
	 */
	protected $logger;

	abstract public function executeInner();

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->output = $output;
		$this->logger = new Logger($this, $output);
		if ($this->isMainCommand())
		{
			$this->checkFiles();
			$this->logger->startCommand();
		}
		else
		{
			$this->logger->startSubcommand();
		}
		$this->executeInner();
		$this->logger->addShortSummary();
		if ($this->isMainCommand())
		{
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
	 * @param \Intervolga\Migrato\Data\BaseData[] $dataClasses
	 *
	 * @return \Intervolga\Migrato\Data\BaseData[]
	 */
	protected function recursiveGetDependentDataClasses(array $dataClasses)
	{
		$newClassesAdded = false;
		foreach ($dataClasses as $dataClass)
		{
			$dependencies = $dataClass->getDependencies();
			if ($dependencies)
			{
				foreach ($dependencies as $dependency)
				{
					$dependentDataClass = $dependency->getTargetData();
					if (!in_array($dependentDataClass, $dataClasses))
					{
						$dataClasses[] = $dependentDataClass;
						$newClassesAdded = true;
					}
				}
			}
			$references = $dataClass->getReferences();
			if ($references)
			{
				foreach ($references as $reference)
				{
					$dependentDataClass = $reference->getTargetData();
					if (!in_array($dependentDataClass, $dataClasses))
					{
						$dataClasses[] = $dependentDataClass;
						$newClassesAdded = true;
					}
				}
			}
		}
		if ($newClassesAdded)
		{
			return $this->recursiveGetDependentDataClasses($dataClasses);
		}
		else
		{
			return $dataClasses;
		}
	}

	/**
	 * @param string $name
	 *
	 * @return \Symfony\Component\Console\Command\Command
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function runSubcommand($name)
	{
		$command = $this->getApplication()->find($name);
		if ($command)
		{
			$command->run(new ArrayInput(array()), $this->output);
			if ($command instanceof BaseCommand)
			{
				$this->logger->mergeTypesCounter($command->logger);
			}
		}

		return $command;
	}
}