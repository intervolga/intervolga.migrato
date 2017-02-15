<? namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataRecordId;
use Intervolga\Migrato\Tool\XmlIdProviders\UfXmlIdProvider;

class Group extends BaseData
{
	public function __construct()
	{
		$this->xmlIdProvider = new UfXmlIdProvider($this);
	}

	public function getFromDatabase()
	{
		$result = array();
		$by = "ID";
		$order = "ASC";
		$getList = \CGroup::getList($by, $order);
		while ($group = $getList->fetch())
		{
			$record = new DataRecord();
			$id = DataRecordId::createNumericId($group["ID"]);
			$record->setXmlId($this->getXmlIdProvider()->getXmlId($id));

			$record->setId($id);
			$record->setFields(array(
				"ACTIVE" => $group["ACTIVE"],
				"NAME" => $group["NAME"],
				"DESCRIPTION" => $group["DESCRIPTION"],
				"STRING_ID" => $group["STRING_ID"],
			));
			$result[] = $record;
		}
		return $result;
	}

	/**
	 * @param DataRecord $record
	 */
	public function update(DataRecord $record)
	{
		// TODO: Implement update() method.
	}

	/**
	 * @param DataRecord $record
	 */
	public function create(DataRecord $record)
	{
		// TODO: Implement create() method.
	}

	/**
	 * @param $xmlId
	 */
	public function delete($xmlId)
	{
		// TODO: Implement delete() method.
	}
}