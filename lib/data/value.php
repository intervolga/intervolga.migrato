<?namespace Intervolga\Migrato\Data;

class Value
{
	/**
	 * @var string
	 */
	protected $value;
	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @param string $value
	 */
	public function __construct($value = "")
	{
		$this->value = $value;
	}

	/**
	 * @return string
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * @param string $value
	 */
	public function setValue($value)
	{
		$this->value = $value;
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * @param string $description
	 */
	public function setDescription($description)
	{
		$this->description = $description;
	}
}