<? namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\XmlIdProvider\TableXmlIdProvider;

Loc::loadMessages(__FILE__);

class Event extends BaseData
{
	const DEPENDENCY_EVENT_NAME = "EVENT_NAME";

	public function __construct()
	{
		$this->xmlIdProvider = new TableXmlIdProvider($this);
	}

	public function getFilesSubdir()
	{
		return "/eventtype/";
	}

	public function isIdExists($id)
	{
		return !!\CEventMessage::getById($id->getValue())->fetch();
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
			$id = $this->createId($message["ID"]);
			$record->setXmlId($this->getXmlId($id));
			$record->setId($id);

			$sites = array();
			$sitesGetList = \CEventMessage::getSite($message["ID"]);
			while ($site = $sitesGetList->fetch())
			{
				$sites[] = $site["SITE_ID"];
			}
			$record->addFieldsRaw(array(
				"LID" => $sites,
				"ACTIVE" => $message["ACTIVE"],
				"EMAIL_FROM" => $message["EMAIL_FROM"],
				"EMAIL_TO" => $message["EMAIL_TO"],
				"SUBJECT" => $message["SUBJECT"],
				"MESSAGE" => $message["MESSAGE"],
				"BODY_TYPE" => $message["BODY_TYPE"],
				"BCC" => $message["BCC"],
				"CC" => $message["CC"],
				"REPLY_TO" => $message["REPLY_TO"],
				"IN_REPLY_TO" => $message["IN_REPLY_TO"],
				"PRIORITY" => $message["PRIORITY"],
				"ADDITIONAL_FIELD" => serialize($message["ADDITIONAL_FIELD"]),
				"SITE_TEMPLATE_ID" => $message["SITE_TEMPLATE_ID"],
			));

			$dependency = clone $this->getDependency(static::DEPENDENCY_EVENT_NAME);
			$dependency->setValue($this->getEventTypeXmlId($message["EVENT_NAME"]));
			if (!$dependency->getValue())
			{
				throw new \Exception(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.EVENT_TYPE_NOT_FOUND',
						array(
							'#ID#' => $message["ID"],
							'#NAME#' => $message['EVENT_NAME'],
						)
					)
				);
			}
			$record->setDependency(static::DEPENDENCY_EVENT_NAME, $dependency);

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
		$fields = $record->getFieldsRaw();
		$fields["ADDITIONAL_FIELD"] = unserialize($fields["ADDITIONAL_FIELD"]);
		$eventMessageObject = new \CEventMessage();
		$isUpdated = $eventMessageObject->update($record->getId()->getValue(), $fields);
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($eventMessageObject->LAST_ERROR)));
		}
	}

	public function create(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$fields["ADDITIONAL_FIELD"] = unserialize($fields["ADDITIONAL_FIELD"]);

		if($eventType = $record->getDependency("EVENT_NAME")->getId())
		{
			$rsEventType = \CEventType::GetList(array("ID" => $eventType->getValue()));
			if($arEventType = $rsEventType->Fetch())
			{
				$fields["EVENT_NAME"] = $arEventType["EVENT_NAME"];
			}

			$eventMessageObject = new \CEventMessage();
			$eventMessageId = $eventMessageObject->add($fields);
			if ($eventMessageId)
			{
				return $this->createId($eventMessageId);
			}
			else
			{
				throw new \Exception(trim(strip_tags($eventMessageObject->LAST_ERROR)));
			}
		}
		else
			throw new \Exception("Не задано поле EVENT_TYPE для почтового шаблона с xmlId " . $record->getXmlId());
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if ($id)
		{
			$eventMessageObject = new \CEventMessage();
			if (!$eventMessageObject->delete($id->getValue()))
			{
				throw new \Exception("Unknown error");
			}
		}
	}
}