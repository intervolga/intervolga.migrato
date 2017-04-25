<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Module\Main\Group;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

class Permission extends BaseData
{
	public function getFilesSubdir()
	{
		return "/type/iblock/";
	}

	public function getList(array $filter = array())
	{
		$result = array();
		foreach ($this->getIblocks() as $iblockId)
		{
			$permissions = \CIBlock::GetGroupPermissions($iblockId);
			foreach ($permissions as $groupId => $permission)
			{
				$id = $this->createId(array(
					"IBLOCK_ID" => $iblockId,
					"GROUP_ID" => $groupId,
				));
				$record = new Record($this);
				$record->setXmlId($this->getXmlId($id));
				$record->setId($id);

				$record->addFieldsRaw(array(
					"PERMISSION" => $permission,
				));

				$dependency = clone $this->getDependency("GROUP_ID");
				$dependency->setValue(
					Group::getInstance()->getXmlId(RecordId::createNumericId($groupId))
				);
				$record->setDependency("GROUP_ID", $dependency);

				$dependency = clone $this->getDependency("IBLOCK_ID");
				$dependency->setValue(
					Iblock::getInstance()->getXmlId(RecordId::createNumericId($iblockId))
				);
				$record->setDependency("IBLOCK_ID", $dependency);

				$result[] = $record;
			}
		}

		return $result;
	}

	/**
	 * @return array|int[]
	 */
	protected function getIblocks()
	{
		$result = array();
		$getList = \CIBlock::GetList();
		while ($iblock = $getList->fetch())
		{
			$result[] = $iblock["ID"];
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			"GROUP_ID" => new Link(Group::getInstance()),
			"IBLOCK_ID" => new Link(Iblock::getInstance()),
		);
	}

	public function update(Record $record)
	{
		$curValue = $record->getId()->getValue();
		$arGroups = \CIBlock::GetGroupPermissions($curValue["IBLOCK_ID"]);

		if(key_exists($curValue["GROUP_ID"], $arGroups))
		{
			$curFields = $record->getFieldsRaw();
			$arGroups[$curValue["GROUP_ID"]] = $curFields["PERMISSION"];
			$iblock = new \CIBlock();
			$iblock->SetPermission($curValue["IBLOCK_ID"], $arGroups);
		}
		else
			throw new \Exception("Not exist the permission for IBlock: " . $curValue["IBLOCK_ID"] . " and Group: " . $curValue["GROUP_ID"]);
	}

	public function create(Record $record)
	{
		if($record->getDependency("IBLOCK_ID") &&
			$iblockId = Iblock::getInstance()->findRecord($record->getDependency("IBLOCK_ID")->getValue()))
		{
			$iblockId = $iblockId->getValue();
		}
		else
			throw new \Exception("Create permission: Broken external link on IBlock");

		if($record->getDependency("GROUP_ID") &&
			$groupId = Group::getInstance()->findRecord($record->getDependency("GROUP_ID")->getValue()))
		{
			$groupId = $groupId->getValue();
		}
		else
			throw new \Exception("Create permission: Broken external link on Group");

		$arGroups = \CIBlock::GetGroupPermissions($iblockId);

		$arGroups[$groupId] = $record->getField("PERMISSION")->getValue();
		$iblock = new \CIBlock();
		$iblock->SetPermission($iblockId, $arGroups);

		return $this->createId(array(
			"IBLOCK_ID" => $iblockId,
			"GROUP_ID" => $groupId,
		));
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if($id)
		{

		}
		else
			throw new \Exception("Delete permission: not found element for xml id = " . $xmlId);
	}

	public function createId($id)
	{
		return RecordId::createComplexId(array(
			"IBLOCK_ID" => intval($id['IBLOCK_ID']),
			"GROUP_ID" => intval($id['GROUP_ID']),
			)
		);
	}

	public function getXmlId($id)
	{
		$array = $id->getValue();
		$iblockData = Iblock::getInstance();
		$groupData = Group::getInstance();
		$iblockXmlId = $iblockData->getXmlId($iblockData->createId($array['IBLOCK_ID']));
		$groupXmlId = $groupData->getXmlId($groupData->createId($array['GROUP_ID']));
		$md5 = md5(serialize(array(
			$iblockXmlId,
			$groupXmlId
		)));
		return BaseXmlIdProvider::formatXmlId($md5);
	}
}