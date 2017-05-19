<? namespace Intervolga\Migrato\Data\Module\Catalog;

use Bitrix\Catalog\GroupAccessTable;
use Bitrix\Catalog\GroupLangTable;
use Bitrix\Catalog\GroupTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Main\Group;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

class PriceType extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("catalog");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Catalog\\GroupTable");
	}

	public function getDependencies()
	{
		return array(
			"USER_GROUP" => new Link(Group::getInstance()),
			"USER_GROUP_BUY" => new Link(Group::getInstance()),
		);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = GroupTable::getList();
		while ($priceType = $getList->fetch())
		{
			$record = new Record($this);
			$record->setId($this->createId($priceType["ID"]));
			$record->setXmlId($priceType["XML_ID"]);
			$record->addFieldsRaw(array(
				"NAME" => $priceType["NAME"],
				"BASE" => $priceType["BASE"],
				"SORT" => $priceType["SORT"],
			));
			$this->addGroupDependency($record, "USER_GROUP");
			$this->addGroupDependency($record, "USER_GROUP_BUY");
			$this->addGroupLang($record);
			$result[] = $record;
		}
		return $result;
	}

	/**
	 * @param Record $record
	 * @param string $type
	 */
	protected function addGroupDependency(Record $record, $type)
	{
		$accesses = $this->getAccessesList();
		$link = clone $this->getDependency($type);
		if ($accesses[$type][$record->getId()->getValue()])
		{
			$viewGroupsXmlIds = array();
			foreach ($accesses[$type][$record->getId()->getValue()] as $groupId)
			{
				$groupIdObject = Group::getInstance()->createId($groupId);
				$groupXmlId = Group::getInstance()->getXmlId($groupIdObject);
				if ($groupXmlId)
				{
					$viewGroupsXmlIds[] = $groupXmlId;
				}
			}
			sort($viewGroupsXmlIds);
			$link->setValues($viewGroupsXmlIds);
			$record->setDependency($type, $link);
		}
	}

	/**
	 * @return array
	 */
	protected function getAccessesList()
	{
		$accesses = array();
		$getList = GroupAccessTable::getList();
		while ($priceTypeAccess = $getList->fetch())
		{
			if ($priceTypeAccess["ACCESS"] == GroupAccessTable::ACCESS_BUY)
			{
				$accesses["USER_GROUP_BUY"][$priceTypeAccess["CATALOG_GROUP_ID"]][] = $priceTypeAccess["GROUP_ID"];
			}
			if ($priceTypeAccess["ACCESS"] == GroupAccessTable::ACCESS_VIEW)
			{
				$accesses["USER_GROUP"][$priceTypeAccess["CATALOG_GROUP_ID"]][] = $priceTypeAccess["GROUP_ID"];
			}
		}

		return $accesses;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @throws \Exception
	 */
	protected function addGroupLang(Record $record)
	{
		$langs = $this->getPriceLangs();
		if ($langs[$record->getId()->getValue()])
		{
			$userLangs = Value::treeToList($langs[$record->getId()->getValue()], "USER_LANG");
			$record->addFieldsRaw($userLangs);
		}
	}

	/**
	 * @return array
	 */
	protected function getPriceLangs()
	{
		$langs = array();
		$getList = GroupLangTable::getList();
		while ($priceTypeLang = $getList->fetch())
		{
			$langs[$priceTypeLang["CATALOG_GROUP_ID"]][$priceTypeLang["LANG"]] = $priceTypeLang["NAME"];
		}

		return $langs;
	}

	public function update(Record $record)
	{
		$update = $this->recordToArray($record);

		$object = new \CCatalogGroup();
		$id = $record->getId()->getValue();
		$updateResult = $object->update($id, $update);
		if (!$updateResult)
		{
			global $APPLICATION;
			throw new \Exception($APPLICATION->getException()->getString());
		}
	}

	protected function createInner(Record $record)
	{
		$add = $this->recordToArray($record);

		$object = new \CCatalogGroup();
		$id = $object->add($add);
		if (!$id)
		{
			global $APPLICATION;
			throw new \Exception($APPLICATION->getException()->getString());
		}
		else
		{
			return $this->createId($id);
		}
	}

	protected function deleteInner($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if ($id)
		{
			$object = new \CCatalogGroup();
			if (!$object->delete($id->getValue()))
			{
				throw new \Exception("Unknown error");
			}
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
		$array = $record->getFieldsRaw(array("USER_LANG"));
		if ($dependency = $record->getDependency("USER_GROUP"))
		{
			foreach ($dependency->findIds() as $id)
			{
				$array["USER_GROUP"][] = $id->getValue();
			}
		}
		if ($dependency = $record->getDependency("USER_GROUP_BUY"))
		{
			foreach ($dependency->findIds() as $id)
			{
				$array["USER_GROUP_BUY"][] = $id->getValue();
			}
		}

		return $array;
	}
}