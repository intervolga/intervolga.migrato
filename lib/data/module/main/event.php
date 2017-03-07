<? namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\XmlIdProvider\UfXmlIdProvider;

class Event extends BaseData
{
	const DEPENDENCY_EVENT_NAME = "EVENT_NAME";

	public function __construct()
	{
		$this->xmlIdProvider = new UfXmlIdProvider($this);
	}

	public function getFilesSubdir()
	{
		return "/eventtype/";
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$by = "ID";
		$order = "ASC";
		$getList = \CEventMessage::getList($by, $order);
		while ($message = $getList->fetch())
		{
			$record = new Record($this);
			$id = RecordId::createNumericId($message["ID"]);
			$record->setXmlId($this->getXmlIdProvider()->getXmlId($id));
			$record->setId($id);
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

			$dependency = clone $this->getDependency(static::DEPENDENCY_EVENT_NAME);
			$dependency->setValue($this->getEventTypeXmlId($message["EVENT_NAME"]));
			$record->addDependency(static::DEPENDENCY_EVENT_NAME, $dependency);

			if ($record->getDependencies())
			{
				$result[$message["ID"]] = $record;
			}
		}
		return array_values($result);
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	protected function getEventTypeXmlId($name)
	{
		static $eventTypes = array();
		if (!$eventTypes)
		{
			$eventTypes = EventType::getInstance()->getList();
		}
		foreach ($eventTypes as $eventType)
		{
			/**
			 * @var Record $eventType
			 */
			if ($eventType->getField("EVENT_NAME")->getValue() == $name)
			{
				return $eventType->getXmlId();
			}
		}

		return "";
	}

	public function getDependencies()
	{
		return array(
			static::DEPENDENCY_EVENT_NAME => new Link(
				EventType::getInstance(),
				"",
				"EVENT_NAME"
			),
		);
	}

	public function update(Record $record)
	{
		$eventMessageObject = new \CEventMessage();
		$isUpdated = $eventMessageObject->update($record->getId()->getValue(), $record->getFieldsStrings());
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($eventMessageObject->LAST_ERROR)));
		}
	}

	public function create(Record $record)
	{
		$eventMessageObject = new \CEventMessage();
		$eventMessageId = $eventMessageObject->add($record->getFieldsStrings());
		if ($eventMessageId)
		{
			return RecordId::createNumericId($eventMessageId);
		}
		else
		{
			throw new \Exception(trim(strip_tags($eventMessageObject->LAST_ERROR)));
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$eventMessageObject = new \CEventMessage();
		if (!$eventMessageObject->delete($id->getValue()))
		{
			throw new \Exception("Unknown error");
		}
	}
}