<?namespace Intervolga\Migrato\Tool;

use Intervolga\Migrato\Data\BaseData;

class XmlIdValidateError
{
	const TYPE_REPEAT = 1;
	const TYPE_EMPTY = 2;
	const TYPE_INVALID = 3;

	protected $dataClass;
	protected $type;
	protected $id;
	protected $xmlId;

	/**
	 * @param BaseData $dataClass
	 * @param int $type
	 * @param DataRecordId $id
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
	 * @return DataRecordId
	 */
	public function getId()
	{
		return $this->id;
	}
}