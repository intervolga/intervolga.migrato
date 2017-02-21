<? namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProviders\UfXmlIdProvider;

class Group extends BaseData
{
	public function __construct()
	{
		$this->xmlIdProvider = new UfXmlIdProvider($this);
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
			$id = RecordId::createNumericId($group["ID"]);
			$record->setXmlId($this->getXmlIdProvider()->getXmlId($id));

			$record->setId($id);
			$record->setFields(array(
				"ACTIVE" => $group["ACTIVE"],
				"NAME" => $group["NAME"],
				"DESCRIPTION" => $group["DESCRIPTION"],
				"STRING_ID" => $group["STRING_ID"],
			));
			$result[] = $record;
		}
		return $result;
	}

	public function update(Record $record)
	{
		$groupObject = new \CGroup();
		$isUpdated = $groupObject->update($record->getId()->getValue(), $record->getFields());
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($groupObject->LAST_ERROR)));
		}
	}

	public function create(Record $record)
	{
		$groupObject = new \CGroup();
		$groupId = $groupObject->add($record->getFields());
		if ($groupId)
		{
			$id = RecordId::createNumericId($groupId);
			$this->getXmlIdProvider()->setXmlId($id, $record->getXmlId());
			return $id;
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
		if (!$groupObject->delete($id->getValue()))
		{
			throw new \Exception("Unknown error");
		}
	}
}