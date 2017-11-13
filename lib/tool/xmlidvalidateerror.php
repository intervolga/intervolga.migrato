<?namespace Intervolga\Migrato\Tool;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\RecordId;

Loc::loadMessages(__FILE__);

class XmlIdValidateError
{
	const TYPE_REPEAT = 1;
	const TYPE_EMPTY = 2;
	const TYPE_INVALID = 3;
	const TYPE_SIMPLE = 4;
	const TYPE_INVALID_EXT = 5;

	protected $dataClass;
	protected $type;
	protected $id;
	protected $xmlId;
	protected $comment;

	/**
	 * @param string $type
	 * @param bool $isVirtualXmlId
	 *
	 * @return string
	 */
	public static function typeToString($type, $isVirtualXmlId = false)
	{
		if ($type == static::TYPE_EMPTY)
		{
			if ($isVirtualXmlId)
			{
				return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE_ERROR_TYPE_EMPTY_VIRTUAL");
			}
			else
			{
				return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE_ERROR_TYPE_EMPTY");
			}
		}
		if ($type == static::TYPE_REPEAT)
		{
			if ($isVirtualXmlId)
			{
				return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE_ERROR_TYPE_REPEAT_VIRTUAL");
			}
			else
			{
				return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE_ERROR_TYPE_REPEAT");
			}
		}
		if ($type == static::TYPE_INVALID)
		{
			return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE_ERROR_TYPE_INVALID");
		}
		if ($type == static::TYPE_INVALID_EXT)
		{
			return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE_ERROR_TYPE_INVALID_EXT");
		}
		if ($type == static::TYPE_SIMPLE)
		{
			return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE_ERROR_TYPE_SIMPLE");
		}
		return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE_ERROR_TYPE_UNKNOWN");
	}

	/**
	 * @param BaseData $dataClass
	 * @param int $type
	 * @param RecordId $id
	 * @param string $xmlId
	 * @param string $comment
	 */
	public function __construct(BaseData $dataClass, $type, $id, $xmlId, $comment = '')
	{
		$this->dataClass = $dataClass;
		$this->type = $type;
		$this->id = $id;
		$this->xmlId = $xmlId;
		$this->comment = $comment;
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
	 * @param string $xmlId
	 */
	public function setXmlId($xmlId)
	{
		$this->xmlId = $xmlId;
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
			$string = $name . " " . $this->getId()->getValue() . " " . static::typeToString($this->getType(), $this->getDataClass()->isVirtualXmlId());
		}
		if ($this->getType() == static::TYPE_REPEAT)
		{
			$string = $name . " " . $xmlId . " " . static::typeToString($this->getType(), $this->getDataClass()->isVirtualXmlId());
		}
		if ($this->getType() == static::TYPE_INVALID)
		{
			$string = $name . " " . $xmlId . " " . static::typeToString($this->getType(), $this->getDataClass()->isVirtualXmlId());
		}
		if ($this->getType() == static::TYPE_INVALID_EXT)
		{
			if ($this->comment)
			{
				$string = $this->comment;
			}
			else
			{
				$string = $name . " " . $xmlId . " " . static::typeToString($this->getType(), $this->getDataClass()->isVirtualXmlId());
			}
		}
		if ($this->getType() == static::TYPE_SIMPLE)
		{
			$string = $name . " " . $xmlId . " " . static::typeToString($this->getType(), $this->getDataClass()->isVirtualXmlId());
		}

		return $string;
	}
}