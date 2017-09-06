<?php
namespace Intervolga\Migrato\Data\Module\Catalog;

use Bitrix\Catalog\ExtraTable;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class Extra extends BaseData
{
	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.CATALOG_EXTRA'));
		$this->setVirtualXmlId(true);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = ExtraTable::getList();
		while ($extra = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($extra['ID']);
			$record->setId($this->createId($extra['ID']));
			$record->setXmlId($this->getXmlId($id));
			$record->addFieldsRaw(array(
				'NAME' => $extra['NAME'],
				'PERCENTAGE' => $extra['PERCENTAGE'],
			));
			$result[] = $record;
		}
		return $result;
	}

	public function getXmlId($id)
	{
		$record = ExtraTable::getById($id->getValue())->fetch();

		$md5 = md5(serialize(array(
			$record['NAME'],
			$record['PERCENTAGE'],
		)));

		return BaseXmlIdProvider::formatXmlId($md5);
	}

	public function update(Record $record)
	{
		$update = $record->getFieldsRaw();

		$object = new \CExtra();
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

		$object = new \CExtra();
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
		$object = new \CExtra();
		if (!$object->delete($id->getValue()))
		{
			throw new \Exception(ExceptionText::getUnknown());
		}
	}

}