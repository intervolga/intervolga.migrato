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
	 * @param array $tree
	 * @param string $root
	 *
	 * @return string[]
	 */
	public static function treeToList(array $tree, $root)
	{
		$list = array();
		foreach ($tree as $key => $value)
		{
			if (is_array($value))
			{
				$list = array_merge($list, static::treeToList($value, "$root.$key"));
			}
			else
			{
				$list["$root.$key"] = $value;
			}
		}
		ksort($list);
		return $list;
	}

	/**
	 * @param array $list
	 *
	 * @return array
	 */
	public static function listToTree(array $list)
	{
		$tree = array();
		foreach ($list as $key => $value)
		{
			$explode = explode(".", $key);
			if (count($explode) == 2)
			{
				$tree[$explode[0]][$explode[1]] = $value;
			}
		}
		return $tree;
	}

	/**
	 * @param array $tree
	 * @param string $root
	 *
	 * @return array
	 */
	public static function listToTreeGet(array $tree, $root)
	{
		$result = static::listToTree($tree);
		return $result[$root];
	}

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
	 * @param string[] $values
	 */
	public function addValues(array $values)
	{
		foreach ($values as $value)
		{
			$this->values[] = $value;
		}
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
	 * @param string $description
	 */
	public function addDescription($description)
	{
		$this->descriptions[] = $description;
		$this->multiple = true;
		$this->descriptionIsSet = true;
	}

	/**
	 * @param string[] $descriptions
	 */
	public function addDescriptions(array $descriptions)
	{
		foreach ($descriptions as $description)
		{
			$this->descriptions[] = $description;
		}
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