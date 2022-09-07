<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\DataTree\Builder;
use Intervolga\Migrato\Tool\ImportList;
use Intervolga\Migrato\Tool\PublicCache;
use Intervolga\Migrato\Tool\Console\DiffCounter;

Loc::loadMessages(__FILE__);

class DiffDataCommand extends BaseCommand
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
		$this->setName('diffdata');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_DATA_DESCRIPTION'));
		$this->addOption('safe-delete');
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
			if (!$this->input->getOption('safe-delete'))
			{
				$this->deleteNotImported();
				echo DiffCounter::getInstance()->makeTable(0);
			}
			$this->resolveReferences();
		}
		PublicCache::getInstance()->clearTagCache();
	}

	protected function init()
	{
		$this->list = new ImportList();
		$tree = Builder::build();
		foreach ($tree->getDataClasses() as $data)
		{
			if (!$tree->findNode($data)->isRoot())
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
		$counter = DiffCounter::getInstance();
		if (Loader::includeModule($data->getModule()))
		{
			$dataGetList = $data->getList();
			foreach ($dataGetList as $localDataRecord)
			{
				$this->list->addCreatedRecord($localDataRecord);
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 */
	protected function prepareConfigData(BaseData $data)
	{
		$counter = DiffCounter::getInstance();
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
		$counter = DiffCounter::getInstance();
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
			$data[$i] = $this->afterRead($dataItem, $dataClass);
		}

		return $data;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @return \Intervolga\Migrato\Data\Record
	 */
	protected function afterRead(Record $record, BaseData $dataClass)
	{
		$record->setData($dataClass);
		if ($dependencies = $record->getDependencies())
		{
			$dependencies = $this->restoreDependenciesFromFile($dataClass, $dependencies);
			$record->setDependencies($dependencies);
		}
		if ($references = $record->getReferences())
		{
			$references = $this->restoreReferencesFromFile($dataClass, $references);
			$record->setReferences($references);
		}
		if ($runtimes = $record->getRuntimes())
		{
			$runtimes = $this->restoreRuntimesFromFile($dataClass, $runtimes);
			$record->setRuntimes($runtimes);
			foreach ($runtimes as $name => $runtime)
			{
				if ($runtime->getData())
				{
					foreach ($runtime->getFields() as $runtimeFieldName => $runtimeFieldValue)
					{
						$record->setReference('RUNTIME.' . $name, new Link($runtime->getData(), $runtimeFieldName));
					}
					foreach ($runtime->getReferences() as $runtimeFieldName => $runtimeFieldValue)
					{
						$record->setReference('RUNTIME.' . $name, new Link($runtime->getData(), $runtimeFieldName));
						$record->setReference('RUNTIME.' . $name . $runtimeFieldName, $runtimeFieldValue);
					}
					foreach ($runtime->getDependencies() as $runtimeFieldName => $runtimeFieldValue)
					{
						$record->setReference('RUNTIME.' . $name, new Link($runtime->getData(), $runtimeFieldName));
						$record->setDependency('RUNTIME.' . $name . $runtimeFieldName, $runtimeFieldValue);
					}
				}
			}
		}

		return $record;
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
			$counter = DiffCounter::getInstance();
			foreach ($dataRecord->getDependencies() as $dependency)
			{
				self::setLinkId($dependency);
			}
			self::setRuntimesId($dataRecord->getRuntimes());
			$counter->addRecord(DiffCounter::UPDATE, $dataRecord);
		}
		catch (\Exception $exception)
		{
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 */
	protected function createWithLog(Record $dataRecord)
	{
		try
		{
			$counter = DiffCounter::getInstance()->addRecord(DiffCounter::CREATE, $dataRecord);
			foreach($dataRecord->getDependencies() as $dependency)
			{
				self::setLinkId($dependency);
			}
		}
		catch (\Exception $exception)
		{
		}
	}

	protected function logNotResolved()
	{
		$this->logger->startStep(Loc::getMessage('INTERVOLGA_MIGRATO.NOT_RESOLVED_STEP'));
		foreach ($this->list->getNotResolvedRecords() as $notResolvedRecord)
		{
			$xmlIds = $this->list->getNotResolvedXmlIds($notResolvedRecord);
			$errorLines = array();
			foreach ($xmlIds as $code => $values)
			{
				$errorLines[] = Loc::getMessage('INTERVOLGA_MIGRATO.DEPENDENCY_DESCRIPTION', array('#NAME#' => $code, '#VALUES#' => implode(', ', $values)));
			}
			$this->logger->addDb(
				array(
					'RECORD' => $notResolvedRecord,
					'EXCEPTION' => new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.NOT_RESOLVED', array('#CODES#' => implode(';', $errorLines)))),
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RESOLVE'),
				),
				Logger::TYPE_FAIL
			);
		}
	}

	protected function analyzeNotImported()
	{
		$counter = DiffCounter::getInstance();
		foreach ($this->list->getRecordsToDelete() as $dataRecord)
		{
			$counter->addRecord(DiffCounter::NO_CHANGE, $dataRecord);
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
			DiffCounter::getInstance()->addRecord(DiffCounter::DELETE, $record);
		}
		catch (\Exception $exception)
		{
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
		$counter = DiffCounter::getInstance();
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
					throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_NOT_FOUND'));
				}
				$counter->addRecord(DiffCounter::UPDATE, $dataRecord);
			}
			catch (\Exception $exception)
			{
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
