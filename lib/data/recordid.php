<? namespace Intervolga\Migrato\Data;

class RecordId
{
	const TYPE_NUMERIC = 1;
	const TYPE_STRING = 2;
	const TYPE_COMPLEX = 3;

	protected $numericId = 0;
	protected $stringId = "";
	protected $complexId = array();
	protected $type = 0;

	protected function __construct()
	{
	}

	/**
	 * @param int $id
	 *
	 * @return static
	 */
	public static function createNumericId($id)
	{
		$idObject = new static();
		$idObject->numericId = intval($id);
		$idObject->type = static::TYPE_NUMERIC;

		return $idObject;
	}

	/**
	 * @param string $id
	 *
	 * @return static
	 */
	public static function createStringId($id)
	{
		$idObject = new static();
		$idObject->stringId = strval($id);
		$idObject->type = static::TYPE_STRING;

		return $idObject;
	}

	/**
	 * @param array $id
	 *
	 * @return static
	 */
	public static function createComplexId(array $id)
	{
		$idObject = new static();
		$idObject->complexId = $id;
		$idObject->type = static::TYPE_COMPLEX;

		return $idObject;
	}

	/**
	 * @return array|int|string
	 * @throws \Exception
	 */
	public function getValue()
	{
		if ($this->type == static::TYPE_NUMERIC)
		{
			return $this->numericId;
		}
		if ($this->type == static::TYPE_STRING)
		{
			return $this->stringId;
		}
		if ($this->type == static::TYPE_COMPLEX)
		{
			return $this->complexId;
		}
		throw new \Exception("Unknown RecordId type");
	}

	/**
	 * @return int
	 */
	public function getType()
	{
		return $this->type;
	}
}