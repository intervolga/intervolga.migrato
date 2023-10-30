<? namespace Intervolga\Migrato\Tool;

use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;

class ImportList
{
	/**
	 * @var \Intervolga\Migrato\Data\Record[]
	 */
	protected $existingRecords = array();
	/**
	 * @var \Intervolga\Migrato\Data\Record[][]
	 */
	protected $recordsToImport = array();
	/**
	 * @var boolean[]
	 */
	protected $createdXmlIds = array();

	/**
	 * @param \Intervolga\Migrato\Data\Record[] $records
	 */
	public function addExistingRecords(array $records)
	{
		foreach ($records as $dataRecord)
		{
			$class = get_class($dataRecord->getData());
			$xmlId = $dataRecord->getXmlId();

			$this->existingRecords[$class][$xmlId] = $dataRecord;
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record[] $records
	 */
	public function addRecordsToImport(array $records)
	{
		foreach ($records as $dataRecord)
		{
			$class = get_class($dataRecord->getData());
			$xmlId = $dataRecord->getXmlId();

			$this->recordsToImport[$class][$xmlId] = $dataRecord;
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 */
	public function addCreatedRecord(Record $dataRecord)
	{
		$class = get_class($dataRecord->getData());
		$xmlId = $dataRecord->getXmlId();
		if ($xmlId)
		{
			$this->createdXmlIds[$class][$xmlId] = "Y";
		}
	}

	public function addCreatedRecordRuntimes()
	{
		foreach ($this->recordsToImport as $records)
		{
			foreach ($records as $record)
			{
				foreach ($record->getRuntimes() as $runtime)
				{
					foreach ($runtime->getDependencies() as $dependency)
					{
						$class = get_class($dependency->getTargetData());
						$xmlId = $dependency->getValue();
						if ($xmlId)
						{
							$this->createdXmlIds[$class][$xmlId] = "Y";
						}
					}
				}
			}
		}
	}

	/**
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getCreatableRecords()
	{
		$result = array();
		foreach ($this->recordsToImport as $dataRecords)
		{
			foreach ($dataRecords as $dataRecord)
			{
				if ($this->canCreate($dataRecord))
				{
					$result[] = $dataRecord;
				}
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 *
	 * @return bool
	 */
	protected function canCreate(Record $dataRecord)
	{
		$result = false;
		if (!$this->isCreated($dataRecord))
		{
			if ($this->isAllDependenciesResolved($dataRecord))
			{
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 *
	 * @return bool
	 */
	protected function isCreated(Record $dataRecord)
	{
		$class = get_class($dataRecord->getData());
		$xmlId = $dataRecord->getXmlId();

		return $this->createdXmlIds[$class][$xmlId] == "Y";
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 *
	 * @return bool
	 */
	protected function isAllDependenciesResolved(Record $dataRecord)
	{
		$result = true;
		foreach ($dataRecord->getDependencies() as $dependency)
		{
			if (!$this->isResolved($dependency))
			{
				$result = false;
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link $dependency
	 *
	 * @return bool
	 */
	protected function isResolved(Link $dependency)
	{
		$class = get_class($dependency->getTargetData());
		$isResolved = true;
		if ($dependency->isMultiple())
		{
			foreach ($dependency->getValues() as $xmlId)
			{
				if ($this->createdXmlIds[$class][$xmlId] != "Y")
				{
					$isResolved = false;
				}
			}
		}
		else
		{
			$xmlId = $dependency->getValue();
			$isResolved = ($this->createdXmlIds[$class][$xmlId] == "Y");
		}

		return $isResolved;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getRecordsToDelete()
	{
		$result = array();
		foreach ($this->existingRecords as $class => $xmlIds)
		{
			/**
			 * @var \Intervolga\Migrato\Data\Record $dataRecord
			 */
			foreach ($xmlIds as $xmlId => $dataRecord)
			{
				$configFilter = Config::getInstance()->getDataClassFilter($dataRecord->getData());
                if (!$configFilter || in_array($xmlId, $configFilter))
				{
					if ($this->createdXmlIds[$class][$xmlId] != "Y")
					{
						$result[] = $dataRecord;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getNotResolvedRecords()
	{
		$result = array();
		foreach ($this->recordsToImport as $dataRecords)
		{
			foreach ($dataRecords as $dataRecord)
			{
				if (!$this->isCreated($dataRecord))
				{
					$result[] = $dataRecord;
				}
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $dataRecord
	 * @return string[]
	 */
	public function getNotResolvedXmlIds(Record $dataRecord)
	{
		$resultXmlIds = array();
		foreach ($dataRecord->getDependencies() as $code => $dependency)
		{
			$class = get_class($dependency->getTargetData());
			if ($dependency->isMultiple())
			{
				foreach ($dependency->getValues() as $xmlId)
				{
					if ($this->createdXmlIds[$class][$xmlId] != "Y")
					{
						$resultXmlIds[$code][] = $xmlId;
					}
				}
			}
			else
			{
				$xmlId = $dependency->getValue();
				if ($this->createdXmlIds[$class][$xmlId] != "Y")
				{
					$resultXmlIds[$code][] = $xmlId;
				}
			}
		}

		return $resultXmlIds;
	}
}