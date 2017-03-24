<? namespace Intervolga\Migrato\Data;

class Record extends BaseDataObject
{
	protected $xmlId = "";
	/**
	 * @var \Intervolga\Migrato\Data\RecordId
	 */
	protected $id;
	/**
	 * @var \Intervolga\Migrato\Data\Runtime[]
	 */
	protected $runtimes = array();
	/**
	 * @var bool
	 */
	protected $deleteMark = false;

	/**
	 * @param string $xmlId
	 */
	public function setXmlId($xmlId)
	{
		$this->xmlId = $xmlId;
	}

	/**
	 * @return string
	 */
	public function getXmlId()
	{
		return $this->xmlId;
	}

	/**
	 * @param array $fields string[] or string[][]
	 */
	public function addFieldsRaw(array $fields)
	{
		foreach ($fields as $name => $field)
		{
			$this->setFieldRaw($name, $field);
		}
	}

	/**
	 * @param string $name
	 * @param string|string[] $field
	 */
	public function setFieldRaw($name, $field)
	{
		if (is_array($field))
		{
			$this->fields[$name] = Value::createMultiple($field);
		}
		else
		{
			$this->fields[$name] = new Value($field);
		}
	}

	/**
	 * @return string[]
	 */
	public function getFieldsRaw()
	{
		$result = array();
		foreach ($this->fields as $name => $field)
		{
			if ($field->isMultiple())
			{
				$result[$name] = $field->getValues();
			}
			else
			{
				$result[$name] = $field->getValue();
			}
		}

		return $result;
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getFieldRaw($name)
	{
		$field = $this->fields[$name];
		if ($field)
		{
			return $field->getValue();
		}
		else
		{
			return "";
		}
	}

	/**
	 * @param string $name
	 *
	 * @return string[]
	 * @throws \Exception
	 */
	public function getFieldRaws($name)
	{
		$field = $this->fields[$name];
		if ($field)
		{
			return $field->getValues();
		}
		else
		{
			return array();
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 */
	public function setId(RecordId $id)
	{
		$this->id = $id;
	}

	/**
	 * @return \Intervolga\Migrato\Data\RecordId
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataObject
	 */
	public function setData(BaseData $dataObject)
	{
		$this->dataClass = $dataObject;
	}

	public function update()
	{
		$this->getData()->update($this);
	}

	/**
	 * @return \Intervolga\Migrato\Data\RecordId
	 */
	public function create()
	{
		return $this->getData()->create($this);
	}

	public function delete()
	{
		$this->getData()->delete($this->getXmlId());
	}

	/**
	 * @param string $name
	 * @param Runtime $runtime
	 */
	public function setRuntime($name, Runtime $runtime)
	{
		$this->runtimes[$name] = $runtime;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Runtime[] $runtimes
	 */
	public function setRuntimes(array $runtimes)
	{
		$this->runtimes = $runtimes;
	}

	/**
	 * @param string $name
	 *
	 * @return \Intervolga\Migrato\Data\Runtime
	 */
	public function getRuntime($name)
	{
		return $this->runtimes[$name];
	}

	/**
	 * @return \Intervolga\Migrato\Data\Runtime[]
	 */
	public function getRuntimes()
	{
		return $this->runtimes;
	}

	/**
	 * @param bool $deleteMark
	 */
	public function setDeleteMark($deleteMark)
	{
		$this->deleteMark = $deleteMark;
	}

	/**
	 * @return bool
	 */
	public function getDeleteMark()
	{
		return $this->deleteMark;
	}

	/**
	 * @return array
	 */
	public function info()
	{
		$info = array(
			"data" => $this->getData()->getModule() . ":" . $this->getData()->getEntityName(),
			"xmlId" => $this->getXmlId(),
			"id" => $this->getId() ? $this->getId()->getValue() : false,
			"deleted" => $this->getDeleteMark(),
			"fields" => $this->getFieldsRaw(),
		);
		if ($this->getDependencies())
		{
			$info["dependencies"] = $this->infoLinks($this->getDependencies());
		}
		if ($this->getReferences())
		{
			$info["references"] = $this->infoLinks($this->getReferences());
		}
		return $info;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $links
	 * @return array
	 */
	protected function infoLinks(array $links)
	{
		$info = array();
		if ($links)
		{
			foreach ($links as $name => $dependency)
			{
				if ($dependency->getTargetData())
				{
					$data = $dependency->getTargetData()->getModule() . ":" . $dependency->getTargetData()->getEntityName();
				}
				else
				{
					$data = false;
				}
				$info[$name]["data"] = $data;

				if ($dependency->isMultiple())
				{
					$info[$name] += $dependency->getValues();
				}
				else
				{
					$info[$name][] = $dependency->getValue();
				}
			}
		}

		return $info;
	}
}