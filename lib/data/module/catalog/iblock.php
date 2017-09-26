<?php
namespace Intervolga\Migrato\Data\Module\Catalog;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Catalog\CatalogIblockTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module;
use Intervolga\Migrato\Data\Module\Iblock\Property;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

/**
 * Class Iblock
 * @package Intervolga\Migrato\Data\Module\Catalog
 */
class Iblock extends BaseData
{

	protected function configure()
	{
		Loader::includeModule("catalog");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.CATALOG_IBLOCK'));
		$this->setDependencies(array(
			'IBLOCK_ID' => new Link(Module\Iblock\Iblock::getInstance()),
			'PRODUCT_IBLOCK_ID' => new Link(Module\Iblock\Iblock::getInstance()),
			'SKU_PROPERTY_ID' => new Link(Property::getInstance()),
		));
		$this->setVirtualXmlId(true);
	}

	/**
	 * @param array $filter
	 * @return array
	 */
	public function getList(array $filter = array())
	{
		$result = array();
		$getList = CatalogIblockTable::getList(array('filter' => $filter));
		while ($iblock = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($iblock['IBLOCK_ID']);
			$record->setId($id);
			$record->setXmlId($this->getXmlId($id));
			$dependency = clone $this->getDependency("IBLOCK_ID");
			$dependency->setValue(
				Module\Iblock\Iblock::getInstance()->getXmlId(RecordId::createStringId($iblock["IBLOCK_ID"]))
			);
			$record->setDependency("IBLOCK_ID", $dependency);

			if($iblock["PRODUCT_IBLOCK_ID"] > 0) {
				$dependency = clone $this->getDependency("PRODUCT_IBLOCK_ID");
				$dependency->setValue(
					Module\Iblock\Iblock::getInstance()->getXmlId(RecordId::createStringId($iblock["PRODUCT_IBLOCK_ID"]))
				);
				$record->setDependency("PRODUCT_IBLOCK_ID", $dependency);
			} else {
				$record->addFieldsRaw(array('PRODUCT_IBLOCK_ID' => $iblock['PRODUCT_IBLOCK_ID']));
			}

			if($iblock["SKU_PROPERTY_ID"] > 0) {
				$dependency = clone $this->getDependency("SKU_PROPERTY_ID");
				$dependency->setValue(
					Property::getInstance()->getXmlId(RecordId::createStringId($iblock["SKU_PROPERTY_ID"]))
				);
				$record->setDependency("SKU_PROPERTY_ID", $dependency);
			} else {
				$record->addFieldsRaw(array('SKU_PROPERTY_ID' => $iblock['SKU_PROPERTY_ID']));
			}
			$record->addFieldsRaw(array(
				'YANDEX_EXPORT' => $iblock['YANDEX_EXPORT'],
				'SUBSCRIPTION' => $iblock['SUBSCRIPTION'],
				'VAT_ID' => $iblock['VAT_ID'],
			));
			$result[] = $record;
		}
		return $result;
	}

	/**
	 * @param RecordId $id
	 * @return string
	 */
	public function getXmlId($id)
	{
		return Module\Iblock\Iblock::getInstance()->getXmlId($id);
	}

	/**
	 * @param Record $record
	 * @return RecordId
	 * @throws \Exception
	 */
	protected function createInner(Record $record)
	{
		$fields = $this->recordToArray($record);
		$result = CatalogIblockTable::add($fields);
		if($result->isSuccess()) {
			$id = $this->createId($result->getData()['IBLOCK_ID']);
			return $id;
		} else {
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	/**
	 * @param RecordId $id
	 * @throws \Exception
	 */
	protected function deleteInner(RecordId $id)
	{
		$result = CatalogIblockTable::delete($id->getValue());
		if(!$result->isSuccess()) {
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	/**
	 * @param Record $record
	 * @throws \Exception
	 */
	public function update(Record $record)
	{
		$fields = $this->recordToArray($record);
		$result = CatalogIblockTable::update($record->getId()->getValue(), $fields);
		if(!$result->isSuccess()) {
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	/**
	 * @param Record $record
	 * @return \string[]
	 */
	protected function recordToArray(Record $record)
	{
		$array = $record->getFieldsRaw(array("YANDEX_EXPORT", "SUBSCRIPTION", "VAT_ID"));

		if($record->getDependency('IBLOCK_ID')->getValue() && $iblockId = $record->getDependency('IBLOCK_ID')->findId()) {
			$array['IBLOCK_ID'] = $iblockId->getValue();
		}

		if($record->getFieldRaw('PRODUCT_IBLOCK_ID') != '0' &&  $record->getDependency('PRODUCT_IBLOCK_ID')->getValue() && $iblockId = $record->getDependency('PRODUCT_IBLOCK_ID')->findId()) {
			$array['PRODUCT_IBLOCK_ID'] = $iblockId->getValue();
		} else {
			$array['PRODUCT_IBLOCK_ID'] = $record->getFieldRaw('PRODUCT_IBLOCK_ID');
		}

		if($record->getFieldRaw('SKU_PROPERTY_ID') != '0' && $record->getDependency('SKU_PROPERTY_ID')->getValue() && $propertyId = $record->getDependency('SKU_PROPERTY_ID')->findId()) {
			$array['SKU_PROPERTY_ID'] = $propertyId->getValue();
		} else {
			$array['SKU_PROPERTY_ID'] = $record->getFieldRaw('SKU_PROPERTY_ID');
		}

		return $array;
	}
}