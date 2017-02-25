<?namespace Intervolga\Migrato\Data;

class Record
{
	protected $xmlId = "";
	protected $id = null;
	protected $fields = array();
	protected $dependencies = array();
	protected $references = array();
	protected $data;
	/**
	 * @var Runtime[]
	 */
	protected $runtimes = array();

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

	/**
	 * @param Values[] $fields
	 */
	public function setFields(array $fields)
	{
		$this->fields = $fields;
	}

	/**
	 * @return Values[]
	 */
	public function getFields()
	{
		return $this->fields;
	}

	/**
	 * @param string $name
	 *
	 * @return Values
	 */
	public function getField($name)
	{
		return $this->fields[$name];
	}

	/**
	 * @param array|\Intervolga\Migrato\Data\Link[] $dependencies
	 */
	public function setDependencies(array $dependencies)
	{
		$this->dependencies = $dependencies;
	}

	/**
	 * @param string $key
	 * @param \Intervolga\Migrato\Data\Link $dependency
	 */
	public function addDependency($key, Link $dependency)
	{
		$this->dependencies[$key] = $dependency;
	}

	/**
	 * @return array|\Intervolga\Migrato\Data\Link[]
	 */
	public function getDependencies()
	{
		return $this->dependencies;
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
	public function addReference($key, Link $reference)
	{
		$this->references[$key] = $reference;
	}

	/**
	 * @return array|\Intervolga\Migrato\Data\Link[]
	 */
	public function getReferences()
	{
		return $this->references;
	}

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
}