<?php
namespace Intervolga\Migrato\Data\Module\Sale;

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Internals\PersonTypeSiteTable;
use Bitrix\Sale\Internals\PersonTypeTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Main\Site;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class PersonType extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.SALE_PERSON_TYPE'));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$personTypesSites = $this->getPersonTypesSites();
		$getList = PersonTypeTable::getList();
		while ($personType = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($personType["ID"]);
			$record->setId($id);
			$record->setXmlId(
				$this->getXmlId($id)
			);
			$record->addFieldsRaw(array(
				"NAME" => $personType["NAME"],
				"SORT" => $personType["SORT"],
				"ACTIVE" => $personType["ACTIVE"],
			));

			$link = clone $this->getDependency('SITE');
			$link->setValues($personTypesSites[$personType["ID"]]);
			$record->setDependency('SITE', $link);

			$result[] = $record;
		}

		return $result;
	}

	/**
	 * @return array
	 */
	protected function getPersonTypesSites()
	{
		$result = array();
		$getList = PersonTypeSiteTable::getList();
		while ($personTypeSite = $getList->fetch())
		{
			$result[$personTypeSite["PERSON_TYPE_ID"]][] = Site::getInstance()->getXmlId(
				Site::getInstance()->createId($personTypeSite["SITE_ID"])
			);
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$record = PersonTypeTable::getById($id->getValue())->fetch();
		$personTypesSites = $this->getPersonTypesSites();

		$md5 = md5(serialize(array(
			$record['NAME'],
			$personTypesSites[$record['ID']],
		)));

		return BaseXmlIdProvider::formatXmlId($md5);
	}

	public function getDependencies()
	{
		return array(
			'SITE' => new Link(Site::getInstance()),
		);
	}

	public function update(Record $record)
	{
		$id = $record->getId()->getValue();
		$update = $this->recordToArray($record);
		$object = new \CSalePersonType();
		$updateResult = $object->update($id, $update);
		if (!$updateResult)
		{
			throw new \Exception(ExceptionText::getFromApplication());
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
		$array = array(
			'NAME' => $record->getFieldRaw('NAME'),
			'SORT' => $record->getFieldRaw('SORT'),
			'ACTIVE' => $record->getFieldRaw('ACTIVE'),
			'LID' => $record->getFieldRaws('LID'),
		);
		$link = $record->getDependency('SITE');
		if ($link && $link->getValues())
		{
			foreach ($link->findIds() as $siteIdObject)
			{
				$array['LID'][] = $siteIdObject->getValue();
			}
		}

		return $array;
	}

	protected function createInner(Record $record)
	{
		$add = $this->recordToArray($record);
		$object = new \CSalePersonType();
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
		$object = new \CSalePersonType();
		$result = $object->delete($id->getValue());
		if (!$result)
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}
}