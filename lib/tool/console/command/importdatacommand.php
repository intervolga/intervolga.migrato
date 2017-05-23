<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\ImportList;

Loc::loadMessages(__FILE__);

class ImportDataCommand extends BaseCommand
{
	/**
	 * @var \Intervolga\Migrato\Data\Record[]
	 */
	protected $recordsWithReferences = array();
	/**
	 * @var \Intervolga\Migrato\Tool\ImportList
	 */
	protected $list = null;
	/**
	 * @var \Intervolga\Migrato\Data\Record[]
	 */
	protected $deleteRecords = array();

	protected function configure()
	{
		$this->setName('importdata');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_DATA_DESCRIPTION'));
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
			$this->init();
			$this->importWithDependencies();
			$this->logNotResolved();
			$this->analyzeNotImported();
			$this->deleteMarked();
			$this->deleteNotImported();
			$this->resolveReferences();
		}
	}

	protected function init()
	{
		$this->list = new ImportList();
		$configDataClasses = Config::getInstance()->getDataClasses();
		$dataClasses = $this->recursiveGetDependentDataClasses($configDataClasses);
		foreach ($dataClasses as $data)
		{
			if (!in_array($data, $configDataClasses))
			{
				$this->prepareNonConfigData($data);
			}
			else
			{
				$this->prepareConfigData($data);
			}
		}

		// Добавить dependencies of runtimes
		$this->list->addCreatedRecordRuntimes();
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 */
	protected function prepareNonConfigData(BaseData $data)
	{
		$dataGetList = $data->getList();
		foreach ($dataGetList as $localDataRecord)
		{
			$this->list->addCreatedRecord($localDataRecord);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 */
	protected function prepareConfigData(BaseData $data)
	{
		$this->list->addExistingRecords($data->getList());
		$records = array();
		foreach ($this->readFromFile($data) as $record)
		{
			if ($record->getDeleteMark())
			{
				$this->deleteRecords[] = $record;
			}
			else
			{
				$records[] = $record;
			}
		}

		$this->list->addRecordsToImport($records);
	}

	/**
	 * @throws \Exception
	 */
	protected function importWithDependencies()
	{
		$this->logger->startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_ITERATE_IMPORT'));
		$configDataClasses = Config::getInstance()->getDataClasses();
		for ($i = 0; $i < count($configDataClasses); $i++)
		{
			$creatableDataRecords = $this->list->getCreatableRecords();
			if ($creatableDataRecords)
			{
				foreach ($creatableDataRecords as $dataRecord)
				{
					$this->saveDataRecord($dataRecord);
					$this->list->addCreatedRecord($dataRecord);
				}
			}
			else
			{
				break;
			}
		}

		if ($this->list->getCreatableRecords())
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.NEED_MORE_STEPS'));
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return array|\Intervolga\Migrato\Data\Record[]
	 */
	protected function readFromFile(BaseData $dataClass)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . '/';

		$data = DataFileViewXml::readFromFileSystem($path);
		foreach ($data as $i => $dataItem)
		{
			$data[$i]->setData($dataClass);
			if ($dependencies = $data[$i]->getDependencies())
			{
				$dependencies = $this->restoreDependenciesFromFile($dataClass, $dependencies);
				$data[$i]->setDependencies($dependencies);
			}
			if ($references = $data[$i]->getReferences())
			{
				$references = $this->restoreReferencesFromFile($dataClass, $references);
				$data[$i]->setReferences($references);
			}
			if ($runtimes = $data[$i]->getRuntimes())
			{
				$runtimes = $this->restoreRuntimesFromFile($dataClass, $runtimes);
				$data[$i]->setRuntimes($runtimes);
				foreach ($runtimes as $name => $runtime)
				{
					if ($runtime->getData())
					{
						foreach ($runtime->getFields() as $runtimeFieldName => $runtimeFieldValue)
						{
							$data[$i]->setReference('RUNTIME.' . $name, new Link($runtime->getData(), $runtimeFieldName));
						}
						foreach ($runtime->getReferences() as $runtimeFieldName => $runtimeFieldValue)
						{
							$data[$i]->setReference('RUNTIME.' . $name, new Link($runtime->getData(), $runtimeFieldName));
							$data[$i]->setReference('RUNTIME.' . $name . $runtimeFieldName, $runtimeFieldValue);
						}
						foreach ($runtime->getDependencies() as $runtimeFieldName => $runtimeFieldValue)
						{
							$data[$i]->setReference('RUNTIME.' . $name, new Link($runtime->getData(), $runtimeFieldName));
							$data[$i]->setDependency('RUNTIME.' . $name . $runtimeFieldName, $runtimeFieldValue);
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
	protected function restoreDependenciesFromFile(BaseData $dataClass, array $dependencies)
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
	protected function restoreReferencesFromFile(BaseData $dataClass, array $references)
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
	protected function restoreRuntimesFromFile(BaseData $dataClass, array $runtimes)
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
	protected function saveDataRecord(Record $dataRecord)
	{
		if ($dataRecord->getReferences())
		{
			$this->recordsWithReferences[] = $dataRecord;
		}
		if ($dataRecordId = $dataRecord->getData()->findRecord($dataRecord->getXmlId()))
		{
			$this->updateWithLog($dataRecordId, $dataRecord);
		}
		else
		{
			$this->createWithLog($dataRecord);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $dataRecordId
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 */
	protected function updateWithLog(RecordId $dataRecordId, Record $dataRecord)
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
			$this->logger->addDb(array(
					'RECORD' => $dataRecord,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_UPDATE'),
				),
				Logger::TYPE_OK
			);
		}
		catch (\Exception $exception)
		{
			$this->logger->addDb(
				array(
					'RECORD' => $dataRecord,
					'EXCEPTION' => $exception,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_UPDATE'),
				),
				Logger::TYPE_FAIL
			);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 */
	protected function createWithLog(Record $dataRecord)
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
			$this->logger->addDb(
				array(
					'RECORD' => $dataRecord,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_CREATE'),
				),
				Logger::TYPE_OK
			);
		}
		catch (\Exception $exception)
		{
			$this->logger->addDb(
				array(
					'RECORD' => $dataRecord,
					'EXCEPTION' => $exception,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_CREATE'),
				),
				Logger::TYPE_FAIL
			);
		}
	}

	protected function logNotResolved()
	{
		$this->logger->startStep(Loc::getMessage('INTERVOLGA_MIGRATO.NOT_RESOLVED_STEP'));
		foreach ($this->list->getNotResolvedRecords() as $notResolvedRecord)
		{
			$this->logger->addDb(
				array(
					'RECORD' => $notResolvedRecord,
					'EXCEPTION' => new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.NOT_RESOLVED')),
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RESOLVE'),
				),
				Logger::TYPE_FAIL
			);
		}
	}

	protected function analyzeNotImported()
	{
		$this->logger->startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_SHOW_NOT_IMPORTED'));
		foreach ($this->list->getRecordsToDelete() as $dataRecord)
		{
			$this->logger->addDb(
				array(
					'RECORD' => $dataRecord,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_NOT_IMPORTED'),
				),
				Logger::TYPE_OK
			);
		}
	}

	protected function deleteNotImported()
	{
		$this->logger->startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_DELETE_NOT_IMPORTED'));
		foreach ($this->list->getRecordsToDelete() as $dataRecord)
		{
			$this->deleteRecordWithLog($dataRecord);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected function deleteRecordWithLog(Record $record)
	{
		try
		{
			$record->delete();
			$this->logger->addDb(
				array(
					'RECORD' => $record,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_DELETE'),
				),
				Logger::TYPE_OK
			);
		}
		catch (\Exception $exception)
		{
			$this->logger->addDb(
				array(
					'RECORD' => $record,
					'EXCEPTION' => $exception,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_DELETE'),
				),
				Logger::TYPE_FAIL
			);
		}
	}

	protected function deleteMarked()
	{
		$this->logger->startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_DELETE_MARKED'));
		foreach ($this->deleteRecords as $record)
		{
			$this->deleteRecordWithLog($record);
		}
	}

	protected function resolveReferences()
	{
		$this->logger->startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_RESOLVE_REFERENCES'));
		/**
		 * @var Record $dataRecord
		 */
		foreach ($this->recordsWithReferences as $dataRecord)
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
					throw new \Exception('Record not found');
				}
				$clone->setId($id);
				$clone->update();
				$this->logger->addDb(
					array(
						'RECORD' => $dataRecord,
						'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_UPDATE_REFERENCES'),
					),
					Logger::TYPE_OK
				);
			}
			catch (\Exception $exception)
			{
				$this->logger->addDb(
					array(
						'RECORD' => $dataRecord,
						'EXCEPTION' => $exception,
						'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_UPDATE_REFERENCES'),
					),
					Logger::TYPE_FAIL
				);
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Runtime[] $runtimes
	 */
	protected function setRuntimesId(array $runtimes)
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
	protected function setLinkId($link)
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