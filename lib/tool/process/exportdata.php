<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\Orm\LogTable;

Loc::loadMessages(__FILE__);

class ExportData extends BaseProcess
{
	public static function run()
	{
		parent::run();
		$errors = Validate::validate();
		Validate::findUseNotClasses();
		if (!$errors)
		{
			static::startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_EXPORT'));
			$configDataClasses = Config::getInstance()->getDataClasses();
			foreach ($configDataClasses as $data)
			{
				static::exportData($data);
			}
			static::reportStepLogs();
		}

		parent::finalReport();
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	protected static function exportData(BaseData $dataClass)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . "/";
		checkDirPath($path);
		DataFileViewXml::markDataDeleted($path);

		$filter = Config::getInstance()->getDataClassFilter($dataClass);
		$records = $dataClass->getList($filter);
		foreach ($records as $record)
		{
			static::exportRecord($record);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected static function exportRecord(Record $record)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY
			. $record->getData()->getModule()
			. $record->getData()->getFilesSubdir()
			. $record->getData()->getEntityName()
			. "/";
		try
		{
			static::checkRuntimesDependencies($record->getRuntimes());
			static::checkDependencies($record->getDependencies());
			DataFileViewXml::write($record, $path);
			LogTable::add(array(
				"RECORD" => $record,
				"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_EXPORT'),
				"STEP" => Loc::getMessage('INTERVOLGA_MIGRATO.STEP_EXPORT'),
			));
		}
		catch (\Exception $exception)
		{
			LogTable::add(array(
				"RECORD" => $record,
				"EXCEPTION" => $exception,
				"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_EXPORT'),
				"STEP" => Loc::getMessage('INTERVOLGA_MIGRATO.STEP_EXPORT'),
			));
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Runtime[] $runtimes
	 */
	protected static function checkRuntimesDependencies(array $runtimes)
	{
		foreach ($runtimes as $runtime)
		{
			static::checkDependencies($runtime->getDependencies());
		}
	}

	/**
	 * @param Link[] $dependencies
	 */
	protected static function checkDependencies(array $dependencies)
	{
		foreach ($dependencies as $name => $dependency)
		{
			static::checkDependency($dependency, $name);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link $dependency
	 * @param string $name
	 *
	 * @throws \Exception
	 */
	protected static function checkDependency(Link $dependency, $name)
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