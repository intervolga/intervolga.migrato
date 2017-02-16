<?namespace Intervolga\Migrato\Tool;

use Intervolga\Migrato\Data\BaseData;

class DataRecord
{
	protected $xmlId = "";
	protected $id = null;
	protected $fields = array();
	protected $dependencies = array();
	protected $references = array();
	protected $data = null;

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
	 * @param array $fields
	 */
	public function setFields(array $fields)
	{
		$this->fields = $fields;
	}

	/**
	 * @return array
	 */
	public function getFields()
	{
		return $this->fields;
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function getField($name)
	{
		return $this->fields[$name];
	}

	/**
	 * @param array|\Intervolga\Migrato\Tool\DataLink[] $dependencies
	 */
	public function setDependencies(array $dependencies)
	{
		$this->dependencies = $dependencies;
	}

	/**
	 * @param string $key
	 * @param \Intervolga\Migrato\Tool\DataLink $dependency
	 */
	public function addDependency($key, DataLink $dependency)
	{
		$this->dependencies[$key] = $dependency;
	}

	/**
	 * @return array|\Intervolga\Migrato\Tool\DataLink[]
	 */
	public function getDependencies()
	{
		return $this->dependencies;
	}

	/**
	 * @param string $key
	 * @param \Intervolga\Migrato\Tool\DataLink $reference
	 */
	public function addReference($key, DataLink $reference)
	{
		$this->references[$key] = $reference;
	}

	/**
	 * @return array|\Intervolga\Migrato\Tool\DataLink[]
	 */
	public function getReferences()
	{
		return $this->references;
	}

	public function setId(DataRecordId $id)
	{
		$this->id = $id;
	}

	/**
	 * @return DataRecordId
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
}