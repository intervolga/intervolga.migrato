<?namespace Intervolga\Migrato\Data;

abstract class BaseDataObject
{
	/**
	 * @var \Intervolga\Migrato\Data\BaseData
	 */
	protected $dataClass;
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
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 */
	public function __construct(BaseData $dataClass = null)
	{
		$this->dataClass = $dataClass;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Value[] $fields
	 */
	public function setFields(array $fields)
	{
		$this->fields = $fields;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Value[] $fields
	 */
	public function addFields(array $fields)
	{
		$this->fields = array_merge($this->fields, $fields);
	}

	/**
	 * @param string $name
	 * @param \Intervolga\Migrato\Data\Value $value
	 */
	public function setField($name, Value $value)
	{
		$this->fields[$name] = $value;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Value[]
	 */
	public function getFields()
	{
		return $this->fields;
	}

	/**
	 * @param string $name
	 *
	 * @return \Intervolga\Migrato\Data\Value
	 */
	public function getField($name)
	{
		return $this->fields[$name];
	}

	public function removeFields()
	{
		$this->fields = array();
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $dependencies
	 */
	public function setDependencies(array $dependencies)
	{
		$this->dependencies = $dependencies;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $dependencies
	 */
	public function addDependencies(array $dependencies)
	{
		$this->dependencies = array_merge($this->dependencies, $dependencies);
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

	public function removeDependencies()
	{
		$this->dependencies = array();
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $references
	 */
	public function setReferences(array $references)
	{
		$this->references = $references;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $references
	 */
	public function addReferences(array $references)
	{
		$this->references = array_merge($this->references, $references);
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

	public function removeReferences()
	{
		$this->references = array();
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData
	 */
	public function getData()
	{
		return $this->dataClass;
	}
}