<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Runtime;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\ImportList;
use Intervolga\Migrato\Tool\Orm\LogTable;

class ImportData extends BaseProcess
{
	/**
	 * @var \Intervolga\Migrato\Data\Record[]
	 */
	protected static $recordsWithReferences = array();
	/**
	 * @var \Intervolga\Migrato\Tool\ImportList
	 */
	protected static $list = null;
	/**
	 * @var \Intervolga\Migrato\Data\Record[]
	 */
	protected static $deleteRecords = array();

	public static function run()
	{
		parent::run();

		$errors = Validate::validate();
		if (!$errors)
		{
			static::init();
			static::importWithDependencies();
			static::logNotResolved();
			static::showNotImported();
			static::deleteMarked();
			static::resolveReferences();
		}

		parent::finalReport();
	}

	protected static function init()
	{
		static::startStep("init");
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

		// Добавить dependencies of runtimes
		static::$list->addCreatedRecordRuntimes();
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 */
	protected static function prepareNonConfigData(BaseData $data)
	{
		$dataGetList = $data->getList();
		foreach ($dataGetList as $localDataRecord)
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
		$records = array();
		foreach (static::readFromFile($data) as $record)
		{
			if ($record->getDeleteMark())
			{
				static::$deleteRecords[] = $record;
			}
			else
			{
				$records[] = $record;
			}
		}

		static::$list->addRecordsToImport($records);
	}

	protected static function importWithDependencies()
	{
		$configDataClasses = Config::getInstance()->getDataClasses();
		for ($i = 0; $i < count($configDataClasses); $i++)
		{
			static::startStep(__FUNCTION__ . " $i");
			$creatableDataRecords = static::$list->getCreatableRecords();
			if ($creatableDataRecords)
			{
				static::report("Import step $i, count=" . count($creatableDataRecords) . " record(s)");
				foreach ($creatableDataRecords as $dataRecord)
				{
					static::saveDataRecord($dataRecord);
					static::$list->addCreatedRecord($dataRecord);
				}
				static::reportStepLogs();
			}
			else
			{
				break;
			}
		}

		if (static::$list->getCreatableRecords())
		{
			static::report("Not enough import depenency steps!", "fail");
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return array|\Intervolga\Migrato\Data\Record[]
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
			if ($runtimes = $data[$i]->getRuntimes())
			{
				$runtimes = static::restoreRuntimesFromFile($dataClass, $runtimes);
				$data[$i]->setRuntimes($runtimes);
				foreach ($runtimes as $name => $runtime)
				{
					if ($runtime->getData())
					{
						foreach ($runtime->getFields() as $runtimeFieldName => $runtimeFieldValue)
						{
							$data[$i]->setReference("RUNTIME.$name", new Link($runtime->getData(), $runtimeFieldName));
						}
						foreach ($runtime->getReferences() as $runtimeFieldName => $runtimeFieldValue)
						{
							$data[$i]->setReference("RUNTIME.$name", new Link($runtime->getData(), $runtimeFieldName));
							$data[$i]->setReference("RUNTIME.$name." . $runtimeFieldName, $runtimeFieldValue);
						}
						foreach ($runtime->getDependencies() as $runtimeFieldName => $runtimeFieldValue)
						{
							$data[$i]->setReference("RUNTIME.$name", new Link($runtime->getData(), $runtimeFieldName));
							$data[$i]->setDependency("RUNTIME.$name." . $runtimeFieldName, $runtimeFieldValue);
						}
					}
				}
			}
		}

		return $data;
	}

	/**
	 * @param BaseData $dataClass
	 * @param \Intervolga\Migrato\Data\Link[] $dependencies
	 *
	 * @return Link[]
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
				if ($dependency->isMultiple())
				{
					$clone->setValues($dependency->getValues());
				}
				else
				{
					$clone->setValue($dependency->getValue());
				}
				$result[$key] = $clone;
			}
		}

		return $result;
	}

	/**
	 * @param BaseData $dataClass
	 * @param Link[] $references
	 *
	 * @return Link[]
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
				if ($reference->isMultiple())
				{
					$clone->setValues($reference->getValues());
				}
				else
				{
					$clone->setValue($reference->getValue());
				}
				$result[$key] = $clone;
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param \Intervolga\Migrato\Data\Runtime[] $runtimes
	 *
	 * @return \Intervolga\Migrato\Data\Runtime[]
	 */
	protected static function restoreRuntimesFromFile(BaseData $dataClass, array $runtimes)
	{
		$result = array();
		foreach ($runtimes as $key => $runtime)
		{
			$runtimeModel = $dataClass->getRuntime($key);
			if ($runtimeModel)
			{
				$clone = clone $runtimeModel;
				$clone->setFields($runtime->getFields());
				$clone->setDependencies($runtime->getDependencies());
				$clone->setReferences($runtime->getReferences());
				$result[$key] = $clone;
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 */
	protected static function saveDataRecord(Record $dataRecord)
	{
		if ($dataRecord->getReferences())
		{
			static::$recordsWithReferences[] = $dataRecord;
		}
		if ($dataRecordId = $dataRecord->getData()->findRecord($dataRecord->getXmlId()))
		{
			static::updateWithLog($dataRecordId, $dataRecord);
		}
		else
		{
			static::createWithLog($dataRecord);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $dataRecordId
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 */
	protected static function updateWithLog(RecordId $dataRecordId, Record $dataRecord)
	{
		try
		{
			foreach ($dataRecord->getDependencies() as $dependency)
			{
				self::setLinkId($dependency);
			}
			self::setRuntimesId($dataRecord->getRuntimes());

			$dataRecord->setId($dataRecordId);
			$dataRecord->update();
			LogTable::add(array(
				"RECORD" => $dataRecord,
				"OPERATION" => "update",
				"STEP" => static::$step,
			));
		}
		catch (\Exception $exception)
		{
			LogTable::add(array(
				"RECORD" => $dataRecord,
				"EXCEPTION" => $exception,
				"OPERATION" => "update",
				"STEP" => static::$step,
			));
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 */
	protected static function createWithLog(Record $dataRecord)
	{
		try
		{
			foreach($dataRecord->getDependencies() as $dependency)
			{
				self::setLinkId($dependency);
			}
			$dataRecord->setId($dataRecord->create());
			$dataRecord->getData()->setXmlId(
				$dataRecord->getId(),
				$dataRecord->getXmlId()
			);
			LogTable::add(array(
				"RECORD" => $dataRecord,
				"OPERATION" => "create",
				"STEP" => static::$step,
			));
		}
		catch (\Exception $exception)
		{
			LogTable::add(array(
				"RECORD" => $dataRecord,
				"EXCEPTION" => $exception,
				"OPERATION" => "create",
				"STEP" => static::$step,
			));
		}
	}

	protected static function logNotResolved()
	{
		static::$step = __FUNCTION__;
		foreach (static::$list->getNotResolvedRecords() as $notResolvedRecord)
		{
			LogTable::add(array(
				"RECORD" => $notResolvedRecord,
				"EXCEPTION" => new \Exception("Dependencies not resolved"),
				"OPERATION" => "resolve",
				"STEP" => static::$step,
			));
		}
	}

	protected static function showNotImported()
	{
		static::startStep(__FUNCTION__);
		foreach (static::$list->getRecordsToDelete() as $dataRecord)
		{
			LogTable::add(array(
				"RECORD" => $dataRecord,
				"OPERATION" => "not import",
				"STEP" => static::$step,
			));
		}
		$getList = LogTable::getList(array("filter" => array("=STEP" => static::$step)));
		while ($logs = $getList->fetch())
		{
			static::report(
				Loc::getMessage(
					"INTERVOLGA_MIGRATO.STATISTIC_ONE_RECORD",
					array(
						"#MODULE#" => $logs["MODULE_NAME"],
						"#ENTITY#" => $logs["ENTITY_NAME"],
						"#OPERATION#" => $logs["OPERATION"],
						"#DATA_XML_ID#" => $logs["DATA_XML_ID"],
					)
				),
				$logs["RESULT"] ? "ok" : "fail"
			);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected static function deleteRecordWithLog(Record $record)
	{
		try
		{
			$record->delete();
			LogTable::add(array(
				"RECORD" => $record,
				"OPERATION" => "delete",
				"STEP" => static::$step,
			));
		}
		catch (\Exception $exception)
		{
			LogTable::add(array(
				"RECORD" => $record,
				"EXCEPTION" => $exception,
				"OPERATION" => "delete",
				"STEP" => static::$step,
			));
		}
	}

	protected static function deleteMarked()
	{
		static::startStep(__FUNCTION__);
		foreach (static::$deleteRecords as $record)
		{
			static::deleteRecordWithLog($record);
		}
		static::reportStepLogs();
	}

	protected static function resolveReferences()
	{
		static::startStep(__FUNCTION__);
		/**
		 * @var Record $dataRecord
		 */
		foreach (static::$recordsWithReferences as $dataRecord)
		{
			$clone = clone $dataRecord;
			$clone->removeFields();
			$clone->removeDependencies();
			foreach ($clone->getReferences() as $reference)
			{
				self::setLinkId($reference);
			}
			self::setRuntimesId($clone->getRuntimes());
			try
			{
				$id = $dataRecord->getData()->findRecord($dataRecord->getXmlId());
				if (!$id)
				{
					throw new \Exception("Record not found");
				}
				$clone->setId($id);
				$clone->update();
				LogTable::add(array(
					"RECORD" => $dataRecord,
					"OPERATION" => "update references",
					"STEP" => static::$step,
				));
			}
			catch (\Exception $exception)
			{
				LogTable::add(array(
					"RECORD" => $dataRecord,
					"EXCEPTION" => $exception,
					"OPERATION" => "update reference",
					"STEP" => static::$step,
				));
			}
		}
		static::reportStepLogs();
	}

	/**
	 * @param Runtime[] $runtimes
	 */
	protected static function setRuntimesId(array $runtimes)
	{
		foreach($runtimes as &$runtime)
		{
			foreach($runtime->getDependencies() as $link)
			{
				self::setLinkId($link);
			}
			foreach($runtime->getReferences() as $link)
			{
				self::setLinkId($link);
			}
		}
	}

	/**
	 * @param Link $link
	 */
	protected static function setLinkId($link)
	{
		if(!$link->isMultiple())
		{
			$id = $link->findId();
			if($id)
			{
				$link->setId($link->findId());
			}
		}
		else
		{
			$link->setIds($link->findIds());
		}
	}
}