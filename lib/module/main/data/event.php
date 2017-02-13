<? namespace Intervolga\Migrato\Module\Main\Data;

use Bitrix\Main\Mail\Internal\EventTypeTable;
use Intervolga\Migrato\Base\DataWithUfXmlId;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\Dependency;

class Event extends DataWithUfXmlId
{
	public function getFromDatabase()
	{
		$result = array();
		$by = "ID";
		$order = "ASC";
		$getList = \CEventMessage::getList($by, $order);
		while ($message = $getList->fetch())
		{
			$record = new DataRecord();
			$record->setXmlId($this->getXmlId($message["ID"]));
			$record->setLocalDbId($message["ID"]);
			$record->setFields(array(
				"LID" => $message["LID"],
				"ACTIVE" => $message["ACTIVE"],
				"EMAIL_FROM" => $message["EMAIL_FROM"],
				"EMAIL_TO" => $message["EMAIL_TO"],
				"SUBJECT" => $message["SUBJECT"],
				"MESSAGE" => $message["MESSAGE"],
				"BODY_TYPE" => $message["BODY_TYPE"],
				"SITE_TEMPLATE_ID" => $message["SITE_TEMPLATE_ID"],
			));

			// TODO getlist в цикле
			$eventTypeGetList = EventTypeTable::getList(array(
				"filter" => array(
					"=EVENT_NAME" => $message["EVENT_NAME"],
				),
				"select" => array("ID"),
			));
			while ($type = $eventTypeGetList->fetch())
			{
				$dependency = new Dependency(
					EventType::getInstance(),
					EventType::getInstance()->getXmlId($type["ID"]),
					"EVENT_NAME"
				);
				$record->addDependency("EVENT_NAME", $dependency);
			}
			if ($record->getDependencies())
			{
				$result[$message["ID"]] = $record;
			}
		}
		return array_values($result);
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