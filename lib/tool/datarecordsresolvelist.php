<? namespace Intervolga\Migrato\Tool;

class DataRecordsResolveList
{
	protected $list = array();
	protected $created = array();

	/**
	 * @param array|DataRecord[] $data
	 */
	public function addDataRecords(array $data)
	{
		foreach ($data as $dataRecord)
		{
			$this->list[get_class($dataRecord->getData())][$dataRecord->getXmlId()] = $dataRecord;
		}
	}

	/**
	 * @return array|DataRecord[]
	 */
	public function getCreatableDataRecords()
	{
		$result = array();
		foreach ($this->list as $dataRecords)
		{
			/**
			 * @var DataRecord $dataRecord
			 */
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
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 *
	 * @return bool
	 */
	protected function canCreate(DataRecord $dataRecord)
	{
		$result = false;
		if (!$this->isCreated($dataRecord))
		{
			if (!$dataRecord->getDependencies())
			{
				$result = true;
			}
			elseif ($this->isAllDependenciesResolved($dataRecord))
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
		$result = false;
		foreach ($dataRecord->getDependencies() as $dependency)
		{
			$isAllResolved = true;
			if (!$this->isResolved($dependency))
			{
				$isAllResolved = false;
			}
			if ($isAllResolved)
			{
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Tool\DataLink $dependency
	 *
	 * @return bool
	 */
	public function isResolved(DataLink $dependency)
	{
		$class = get_class($dependency->getTargetData());
		$xmlId = $dependency->getXmlId();

		return ($this->created[$class][$xmlId] == "Y");
	}

	/**
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 */
	public function setCreated(DataRecord $dataRecord)
	{
		$class = get_class($dataRecord->getData());
		$xmlId = $dataRecord->getXmlId();

		$this->created[$class][$xmlId] = "Y";
	}

	/**
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 *
	 * @return bool
	 */
	public function isCreated(DataRecord $dataRecord)
	{
		$class = get_class($dataRecord->getData());
		$xmlId = $dataRecord->getXmlId();

		return ($this->created[$class][$xmlId] == "Y");
	}
}