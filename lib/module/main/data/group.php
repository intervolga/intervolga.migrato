<? namespace Intervolga\Migrato\Module\Main\Data;

use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Base\DataWithUfXmlId;
use Intervolga\Migrato\Tool\DataRecordId;

class Group extends DataWithUfXmlId
{
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
			$record->setXmlId($this->getXmlId($id));
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
	protected function update(DataRecord $record)
	{
		// TODO: Implement update() method.
	}

	/**
	 * @param DataRecord $record
	 */
	protected function create(DataRecord $record)
	{
		// TODO: Implement create() method.
	}

	/**
	 * @param $xmlId
	 */
	protected function delete($xmlId)
	{
		// TODO: Implement delete() method.
	}
}