<? namespace Intervolga\Migrato;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\DataLink;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\ImportList;

class MigratoImportData extends Migrato
{
	/**
	 * @var \Intervolga\Migrato\Tool\DataRecord[]
	 */
	protected static $recordsWithReferences = array();
	/**
	 * @var \Intervolga\Migrato\Tool\ImportList
	 */
	protected static $list = null;

	public static function run()
	{
		parent::run();

		static::init();
		static::importWithDependencies();
		static::deleteNotImported();
		static::resolveReferences();
		static::report("finishing");
	}

	protected static function init()
	{
		static::report(__FUNCTION__);
		static::$list = new ImportList();
		$configDataClasses = Config::getInstance()->getDataClasses();
		$dataClasses = static::recursiveGetDependentDataClasses($configDataClasses);
		foreach ($dataClasses as $data)
		{
			if (!in_array($data, $configDataClasses))
			{
				static::prepareNonConfigData($data);
			}
			else
			{
				static::prepareConfigData($data);
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 */
	protected static function prepareNonConfigData(BaseData $data)
	{
		foreach ($data->getList() as $localDataRecord)
		{
			static::$list->addCreatedRecord($localDataRecord);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 */
	protected static function prepareConfigData(BaseData $data)
	{
		static::$list->addExistingRecords($data->getList());
		static::$list->addRecordsToImport(static::readFromFile($data));
	}

	protected static function importWithDependencies()
	{
		static::report(__FUNCTION__);
		$configDataClasses = Config::getInstance()->getDataClasses();
		for ($i = 0; $i < count($configDataClasses); $i++)
		{
			$creatableDataRecords = static::$list->getCreatableRecords();
			static::report("Import depenency step $i, count=" . count($creatableDataRecords) . " record(s)");
			if ($creatableDataRecords)
			{
				foreach ($creatableDataRecords as $dataRecord)
				{
					static::saveDataRecord($dataRecord);
					static::$list->addCreatedRecord($dataRecord);
				}
			}
			else
			{
				break;
			}
		}
		if (static::$list->getCreatableRecords())
		{
			static::report("Not enough import depenency steps!");
		}
	}


	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return array|\Intervolga\Migrato\Tool\DataRecord[]
	 */
	protected static function readFromFile(BaseData $dataClass)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . "/";

		$data = DataFileViewXml::readFromFileSystem($path);
		foreach ($data as $i => $dataItem)
		{
			$data[$i]->setData($dataClass);
			if ($dependencies = $data[$i]->getDependencies())
			{
				$dependencies = static::restoreDependenciesFromFile($dataClass, $dependencies);
				$data[$i]->setDependencies($dependencies);
			}
			if ($references = $data[$i]->getReferences())
			{
				$references = static::restoreReferencesFromFile($dataClass, $references);
				$data[$i]->setReferences($references);
			}
		}

		return $data;
	}

	/**
	 * @param BaseData $dataClass
	 * @param DataLink[] $dependencies
	 *
	 * @return DataLink[]
	 */
	protected static function restoreDependenciesFromFile(BaseData $dataClass, array $dependencies)
	{
		$result = array();
		foreach ($dependencies as $key => $dependency)
		{
			$dependencyModel = $dataClass->getDependency($key);
			if ($dependencyModel)
			{
				$clone = clone $dependencyModel;
				$clone->setXmlId($dependency->getXmlId());
				$result[$key] = $clone;
			}
		}

		return $result;
	}

	/**
	 * @param BaseData $dataClass
	 * @param DataLink[] $references
	 *
	 * @return DataLink[]
	 */
	protected static function restoreReferencesFromFile(BaseData $dataClass, array $references)
	{
		$result = array();
		foreach ($references as $key => $reference)
		{
			$referenceModel = $dataClass->getReference($key);
			if ($referenceModel)
			{
				$clone = clone $referenceModel;
				$clone->setXmlId($reference->getXmlId());
				$result[$key] = $clone;
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 */
	protected static function saveDataRecord(DataRecord $dataRecord)
	{
		if ($dataRecord->getReferences())
		{
			static::$recordsWithReferences[] = $dataRecord;
		}
		if ($dataRecordId = $dataRecord->getData()->findRecord($dataRecord->getXmlId()))
		{
			try
			{
				$dataRecord->setId($dataRecordId);
				$dataRecord->update();
				static::reportRecord($dataRecord, "updated");
			}
			catch (\Exception $exception)
			{
				static::reportRecordException($dataRecord, $exception, "update");
			}
		}
		else
		{
			try
			{
				$id = $dataRecord->create();
				$dataRecord->setId($id);
				static::reportRecord($dataRecord, "created");
			}
			catch (\Exception $exception)
			{
				static::reportRecordException($dataRecord, $exception, "create");
			}
		}
	}

	protected static function deleteNotImported()
	{
		static::report(__FUNCTION__);
		foreach (static::$list->getRecordsToDelete() as $dataRecord)
		{
			try
			{
				$dataRecord->delete();
				static::reportRecord($dataRecord, "deleted");
			}
			catch (\Exception $exception)
			{
				static::reportRecordException($dataRecord, $exception, "delete");
			}
		}
	}

	protected static function resolveReferences()
	{
		static::report(__FUNCTION__);
		foreach (static::$recordsWithReferences as $dataRecord)
		{
			$clone = clone $dataRecord;
			$clone->setFields(array());
			$clone->setDependencies(array());
			foreach ($clone->getReferences() as $reference)
			{
				$id = $reference->getTargetData()->findRecord($reference->getXmlId());
				if ($id)
				{
					$reference->setId($id);
				}
			}
			try
			{
				$clone->update();
				static::reportRecord($dataRecord, "updated references");
			}
			catch (\Exception $exception)
			{
				static::reportRecordException($dataRecord, $exception, "update reference");
			}
		}
	}
}