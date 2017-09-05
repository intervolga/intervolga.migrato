<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseUserField;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class Field extends BaseUserField
{
	protected function configure()
	{
		parent::configure();
		$this->setFilesSubdir('/type/iblock/section/');
		$this->setDependencies(array(
			'IBLOCK_ID' => new Link(Iblock::getInstance()),
		));
	}

	/**
	 * @param string $userFieldEntityId
	 *
	 * @return int
	 */
	public function isCurrentUserField($userFieldEntityId)
	{
		return preg_match("/^IBLOCK_[0-9]+_SECTION$/", $userFieldEntityId);
	}

	/**
	 * @param array $userField
	 *
	 * @return Record
	 */
	protected function userFieldToRecord(array $userField)
	{
		$record = parent::userFieldToRecord($userField);
		$iBlockId = str_replace("IBLOCK_", "", $userField["ENTITY_ID"]);
		$iBlockRecordId = RecordId::createNumericId($iBlockId);
		$iBlockXmlId = Iblock::getInstance()->getXmlId($iBlockRecordId);

		$dependency = clone $this->getDependency("IBLOCK_ID");
		$dependency->setValue($iBlockXmlId);
		$record->setDependency("IBLOCK_ID", $dependency);

		return $record;
	}

	public function getList(array $filter = array())
	{
		if ($filter["IBLOCK_ID"])
		{
			$filter["ENTITY_ID"] = "IBLOCK_" . $filter["IBLOCK_ID"] . "_SECTION";
			unset($filter["IBLOCK_ID"]);
		}

		return parent::getList($filter);
	}

	public function getDependencyString()
	{
		return "IBLOCK_ID";
	}

	public function getDependencyNameKey($id)
	{
		return "IBLOCK_" . $id . "_SECTION";
	}

	protected function createInner(Record $record)
	{
		if ($iblockId = $record->getDependency($this->getDependencyString())->getId())
		{
			$userTypeEntity = new \CUserTypeEntity();
			$userTypeEntity->CreatePropertyTables("iblock_" . $iblockId->getValue() . "_section");
			$record->setFieldRaw("ENTITY_ID", $this->getDependencyNameKey($iblockId->getValue()));

			return parent::createInner($record);
		}
		else
		{
			throw new \Exception(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.DEPENDENCY_NOT_RESOLVED',
					array
					(
						'#XML_ID#' => $record->getXmlId()
					)
				)
			);
		}
	}

	public function getXmlId($id)
	{
		$userField = \CUserTypeEntity::getById($id->getValue());
		$iBlockId = str_replace("IBLOCK_", "", $userField["ENTITY_ID"]);
		$iBlockRecordId = RecordId::createNumericId($iBlockId);
		$iBlockXmlId = Iblock::getInstance()->getXmlId($iBlockRecordId);
		$md5 = md5(serialize(array(
			$iBlockXmlId,
			$userField['FIELD_NAME'],
		)));

		return BaseXmlIdProvider::formatXmlId($md5);
	}
}