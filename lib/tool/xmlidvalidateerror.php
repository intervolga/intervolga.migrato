<?namespace Intervolga\Migrato\Tool;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\RecordId;

class XmlIdValidateError
{
	const TYPE_REPEAT = 1;
	const TYPE_EMPTY = 2;
	const TYPE_INVALID = 3;
	const TYPE_SIMPLE = 4;

	protected $dataClass;
	protected $type;
	protected $id;
	protected $xmlId;

	/**
	 * @param BaseData $dataClass
	 * @param int $type
	 * @param RecordId $id
	 * @param string $xmlId
	 */
	public function __construct(BaseData $dataClass, $type, $id, $xmlId)
	{
		$this->dataClass = $dataClass;
		$this->type = $type;
		$this->id = $id;
		$this->xmlId = $xmlId;
	}

	/**
	 * @return BaseData
	 */
	public function getDataClass()
	{
		return $this->dataClass;
	}

	/**
	 * @return int
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getXmlId()
	{
		return $this->xmlId;
	}

	/**
	 * @return RecordId
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function toString()
	{
		$string = "";
		$name = "Validate error at " . $this->getDataClass()->getModule() . "/" . $this->getDataClass()->getEntityName();
		$xmlId = $this->getXmlId();
		if ($this->getType() == static::TYPE_EMPTY)
		{
			$string = $name . " " . $this->getId()->getValue() . " empty xmlid";
		}
		if ($this->getType() == static::TYPE_REPEAT)
		{
			$string = $name . " " . $xmlId . " repeat error";
		}
		if ($this->getType() == static::TYPE_INVALID)
		{
			$string = $name . " " . $xmlId . " invalid";
		}
		if ($this->getType() == static::TYPE_SIMPLE)
		{
			$string = $name . " " . $xmlId . " is too simple";
		}

		return $string;
	}
}