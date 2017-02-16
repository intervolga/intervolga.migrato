<? namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataRecordId;
use Intervolga\Migrato\Tool\XmlIdProviders\UfXmlIdProvider;

class Group extends BaseData
{
	public function __construct()
	{
		$this->xmlIdProvider = new UfXmlIdProvider($this);
	}

	public function getFromDatabase(array $filter = array())
	{
		$result = array();
		$by = "ID";
		$order = "ASC";
		$getList = \CGroup::getList($by, $order);
		while ($group = $getList->fetch())
		{
			$record = new DataRecord();
			$id = DataRecordId::createNumericId($group["ID"]);
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

	/**
	 * @param DataRecord $record
	 *
	 * @throws \Exception
	 */
	public function update(DataRecord $record)
	{
		$groupObject = new \CGroup();
		$isUpdated = $groupObject->update($record->getId()->getValue(), $record->getFields());
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($groupObject->LAST_ERROR)));
		}
	}

	/**
	 * @param DataRecord $record
	 *
	 * @throws \Exception
	 */
	public function create(DataRecord $record)
	{
		$groupObject = new \CGroup();
		$groupId = $groupObject->add($record->getFields());
		if ($groupId)
		{
			$id = DataRecordId::createNumericId($groupId);
			$this->getXmlIdProvider()->setXmlId($id, $record->getXmlId());
		}
		else
		{
			throw new \Exception(trim(strip_tags($groupObject->LAST_ERROR)));
		}
	}

	/**
	 * @param string $xmlId
	 *
	 * @throws \Exception
	 */
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