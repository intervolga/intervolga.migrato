<?namespace Intervolga\Migrato\Tool;

use Intervolga\Migrato\Base\Data;

class Dependency
{
	protected $targetData = null;
	protected $xmlId = "";
	protected $toCustomField = "";

	/**
	 * @param \Intervolga\Migrato\Base\Data $target
	 * @param string $xmlId
	 * @param string $toCustomField
	 */
	public function __construct(Data $target, $xmlId, $toCustomField = "")
	{
		$this->targetData = $target;
		$this->xmlId = $xmlId;
		$this->toCustomField = $toCustomField;
	}

	/**
	 * @return \Intervolga\Migrato\Base\Data
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
	 * @return string
	 */
	public function getToCustomField()
	{
		return $this->toCustomField;
	}
}