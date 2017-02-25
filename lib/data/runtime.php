<?namespace Intervolga\Migrato\Data;

class Runtime
{
	/**
	 * @var \Intervolga\Migrato\Data\BaseData
	 */
	protected $dataClass;
	/**
	 * @var \Intervolga\Migrato\Data\Values[]
	 */
	protected $fields = array();
	/**
	 * @var \Intervolga\Migrato\Data\Values[]
	 */
	protected $dependencies = array();
	/**
	 * @var \Intervolga\Migrato\Data\Values[]
	 */
	protected $references = array();

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	public function __construct(BaseData $dataClass = null)
	{
		$this->dataClass = $dataClass;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Values[] $fields
	 */
	public function setFields(array $fields)
	{
		$this->fields = $fields;
	}

	/**
	 * @param string $name
	 * @param \Intervolga\Migrato\Data\Values $values
	 */
	public function setField($name, Values $values)
	{
		$this->fields[$name] = $values;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Values[]
	 */
	public function getFields()
	{
		return $this->fields;
	}

	/**
	 * @param string $name
	 *
	 * @return \Intervolga\Migrato\Data\Values
	 */
	public function getField($name)
	{
		return $this->fields[$name];
	}

	/**
	 * @param array|\Intervolga\Migrato\Data\Values[] $dependencies
	 */
	public function setDependencies(array $dependencies)
	{
		$this->dependencies = $dependencies;
	}

	/**
	 * @param string $key
	 * @param \Intervolga\Migrato\Data\Values $dependency
	 */
	public function setDependency($key, Values $dependency)
	{
		$this->dependencies[$key] = $dependency;
	}

	/**
	 * @return array|\Intervolga\Migrato\Data\Values[]
	 */
	public function getDependencies()
	{
		return $this->dependencies;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Values[] $references
	 */
	public function setReferences(array $references)
	{
		$this->references = $references;
	}

	/**
	 * @param string $key
	 * @param \Intervolga\Migrato\Data\Values $reference
	 */
	public function setReference($key, Values $reference)
	{
		$this->references[$key] = $reference;
	}

	/**
	 * @return array|\Intervolga\Migrato\Data\Values[]
	 */
	public function getReferences()
	{
		return $this->references;
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData
	 */
	public function getData()
	{
		return $this->dataClass;
	}
}