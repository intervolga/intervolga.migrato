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

	public static function run()
	{
		$result = array();
		$list = new DataRecordsResolveList();
		foreach (Config::getInstance()->getDataClasses() as $data)
		{
			foreach ($data->getList() as $dataRecord)
			{
				if ($dataRecord->getXmlId())
				{
					static::$baseBeforeImport[$data->getModule()][$data->getEntityName()][] = $dataRecord->getXmlId();
				}
			}

			$list->addDataRecords(static::readFromFile($data));
		}

		for ($i = 0; $i < count(Config::getInstance()->getDataClasses()) * 2; $i++)
		{
			$creatableDataRecords = $list->getCreatableDataRecords();
			if ($creatableDataRecords)
			{
				foreach ($creatableDataRecords as $dataRecord)
				{
					$result[] = static::saveDataRecord($dataRecord);
					$list->setCreated($dataRecord);
				}
			}
			else
			{
				break;
			}
		}
		$result = array_merge($result, static::deleteNotImported());

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
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 *
	 * @return string
	 */
	protected static function saveDataRecord(DataRecord $dataRecord)
	{
		$nameForReport = "Data " . $dataRecord->getData()->getModule() . "/" . $dataRecord->getData()->getEntityName() . " (" . $dataRecord->getXmlId() . ")";
		if ($dataRecordId = $dataRecord->getData()->findRecord($dataRecord->getXmlId()))
		{
			try
			{
				$dataRecord->setId($dataRecordId);
				$dataRecord->getData()->update($dataRecord);
				static::$imported[$dataRecord->getData()->getModule()][$dataRecord->getData()->getEntityName()][] = $dataRecord->getXmlId();
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
				$dataRecord->getData()->create($dataRecord);
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
	 * @return array|string[]
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
}