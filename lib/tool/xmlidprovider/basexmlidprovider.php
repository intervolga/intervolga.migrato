<?namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Bitrix\Main\NotImplementedException;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\RecordId;

abstract class BaseXmlIdProvider
{
	protected $dataClass = null;

	public static function deleteXmlIdFields()
	{
		throw new NotImplementedException(__FUNCTION__ . " is not yet implemented");
	}

	public function __construct(BaseData $dataClass)
	{
		$this->dataClass = $dataClass;
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 * @param string $xmlId
	 */
	abstract public function setXmlId($id, $xmlId);

	/**
	 * @param RecordId $id
	 *
	 * @return string
	 */
	abstract public function getXmlId($id);

	/**
	 * @return bool
	 */
	public function isXmlIdFieldExists()
	{
		return true;
	}

	public function createXmlIdField()
	{
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 *
	 * @return bool
	 */
	public function generateXmlId($id)
	{
		return $this->setXmlId($id, $this->makeXmlId());
	}

	/**
	 * @return string
	 */
	protected function makeXmlId()
	{
		$prefix = $this->dataClass->getModule() . "-" . $this->dataClass->getEntityName() . "-";
		$prefix = str_replace(
			array(
				"iblock",
				"element",
				"section",
				"highloadblock",
				"field",
				"property",
				"group",
				"event",
			),
			array(
				"ibl",
				"el",
				"sect",
				"hlb",
				"fld",
				"prop",
				"grp",
				"evt",
			),
			$prefix
		);
		$xmlid = strrev(uniqid("", true));
		$xmlid = str_replace(".", "", $xmlid);
		$xmlid = implode("-", str_split($xmlid, 6));
		return $prefix.$xmlid;
	}
}