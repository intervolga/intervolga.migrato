<?namespace Intervolga\Migrato\Tool;

class XmlIdValidateError
{
	const TYPE_REPEAT = 1;
	const TYPE_EMPTY = 2;
	const TYPE_INVALID = 3;

	protected $type;
	protected $id;
	protected $xmlId;

	/**
	 * @param int $type
	 * @param DataRecordId $id
	 * @param string $xmlId
	 */
	public function __construct($type, $id, $xmlId)
	{
		$this->type = $type;
		$this->id = $id;
		$this->xmlId = $xmlId;
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