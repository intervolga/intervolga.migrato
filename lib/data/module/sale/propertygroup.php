<?php
namespace Intervolga\Migrato\Data\Module\Sale;

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Internals\OrderPropsGroupTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class PropertyGroup extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.SALE_PROPERTY_GROUP'));
		$this->setFilesSubdir('/persontype/');
		$this->setDependencies(array(
			'PERSON_TYPE_ID' => new Link(PersonType::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = OrderPropsGroupTable::getList();
		while ($propGroup = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($propGroup["ID"]);
			$record->setId($id);
			$record->setXmlId(
				$this->getXmlId($id)
			);
			$record->addFieldsRaw(array(
				"NAME" => $propGroup["NAME"],
				"SORT" => $propGroup["SORT"],
			));

			$link = clone $this->getDependency("PERSON_TYPE_ID");
			$personTypeXmlId = PersonType::getInstance()->getXmlId(
				PersonType::getInstance()->createId($propGroup["PERSON_TYPE_ID"])
			);
			$link->setValue($personTypeXmlId);
			$record->setDependency("PERSON_TYPE_ID", $link);

			$result[] = $record;
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$record = OrderPropsGroupTable::getById($id->getValue())->fetch();
		$personTypeXmlId = PersonType::getInstance()->getXmlId(
			PersonType::getInstance()->createId($record["PERSON_TYPE_ID"])
		);
		$md5 = md5(serialize(array(
			$record['NAME'],
			$personTypeXmlId,
		)));

		return BaseXmlIdProvider::formatXmlId($md5);
	}

	public function update(Record $record)
	{
		$update = $this->recordToArray($record);
		$object = new \CSaleOrderPropsGroup();
		$updateResult = $object->update($record->getId()->getValue(), $update);
		if (!$updateResult)
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 */
	protected function recordToArray(Record $record)
	{
		$array = $record->getFieldsRaw();
		if ($depenency = $record->getDependency("PERSON_TYPE_ID"))
		{
			$personTypeXmlId = $depenency->getValue();
			$idObject = PersonType::getInstance()->findRecord($personTypeXmlId);
			if ($idObject)
			{
				$array["PERSON_TYPE_ID"] = $idObject->getValue();
			}
		}

		return $array;
	}

	protected function createInner(Record $record)
	{
		$add = $this->recordToArray($record);
		$object = new \CSaleOrderPropsGroup();
		$id = $object->add($add);
		if ($id)
		{
			return $this->createId($id);
		}
		else
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$object = new \CSaleOrderPropsGroup();
		$result = $object->delete($id->getValue());
		if (!$result)
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}
}