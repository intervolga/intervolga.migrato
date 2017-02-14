<?namespace Intervolga\Migrato\Tool;

class Dependency
{
	protected $targetData = null;
	protected $xmlId = "";
	protected $toCustomField = "";

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $target
	 * @param string $xmlId
	 * @param string $toCustomField
	 */
	public function __construct($target, $xmlId, $toCustomField = "")
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
}