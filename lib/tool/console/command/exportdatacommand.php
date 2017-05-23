<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataFileViewXml;

Loc::loadMessages(__FILE__);

class ExportDataCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('exportdata');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.EXPORT_DATA_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->runSubcommand('unused');
		/**
		 * @var ValidateCommand $validateCommand
		 */
		$validateCommand = $this->runSubcommand('validatexmlid');
		$errors = $validateCommand->getLastExecuteResult();
		if (!$errors)
		{
			$configDataClasses = Config::getInstance()->getDataClasses();
			foreach ($configDataClasses as $data)
			{
				$this->exportData($data);
			}
		}
	}

	public function export()
	{
		$configDataClasses = Config::getInstance()->getDataClasses();
		foreach ($configDataClasses as $data)
		{
			$this->exportData($data);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	protected function exportData(BaseData $dataClass)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . "/";
		checkDirPath($path);
		DataFileViewXml::markDataDeleted($path);

		$filter = Config::getInstance()->getDataClassFilter($dataClass);
		$records = $dataClass->getList($filter);
		foreach ($records as $record)
		{
			$this->exportRecord($record);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected function exportRecord(Record $record)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY
			. $record->getData()->getModule()
			. $record->getData()->getFilesSubdir()
			. $record->getData()->getEntityName()
			. "/";
		try
		{
			$this->checkRuntimesDependencies($record->getRuntimes());
			$this->checkDependencies($record->getDependencies());
			DataFileViewXml::write($record, $path);
			$this->logger->addDb(
				array(
					"RECORD" => $record,
					"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_EXPORT'),
				),
				Logger::TYPE_OK
			);
		}
		catch (\Exception $exception)
		{
			$this->logger->addDb(
				array(
					"RECORD" => $record,
					"EXCEPTION" => $exception,
					"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_EXPORT'),
				),
				Logger::TYPE_FAIL
			);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Runtime[] $runtimes
	 */
	protected function checkRuntimesDependencies(array $runtimes)
	{
		foreach ($runtimes as $runtime)
		{
			$this->checkDependencies($runtime->getDependencies());
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $dependencies
	 */
	protected function checkDependencies(array $dependencies)
	{
		foreach ($dependencies as $name => $dependency)
		{
			$this->checkDependency($dependency, $name);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link $dependency
	 * @param string $name
	 *
	 * @throws \Exception
	 */
	protected function checkDependency(Link $dependency, $name)
	{
		if ($dependency->isMultiple())
		{
			if (!$dependency->getValues())
			{
				throw new \Exception("Values for dependency <$name> are not set!");
			}
		}
		else
		{
			if (!$dependency->getValue())
			{
				throw new \Exception("Value for dependency <$name> is not set!");
			}
		}
	}
}