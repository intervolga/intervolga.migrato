<? namespace Intervolga\Migrato\Tool;

class ImportList
{
	/**
	 * @var \Intervolga\Migrato\Tool\DataRecord[]
	 */
	protected $existingRecords = array();
	/**
	 * @var \Intervolga\Migrato\Tool\DataRecord[]
	 */
	protected $recordsToImport = array();
	/**
	 * @var boolean[]
	 */
	protected $createdXmlIds = array();

	/**
	 * @param \Intervolga\Migrato\Tool\DataRecord[] $records
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
	 * @param \Intervolga\Migrato\Tool\DataRecord[] $records
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
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 */
	public function addCreatedRecord(DataRecord $dataRecord)
	{
		$class = get_class($dataRecord->getData());
		$xmlId = $dataRecord->getXmlId();

		$this->createdXmlIds[$class][$xmlId] = true;
	}

	/**
	 * @return \Intervolga\Migrato\Tool\DataRecord[]
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
	 * @return \Intervolga\Migrato\Tool\DataRecord[]
	 */
	public function getRecordsToDelete()
	{
		$result = array();
		foreach ($this->existingRecords as $class => $xmlIds)
		{
			foreach ($xmlIds as $xmlId => $dataRecord)
			{
				if (!$this->createdXmlIds[$class][$xmlId])
				{
					$result[] = $dataRecord;
				}
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 *
	 * @return bool
	 */
	protected function canCreate(DataRecord $dataRecord)
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
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 *
	 * @return bool
	 */
	protected function isAllDependenciesResolved(DataRecord $dataRecord)
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
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 *
	 * @return bool
	 */
	protected function isCreated(DataRecord $dataRecord)
	{
		$class = get_class($dataRecord->getData());
		$xmlId = $dataRecord->getXmlId();

		return !!$this->createdXmlIds[$class][$xmlId];
	}

	/**
	 * @param \Intervolga\Migrato\Tool\DataLink $dependency
	 *
	 * @return bool
	 */
	protected function isResolved(DataLink $dependency)
	{
		$class = get_class($dependency->getTargetData());
		$xmlId = $dependency->getXmlId();

		return ($this->createdXmlIds[$class][$xmlId] == "Y");
	}
}