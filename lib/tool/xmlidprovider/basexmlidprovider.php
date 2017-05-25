<?namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Bitrix\Main\NotImplementedException;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\RecordId;

abstract class BaseXmlIdProvider
{
	protected $dataClass = null;

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
	 * @return string
	 */
	public function generateXmlId($id)
	{
		$xmlId = $this->makeXmlId();
		$this->setXmlId($id, $this->makeXmlId());
		return $xmlId;
	}

	/**
	 * @return string
	 */
	public function makeXmlId()
	{
		return static::makeDefaultXmlId($this->dataClass);
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return string
	 */
	public static function makeDefaultXmlId(BaseData $dataClass)
	{
		$prefix = $dataClass->getModule() . "-" . $dataClass->getEntityName() . "-";
		$replace = array(
			"iblock" => "ibl",
			"element" => "el",
			"section" => "sect",
			"highloadblock" => "hlb",
			"field" => "fld",
			"property" => "prop",
			"group" => "grp",
			"event" => "evt",
			"permission" => "perm",
		);
		$prefix = str_replace(
			array_keys($replace),
			array_values($replace),
			$prefix
		);
		$xmlid = strrev(uniqid("", true));
		return static::formatXmlId($xmlid, $prefix);
	}

	/**
	 * @param string $xmlid
	 * @param string $prefix
	 *
	 * @return string
	 */
	public static function formatXmlId($xmlid, $prefix = "")
	{
		$xmlid = str_replace(".", "", $xmlid);
		$xmlid = implode("-", str_split($xmlid, 6));

		return strtolower($prefix.$xmlid);
	}

	/**
	 * @param string $xmlId
	 *
	 * @return \Intervolga\Migrato\Data\RecordId|null
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	public function findRecord($xmlId)
	{
		throw new NotImplementedException("Not implemented yet");
	}
}