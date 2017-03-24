<? namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\XmlIdProvider\EventTypeXmlIdProvider;

class EventType extends BaseData
{
	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CEventType::getList();
		while ($type = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($type["ID"]);
			$record->setXmlId($this->getXmlId($id));
			$record->setId($id);
			$record->addFieldsRaw(array(
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

	public function update(Record $record)
	{
		$isUpdated = \CEventType::update(array("ID" => $record->getId()->getValue()), $record->getFieldsRaw());
		if (!$isUpdated)
		{
			global $APPLICATION;
			if($exception = $APPLICATION->GetException())
			{
				throw new \Exception(trim(strip_tags($exception->GetString())));
			}
		}
	}

	public function create(Record $record)
	{
		$eventTypeId = \CEventType::add($record->getFieldsRaw());
		if ($eventTypeId)
		{
			return $this->createId($eventTypeId);
		}
		else
		{
			global $APPLICATION;
			throw new \Exception(trim(strip_tags($APPLICATION->getException()->getString())));
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if (!\CEventType::delete(array("ID" => $id->getValue())))
		{
			throw new \Exception("Unknown error");
		}
	}

	public function setXmlId($id, $xmlId)
	{
		$fields = explode("___", $xmlId);
		$isUpdated = \CEventType::update(
			array("ID" => $id->getValue()),
			array("LID" => $fields[0], "EVENT_NAME" => $fields[1])
		);
		if (!$isUpdated)
		{
			global $APPLICATION;
			throw new \Exception(trim(strip_tags($APPLICATION->getException()->getString())));
		}
	}

	public function getXmlId($id)
	{
		$eventType = \CEventType::GetList(array("ID" => $id->getValue()));
		if ($type = $eventType->Fetch())
		{
			return $type["LID"] . "___" .  $type["EVENT_NAME"];
		}
		else
		{
			return "";
		}
	}
}