<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Mail\Internal\EventTypeTable;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class Event extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_EVENT'));
		$this->setFilesSubdir('/eventtype/');
		$this->setDependencies(array(
			'EVENT_NAME' => new Link(
				EventType::getInstance(),
				'',
				'EVENT_NAME'
			),
			'SITE' => new Link(Site::getInstance()),
		));
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

			$record->addFieldsRaw(array(
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

			$dependency = clone $this->getDependency('EVENT_NAME');
			$dependency->setValue($this->getEventTypeXmlId($message["EVENT_NAME"]));
			if ($dependency->getValue())
			{
				$record->setDependency('EVENT_NAME', $dependency);
			}
			else
			{
				$record->registerValidateError(Loc::getMessage(
					'INTERVOLGA_MIGRATO.EVENT_TYPE_NOT_FOUND',
					array(
						'#ID#' => $message["ID"],
						'#NAME#' => $message['EVENT_NAME'],
					)
				));
			}

			$dependency = clone $this->getDependency('SITE');
			$sites = array();
			$sitesGetList = \CEventMessage::getSite($message['ID']);
			while ($site = $sitesGetList->fetch())
			{
				$sites[] = Site::getInstance()->getXmlId(Site::getInstance()->createId($site['SITE_ID']));
			}
			$dependency->setValues($sites);
			$record->setDependency('SITE', $dependency);

			if ($record->getDependencies())
			{
				$result[$message["ID"]] = $record;
			}
		}

		return array_values($result);
	}

	public function getXmlId($id)
	{
		$message = \CEventMessage::getByID($id->getValue())->fetch();
		$sites = array();
		$sitesGetList = \CEventMessage::getSite($message['ID']);
		while ($site = $sitesGetList->fetch())
		{
			$sites[] = Site::getInstance()->getXmlId(Site::getInstance()->createId($site['SITE_ID']));
		}
		$XmlId = $message["EVENT_NAME"] . '-' . mb_substr(md5(serialize(array(
			$message["EMAIL_FROM"],
			$message["EMAIL_TO"],
			$sites,
		))), 0, 10);

		return strtolower($XmlId);
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

	public function update(Record $record)
	{
		$fields = $this->recordToArray($record);
		$eventMessageObject = new \CEventMessage();
		$isUpdated = $eventMessageObject->update($record->getId()->getValue(), $fields);
		if (!$isUpdated)
		{
			throw new \Exception(ExceptionText::getLastError($eventMessageObject));
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function recordToArray(Record $record)
	{
		$array = $record->getFieldsRaw();
		$array['ADDITIONAL_FIELD'] = unserialize($array['ADDITIONAL_FIELD']);
		if ($eventType = $record->getDependency('EVENT_NAME')->getId())
		{
			$rsEventType = EventTypeTable::getList(array(
				'filter' => array('ID' => $eventType->getValue()),
				'select' => array('ID', 'EVENT_NAME'),
			));
			if ($eventType = $rsEventType->fetch())
			{
				$array['EVENT_NAME'] = $eventType['EVENT_NAME'];
			}
		}
		$link = $record->getDependency('SITE');
		if ($link && $link->getValues())
		{
			foreach ($link->findIds() as $siteIdObject)
			{
				$array['LID'][] = $siteIdObject->getValue();
			}
		}

		return $array;
	}

	protected function createInner(Record $record)
	{
		$fields = $this->recordToArray($record);

		$eventMessageObject = new \CEventMessage();
		$eventMessageId = $eventMessageObject->add($fields);
		if ($eventMessageId)
		{
			return $this->createId($eventMessageId);
		}
		else
		{
			throw new \Exception(ExceptionText::getLastError($eventMessageObject));
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$eventMessageObject = new \CEventMessage();
		if (!$eventMessageObject->delete($id->getValue()))
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}
}