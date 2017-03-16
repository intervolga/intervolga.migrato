<? namespace Intervolga\Migrato\Data;

class Record
{
	protected $xmlId = "";
	/**
	 * @var \Intervolga\Migrato\Data\RecordId
	 */
	protected $id;
	/**
	 * @var \Intervolga\Migrato\Data\Value[]
	 */
	protected $fields = array();
	/**
	 * @var \Intervolga\Migrato\Data\Link[]
	 */
	protected $dependencies = array();
	/**
	 * @var \Intervolga\Migrato\Data\Link[]
	 */
	protected $references = array();
	/**
	 * @var \Intervolga\Migrato\Data\BaseData
	 */
	protected $data;
	/**
	 * @var Runtime[]
	 */
	protected $runtimes = array();
	/**
	 * @var bool
	 */
	protected $deleteMark = false;

	/**
	 * @param BaseData $data
	 */
	public function __construct(BaseData $data = null)
	{
		$this->data = $data;
	}

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

	public function removeFields()
	{
		$this->fields = array();
	}

	/**
	 * @param array $fields string[] or string[][]
	 */
	public function addFields(array $fields)
	{
		foreach ($fields as $name => $field)
		{
			$this->setField($name, $field);
		}
	}

	/**
	 * @param string $name
	 * @param string|string[] $field
	 */
	public function setField($name, $field)
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
	 * @param \Intervolga\Migrato\Data\Value[] $values
	 */
	public function addFieldsValues(array $values)
	{
		foreach ($values as $name => $value)
		{
			$this->setFieldValue($name, $value);
		}
	}

	/**
	 * @param string $name
	 * @param \Intervolga\Migrato\Data\Value $value
	 */
	public function setFieldValue($name, Value $value)
	{
		$this->fields[$name] = $value;
	}

	/**
	 * @return Value[]
	 */
	public function getFields()
	{
		return $this->fields;
	}

	/**
	 * @return string[]
	 */
	public function getFieldsStrings()
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
	 * @return Value
	 */
	public function getField($name)
	{
		return $this->fields[$name];
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getFieldValue($name)
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
	public function getFieldValues($name)
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

	public function removeDependencies()
	{
		$this->dependencies = array();
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $dependencies
	 */
	public function setDependencies(array $dependencies)
	{
		$this->dependencies = $dependencies;
	}

	/**
	 * @param string $key
	 * @param \Intervolga\Migrato\Data\Link $dependency
	 */
	public function setDependency($key, Link $dependency)
	{
		$this->dependencies[$key] = $dependency;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Link[]
	 */
	public function getDependencies()
	{
		return $this->dependencies;
	}

	/**
	 * @param $key string
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 */
	public function getDependency($key)
	{
		$dependencies = $this->getDependencies();
		return $dependencies[$key];
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $references
	 */
	public function setReferences(array $references)
	{
		$this->references = $references;
	}

	/**
	 * @param string $key
	 * @param \Intervolga\Migrato\Data\Link $reference
	 */
	public function setReference($key, Link $reference)
	{
		$this->references[$key] = $reference;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Link[]
	 */
	public function getReferences()
	{
		return $this->references;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Link
	 */
	public function getReference($key)
	{
		$references = $this->getReferences();
		return $references[$key];
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 */
	public function setId(RecordId $id)
	{
		$this->id = $id;
	}

	/**
	 * @return RecordId
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param BaseData $dataObject
	 */
	public function setData(BaseData $dataObject)
	{
		$this->data = $dataObject;
	}

	/**
	 * @return BaseData
	 */
	public function getData()
	{
		return $this->data;
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
	 * @return Runtime
	 */
	public function getRuntime($name)
	{
		return $this->runtimes[$name];
	}

	/**
	 * @return Runtime[]
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
			"fields" => $this->getFieldsStrings(),
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
	 * @param Link[] $links
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