<?php
namespace Intervolga\Migrato\Data\Module\Catalog;

use Bitrix\Catalog\StoreTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

Loc::loadMessages(__FILE__);

class Store extends BaseData
{
	public static function getMinVersion()
	{
		return "12.5.1";
	}

	public function configure()
	{
		Loader::includeModule("catalog");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Catalog\\StoreTable");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.CATALOG_STORE'));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = StoreTable::getList();
		while ($store = $getList->fetch())
		{
			$record = new Record($this);
			$record->setId($this->createId($store["ID"]));
			$record->setXmlId($store["XML_ID"]);
			$record->addFieldsRaw(array(
				"TITLE" => $store["TITLE"],
				"ACTIVE" => $store["ACTIVE"],
				"SORT" => $store["SORT"],
				"ADDRESS" => $store["ADDRESS"],
				"DESCRIPTION" => $store["DESCRIPTION"],
				"GPS_N" => $store["GPS_N"],
				"GPS_S" => $store["GPS_S"],
				"PHONE" => $store["PHONE"],
				"SCHEDULE" => $store["SCHEDULE"],
				"EMAIL" => $store["EMAIL"],
				"ISSUING_CENTER" => $store["ISSUING_CENTER"],
				"SHIPPING_CENTER" => $store["SHIPPING_CENTER"],
			));
			$result[] = $record;
		}
		return $result;
	}

	public function update(Record $record)
	{
		$update = $record->getFieldsRaw();

		$object = new \CCatalogStore();
		$id = $record->getId()->getValue();
		$updateResult = $object->update($id, $update);
		if (!$updateResult)
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	protected function createInner(Record $record)
	{
		$add = $record->getFieldsRaw();

		$object = new \CCatalogStore();
		$id = $object->add($add);
		if (!$id)
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
		else
		{
			return $this->createId($id);
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$object = new \CCatalogStore();
		if (!$object->delete($id->getValue()))
		{
			throw new \Exception(ExceptionText::getUnknown());
		}
	}

}