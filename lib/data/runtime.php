<?namespace Intervolga\Migrato\Data;

class Runtime
{
	protected $fields = array();

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
}