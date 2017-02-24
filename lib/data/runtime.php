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
	 * @return \Intervolga\Migrato\Data\BaseData
	 */
	public function getData()
	{
		return $this->dataClass;
	}
}