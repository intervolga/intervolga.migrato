<? namespace Intervolga\Migrato\Module\Main\Data;

use Intervolga\Migrato\Base\DataWithUfXmlId;
use Intervolga\Migrato\Tool\DataRecord;

class EventType extends DataWithUfXmlId
{
	public function getFromDatabase()
	{
		$result = array();
		$getList = \CEventType::getList();
		while ($type = $getList->fetch())
		{
			$record = new DataRecord();
			$record->setXmlId($this->getXmlId($type["ID"]));
			$record->setLocalDbId($type["ID"]);
			$record->setFields(array(
				"LID" => $type["LID"],
				"EVENT_NAME" => $type["EVENT_NAME"],
				"NAME" => $type["NAME"],
				"DESCRIPTION" => $type["DESCRIPTION"],
				"SORT" => $type["SORT"],
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