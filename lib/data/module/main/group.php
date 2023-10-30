<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Data\Value;

Loc::loadMessages(__FILE__);

class Group extends BaseData
{
	const GROUP_ADMINS = 1;
	const GROUP_ALL_USERS = 2;

	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_GROUP'));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$by = "ID";
		$order = "ASC";
		$getList = \CGroup::getList($by, $order);
		while ($group = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($group["ID"]);
			if (!$filter || in_array($group["STRING_ID"], $filter))
			{
				$record->setId($id);
				$record->setXmlId($group["STRING_ID"]);
				$record->addFieldsRaw(array(
					"ACTIVE" => $group["ACTIVE"],
					"NAME" => $group["NAME"],
					"C_SORT" => $group["C_SORT"],
					"DESCRIPTION" => $group["DESCRIPTION"],
					"STRING_ID" => $group["STRING_ID"],
				));

				$this->addSecurityPolicySettings($record);

				$result[] = $record;
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected function addSecurityPolicySettings(Record $record)
	{
		$getElement = \CGroup::GetByID($record->getId()->getValue(), "N");
		$groupElement = $getElement->Fetch();
        $arSecurityPolicy = unserialize($groupElement['SECURITY_POLICY'] ?: []);

		if ($arSecurityPolicy)
		{
			$arSecurityPolicyValues = Value::treeToList($arSecurityPolicy, "SECURITY_POLICY");
			$record->addFieldsRaw($arSecurityPolicyValues);
		}
	}

	public function update(Record $record)
	{
		$groupObject = new \CGroup();
		$fields = $record->getFieldsRaw(array("SECURITY_POLICY"));
        $fields["SECURITY_POLICY"] = serialize($fields["SECURITY_POLICY"] ?: []);
		$isUpdated = $groupObject->update($record->getId()->getValue(), $fields);
		if (!$isUpdated)
		{
			throw new \Exception(ExceptionText::getLastError($groupObject));
		}
	}

	protected function createInner(Record $record)
	{
		$groupObject = new \CGroup();
		$groupId = $groupObject->add($record->getFieldsRaw());
		if ($groupId)
		{
			return $this->createId($groupId);
		}
		else
		{
			throw new \Exception(ExceptionText::getLastError($groupObject));
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$groupObject = new \CGroup();
		if (in_array($id->getValue(), array(static::GROUP_ADMINS, static::GROUP_ALL_USERS)))
		{
			$group = Loc::getMessage("INTERVOLGA_MIGRATO.SYSTEM_GROUP_" . $id->getValue());
			$message = Loc::getMessage("INTERVOLGA_MIGRATO.DELETE_SYSTEM_GROUP_ERROR", array(
				"#GROUP#" => $group,
			));
			throw new \Exception($message);
		}
		if (!$groupObject->delete($id->getValue()))
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	public function setXmlId($id, $xmlId)
	{
		$groupObject = new \CGroup();
		$isUpdated = $groupObject->update($id->getValue(), array("STRING_ID" => $xmlId));
		if (!$isUpdated)
		{
			throw new \Exception(ExceptionText::getLastError($groupObject));
		}
	}

	public function getXmlId($id)
	{
		$element = \CGroup::getByID($id->getValue());
		if ($element = $element->fetch())
		{
			return $element["STRING_ID"];
		}
		else
		{
			return "";
		}
	}

	public function generateXmlId($id)
	{
		if ($id->getValue() == static::GROUP_ADMINS)
		{
			$xmlId = "ADMINS";
		}
		elseif ($id->getValue() == static::GROUP_ALL_USERS)
		{
			$xmlId = "ALL-USERS";
		}
		else
		{
			$xmlId = parent::makeXmlId();
		}
		$this->setXmlId($id, $xmlId);
		return $xmlId;
	}
}