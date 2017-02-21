<?namespace Intervolga\Migrato\Data;

class Link
{
	protected $targetData = null;
	protected $xmlId = "";
	protected $id = null;
	protected $toCustomField = "";

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $target
	 * @param string $xmlId
	 * @param string $toCustomField
	 */
	public function __construct($target, $xmlId = "", $toCustomField = "")
	{
		$this->targetData = $target;
		$this->xmlId = $xmlId;
		$this->toCustomField = $toCustomField;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $tragetData
	 */
	public function setTargetData($tragetData)
	{
		$this->targetData = $tragetData;
	}

	/**
	 * @param string $xmlId
	 */
	public function setXmlId($xmlId)
	{
		$this->xmlId = $xmlId;
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData
	 */
	public function getTargetData()
	{
		return $this->targetData;
	}

	/**
	 * @return string
	 */
	public function getXmlId()
	{
		return $this->xmlId;
	}

	/**
	 * @param string $toCustomField
	 */
	public function setToCustomField($toCustomField)
	{
		$this->toCustomField = $toCustomField;
	}

	/**
	 * @return string
	 */
	public function getToCustomField()
	{
		return $this->toCustomField;
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 */
	public function setId(RecordId $id)
	{
		$this->id = $id;
	}

	/**
	 * @return \Intervolga\Migrato\Data\RecordId
	 */
	public function getId()
	{
		return $this->id;
	}
}