<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class EventType extends BaseData
{
	const XML_ID_SEPARATOR = "___";

	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_EVENT_TYPE'));
		$this->setDependencies(array(
			'LANGUAGE' => new Link(Language::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CEventType::getList(
			array(),
			array(
				"LID" => "ASC",
			)
		);
		while ($type = $getList->fetch())
		{
			if (!substr_count($type["EVENT_NAME"], "FORM_STATUS_CHANGE_"))
			{
				$record = new Record($this);
				$id = $this->createId($type["ID"]);
				$record->setXmlId($this->getXmlId($id));
				$record->setId($id);
				$record->addFieldsRaw(array(
					"EVENT_NAME" => $type["EVENT_NAME"],
					"NAME" => $type["NAME"],
					"DESCRIPTION" => $type["DESCRIPTION"],
					"SORT" => $type["SORT"],
				));

				$dependency = clone $this->getDependency('LANGUAGE');
				$dependency->setValue(Language::getInstance()->getXmlId(
					Language::getInstance()->createId($type['LID'])
				));
				$record->setDependency('LANGUAGE', $dependency);

				$result[] = $record;
			}
		}
		return $result;
	}

	public function update(Record $record)
	{
		$isUpdated = \CEventType::update(array("ID" => $record->getId()->getValue()), $record->getFieldsRaw());
		if (!$isUpdated)
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	protected function createInner(Record $record)
	{
		$arFields = $record->getFieldsRaw();
		if ($lang = $record->getDependency('LANGUAGE')->getId())
		{
			$arFields['LID'] = $lang->getValue();
		}
		$eventTypeId = \CEventType::add($arFields);
		if ($eventTypeId)
		{
			return $this->createId($eventTypeId);
		}
		else
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	protected function deleteInner(RecordId $id)
	{
		if (!\CEventType::delete(array("ID" => $id->getValue())))
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	public function setXmlId($id, $xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$isUpdated = \CEventType::update(
			array("ID" => $id->getValue()),
			array("LID" => $fields[0], "EVENT_NAME" => $fields[1])
		);
		if (!$isUpdated)
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	public function getXmlId($id)
	{
		$eventType = \CEventType::GetList(array("ID" => $id->getValue()));
		if ($type = $eventType->Fetch())
		{
			return $type["LID"] . static::XML_ID_SEPARATOR . $type["EVENT_NAME"];
		}
		else
		{
			return "";
		}
	}

	public function findRecord($xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$filter = array("LID" => $fields[0], "EVENT_NAME" => $fields[1]);
		$record = \CEventType::getList($filter)->fetch();
		if ($record["ID"])
		{
			return $this->createId($record["ID"]);
		}
		return null;
	}
}