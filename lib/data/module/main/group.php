<? namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\XmlIdProvider\GroupXmlIdProvider;

class Group extends BaseData
{
	public function __construct()
	{
		$this->xmlIdProvider = new GroupXmlIdProvider($this);
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
			$xmlId = $this->getXmlIdProvider()->getXmlId($id);
			if (!$filter || in_array($xmlId, $filter))
			{
				$record->setId($id);
				$record->setXmlId($xmlId);
				$record->setFields(array(
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
		$isUpdated = $groupObject->update($record->getId()->getValue(), $record->getFieldsStrings());
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($groupObject->LAST_ERROR)));
		}
	}

	public function create(Record $record)
	{
		$groupObject = new \CGroup();
		$groupId = $groupObject->add($record->getFieldsStrings());
		if ($groupId)
		{
			return $this->createId($groupId);
		}
		else
		{
			throw new \Exception(trim(strip_tags($groupObject->LAST_ERROR)));
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$groupObject = new \CGroup();
		if ($id)
		{
			if (!$groupObject->delete($id->getValue()))
			{
				throw new \Exception("Unknown error");
			}
		}
	}
}