<?namespace Intervolga\Migrato\Data;

class Values
{
	/**
	 * @var \Intervolga\Migrato\Data\Value[]
	 */
	protected $values = array();
	protected $isMultiple = false;

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
		if (count($this->values) > 1)
		{
			$this->isMultiple = true;
		}
	}

	/**
	 * @return \Intervolga\Migrato\Data\Value[]
	 */
	public function getValues()
	{
		return $this->values;
	}

	/**
	 * @param bool $isMultiple
	 */
	public function setIsMultiple($isMultiple)
	{
		if (count($this->values) > 1)
		{
			$this->isMultiple = true;
		}
		else
		{
			$this->isMultiple = $isMultiple;
		}
	}

	/**
	 * @return bool
	 */
	public function getIsMultiple()
	{
		return $this->isMultiple;
	}
}