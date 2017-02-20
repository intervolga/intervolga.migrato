<?namespace Intervolga\Migrato;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\DataLink;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataRecordsResolveList;

class MigratoImportData extends Migrato
{
	protected static $baseBeforeImport = array();
	protected static $imported = array();
	/**
	 * @var DataRecord[]
	 */
	protected static $references = array();
	/**
	 * @var DataRecordsResolveList
	 */
	protected static $list = null;

	public static function run()
	{
		static::prepareList();
		$result = static::importWithDependencies();
		$result = array_merge($result, static::deleteNotImported());
		$result = array_merge($result, static::resolveReferences());

		return $result;
	}

	protected static function prepareList()
	{
		static::$list = new DataRecordsResolveList();
		$configDataClasses = Config::getInstance()->getDataClasses();
		$dataClasses = static::recursiveGetDependentDataClasses($configDataClasses);
		foreach ($dataClasses as $data)
		{
			if (in_array($data, $dataClasses) && !in_array($data, $configDataClasses))
			{
				$localDataRecords = $data->getList();
				foreach ($localDataRecords as $localDataRecord)
				{
					static::$list->setCreated($localDataRecord);
				}
			}
			else
			{
				foreach ($data->getList() as $dataRecord)
				{
					if ($dataRecord->getXmlId())
					{
						static::$baseBeforeImport[$data->getModule()][$data->getEntityName()][] = $dataRecord->getXmlId();
					}
				}

				static::$list->addDataRecords(static::readFromFile($data));
			}
		}
	}

	/**
	 * @return string[]
	 */
	protected static function importWithDependencies()
	{
		$result = array();
		$configDataClasses = Config::getInstance()->getDataClasses();
		for ($i = 0; $i < count($configDataClasses) * 2; $i++)
		{
			$creatableDataRecords = static::$list->getCreatableDataRecords();
			if ($creatableDataRecords)
			{
				foreach ($creatableDataRecords as $dataRecord)
				{
					$result[] = static::saveDataRecord($dataRecord);
					static::$list->setCreated($dataRecord);
				}
			}
			else
			{
				break;
			}
		}

		return $result;
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
	 *
	 * @return string
	 */
	protected static function saveDataRecord(DataRecord $dataRecord)
	{
		$nameForReport = "Data " . $dataRecord->getData()->getModule() . "/" . $dataRecord->getData()->getEntityName() . " (" . $dataRecord->getXmlId() . ")";
		if ($dataRecord->getReferences())
		{
			static::$references[] = $dataRecord;
		}
		if ($dataRecordId = $dataRecord->getData()->findRecord($dataRecord->getXmlId()))
		{
			try
			{
				$dataRecord->setId($dataRecordId);
				static::$imported[$dataRecord->getData()->getModule()][$dataRecord->getData()->getEntityName()][] = $dataRecord->getXmlId();
				$dataRecord->update();
				return $nameForReport . " updated";
			}
			catch (\Exception $exception)
			{
				return $nameForReport . " update exception: " . $exception->getMessage();
			}
		}
		else
		{
			try
			{
				$id = $dataRecord->create();
				$dataRecord->setId($id);
				static::$imported[$dataRecord->getData()->getModule()][$dataRecord->getData()->getEntityName()][] = $dataRecord->getXmlId();
				return $nameForReport . " created";
			}
			catch (\Exception $exception)
			{
				return $nameForReport . " create exception: " . $exception->getMessage();
			}
		}
	}

	/**
	 * @return string[]
	 */
	protected static function deleteNotImported()
	{
		$result = array();
		foreach (Config::getInstance()->getDataClasses() as $data)
		{
			foreach (static::$baseBeforeImport[$data->getModule()][$data->getEntityName()] as $xmlId)
			{
				$nameForReport = "Data " . $data->getModule() . "/" . $data->getEntityName() . " (" . $xmlId . ")";
				if (!in_array($xmlId, static::$imported[$data->getModule()][$data->getEntityName()]))
				{
					try
					{
						$data->delete($xmlId);
						$result[] = $nameForReport . " deleted";
					}
					catch (\Exception $exception)
					{
						$result[] = $nameForReport . " delete error: " . $exception->getMessage();
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @return string[]
	 */
	protected static function resolveReferences()
	{
		$result = array();
		foreach (static::$references as $dataRecord)
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
			}
			catch (\Exception $exception)
			{
				$nameForReport = "Data " . $dataRecord->getData()->getModule() . "/" . $dataRecord->getData()->getEntityName() . " (" . $clone->getXmlId() . ")";
				$result[] = $nameForReport . " update reference exception: " . $exception->getMessage();
			}
		}
		if ($result)
		{
			array_unshift($result, "-----resolveReferences() fail-----");
		}

		return $result;
	}
}