<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Module\Main\Group;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\XmlIdProvider\TableXmlIdProvider;

class Permission extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new TableXmlIdProvider($this);
	}

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
				$id = RecordId::createComplexId(array(
					"IBLOCK_ID" => intval($iblockId),
					"GROUP_ID" => intval($groupId),
				));
				$record = new Record($this);
				$record->setXmlId($this->getXmlIdProvider()->getXmlId($id));
				$record->setId($id);

				$record->addFieldsRaw(array(
					"PERMISSION" => $permission,
				));

				$dependency = clone $this->getDependency("GROUP_ID");
				$dependency->setValue(
					Group::getInstance()->getXmlIdProvider()->getXmlId(RecordId::createNumericId($groupId))
				);
				$record->setDependency("GROUP_ID", $dependency);

				$dependency = clone $this->getDependency("IBLOCK_ID");
				$dependency->setValue(
					Iblock::getInstance()->getXmlIdProvider()->getXmlId(RecordId::createNumericId($iblockId))
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

		$arGroups[$groupId] = $record->getField("PERMISSION");
		$iblock = new \CIBlock();
		$iblock->SetPermission($iblockId, $arGroups);

		return RecordId::createComplexId(array(
			"IBLOCK_ID" => intval($iblockId),
			"GROUP_ID" => intval($groupId),
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
}