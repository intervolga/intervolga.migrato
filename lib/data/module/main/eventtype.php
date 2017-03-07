<? namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\UfXmlIdProvider;

class EventType extends BaseData
{
	public function __construct()
	{
		$this->xmlIdProvider = new UfXmlIdProvider($this);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CEventType::getList();
		while ($type = $getList->fetch())
		{
			$record = new Record($this);
			$id = RecordId::createNumericId($type["ID"]);
			$record->setXmlId($this->getXmlIdProvider()->getXmlId($id));
			$record->setId($id);
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

	public function update(Record $record)
	{
		$isUpdated = \CEventType::update(array("ID" => $record->getId()->getValue()), $record->getFieldsStrings());
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
		$eventTypeId = \CEventType::add($record->getFieldsStrings());
		if ($eventTypeId)
		{
			return RecordId::createNumericId($eventTypeId);
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
}