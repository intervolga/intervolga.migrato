<? namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;

Loc::loadMessages(__FILE__);

class Group extends BaseData
{
	const GROUP_ADMINS = 1;
	const GROUP_ALL_USERS = 2;

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
					"DESCRIPTION" => $group["DESCRIPTION"],
					"STRING_ID" => $group["STRING_ID"],
				));
				$result[] = $record;
			}
		}

		return $result;
	}

	public function update(Record $record)
	{
		$groupObject = new \CGroup();
		$isUpdated = $groupObject->update($record->getId()->getValue(), $record->getFieldsRaw());
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($groupObject->LAST_ERROR)));
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
			throw new \Exception(trim(strip_tags($groupObject->LAST_ERROR)));
		}
	}

	protected function deleteInner($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$groupObject = new \CGroup();
		if ($id)
		{
			if (in_array($id->getValue(), array(static::GROUP_ADMINS, static::GROUP_ALL_USERS)))
			{
				$group = Loc::getMessage("INTERVOLGA_MIGRATO.SYSTEM_GROUP_" . $id->getValue());
				$message = Loc::getMessage("INTERVOLGA_MIGRATO.DELETE_SYSTEM_GROUP_ERROR", array(
					"#GROUP#" => $group,
					"#XMLID#" => $xmlId,
				));
				throw new \Exception($message);
			}
			if (!$groupObject->delete($id->getValue()))
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.UNKNOWN_ERROR'));
			}
		}
	}

	public function setXmlId($id, $xmlId)
	{
		$groupObject = new \CGroup();
		$isUpdated = $groupObject->update($id->getValue(), array("STRING_ID" => $xmlId));
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($groupObject->LAST_ERROR)));
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