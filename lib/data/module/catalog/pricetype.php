<? namespace Intervolga\Migrato\Data\Module\Catalog;

use Bitrix\Catalog\GroupAccessTable;
use Bitrix\Catalog\GroupLangTable;
use Bitrix\Catalog\GroupTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Main\Group;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
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
			$record->setId(RecordId::createNumericId($priceType["ID"]));
			$record->setXmlId($priceType["XML_ID"]);
			$record->setFields(array(
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
				$groupIdObject = RecordId::createNumericId($groupId);
				$groupXmlId = Group::getInstance()->getXmlIdProvider()->getXmlId($groupIdObject);
				if ($groupXmlId)
				{
					$viewGroupsXmlIds[] = $groupXmlId;
				}
			}
			$link->setValues($viewGroupsXmlIds);
			$record->addDependency($type, $link);
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

	protected function addGroupLang(Record $record)
	{
		$langs = $this->getPriceLangs();
		if ($langs[$record->getId()->getValue()])
		{
			foreach ($langs[$record->getId()->getValue()] as $lang => $name)
			{
				$record->setField("NAME.$lang", $name);
			}
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
}