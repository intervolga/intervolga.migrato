<? namespace Intervolga\Migrato\Data;

class Value
{
	/**
	 * @var string[]
	 */
	protected $values;
	/**
	 * @var string[]
	 */
	protected $descriptions;
	/**
	 * @var bool
	 */
	protected $multiple = false;
	/**
	 * @var bool
	 */
	protected $descriptionIsSet = false;

	/**
	 * @param string[] $values
	 *
	 * @return static
	 */
	public static function createMultiple(array $values)
	{
		$object = new static();
		$object->setValues($values);

		return $object;
	}

	/**
	 * @param string $value
	 */
	public function __construct($value = "")
	{
		$this->setValue($value);
	}

	/**
	 * @param string $value
	 */
	public function setValue($value)
	{
		$this->values[0] = $value;
		$this->multiple = false;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getValue()
	{
		if ($this->multiple)
		{
			throw new \Exception("Use getValues() for getting multiple Value values");
		}
		else
		{
			return $this->values[0];
		}
	}

	/**
	 * @param array $values
	 */
	public function setValues(array $values)
	{
		$this->values = $values;
		$this->multiple = true;
	}

	/**
	 * @param string $value
	 */
	public function addValue($value)
	{
		$this->values[] = $value;
		$this->multiple = true;
	}

	/**
	 * @return \string[]
	 * @throws \Exception
	 */
	public function getValues()
	{
		if ($this->multiple)
		{
			return $this->values;
		}
		else
		{
			throw new \Exception("Use getValue() for getting single Value value");
		}
	}

	/**
	 * @param string|string[] $description
	 */
	public function setDescription($description)
	{
		$this->descriptions[0] = $description;
		$this->multiple = false;
		$this->descriptionIsSet = true;
	}


	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getDescription()
	{
		if ($this->multiple)
		{
			throw new \Exception("Use getDescriptions() for getting multiple Value descriptions");
		}
		else
		{
			return $this->descriptions[0];
		}
	}

	/**
	 * @param string[] $descriptions
	 */
	public function setDescriptions(array $descriptions)
	{
		$this->descriptions = $descriptions;
		$this->multiple = true;
		$this->descriptionIsSet = true;
	}

	/**
	 * @return \string[]
	 * @throws \Exception
	 */
	public function getDescriptions()
	{
		if ($this->multiple)
		{
			return $this->descriptions;
		}
		else
		{
			throw new \Exception("Use getDescription() for getting single Value description");
		}
	}

	/**
	 * @return bool
	 */
	public function isMultiple()
	{
		return $this->multiple;
	}

	/**
	 * @return bool
	 */
	public function isDescriptionSet()
	{
		return $this->descriptionIsSet;
	}
}