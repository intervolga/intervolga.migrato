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
	 * @var bool
	 */
	protected $isReferenceUpdate = false;
	/**
	 * @var string[]
	 */
	protected $validateCustomErrors = array();

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
	 * @return string
	 */
	public function getValidationXmlId()
	{
		return $this->getData()->getValidationXmlId($this->xmlId);
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
	 * @param string[] $treeRoots
	 *
	 * @return \string[]
	 * @throws \Exception
	 */
	public function getFieldsRaw($treeRoots = array())
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
		if ($treeRoots)
		{
			$tree = Value::listToTree($result);
			foreach ($tree as $root => $treeFields)
			{
				if (in_array($root, $treeRoots))
				{
					foreach ($result as $key => $value)
					{
						if (strpos($key, $root . ".") === 0)
						{
							unset($result[$key]);
						}
					}
					$result[$root] = $treeFields;
				}
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

	public function getFieldsFromDB()
	{
		return $this->getData()->getFieldsFromDB($this);
	}

	public function update()
	{
		$this->getData()->update($this);
	}

	public function updateReferences()
	{
		$this->isReferenceUpdate = true;
		$this->getData()->update($this);
		$this->isReferenceUpdate = false;
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
		$this->getData()->delete($this->getXmlId(), $this->getId());
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

	/**
	 * @return bool
	 */
	public function isReferenceUpdate()
	{
		return $this->isReferenceUpdate;
	}

	/**
	 * @param string $error
	 */
	public function registerValidateError($error)
	{
		$this->validateCustomErrors[] = $error;
	}

	/**
	 * @return string[]
	 */
	public function getValidateErrors()
	{
		return $this->validateCustomErrors;
	}
}
