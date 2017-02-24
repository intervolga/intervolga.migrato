<?namespace Intervolga\Migrato\Data;

class Values
{
	/**
	 * @var \Intervolga\Migrato\Data\Value[]
	 */
	protected $values = array();

	/**
	 * @param \Intervolga\Migrato\Data\Value $value
	 */
	public function __construct(Value $value = null)
	{
		if ($value)
		{
			$this->addValue($value);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Value $value
	 */
	protected function addValue(Value $value)
	{
		$this->values[] = $value;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Value[]
	 */
	public function getValues()
	{
		return $this->values;
	}
}