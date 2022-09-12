<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Enum extends BaseData
{
	const XML_ID_SEPARATOR = '.';

	protected function configure()
	{
		Loader::includeModule('iblock');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ENUM'));
		$this->setFilesSubdir('/type/iblock/property/');
		$this->setDependencies(array(
			'PROPERTY_ID' => new Link(Property::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = PropertyEnumerationTable::getList();
		while ($enum = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($enum['ID']);
			$record->setXmlId($this->getXmlId($id));
			$record->setId($id);
			$record->addFieldsRaw(array(
				"VALUE" => $enum["VALUE"],
				"DEF" => $enum["DEF"],
				"SORT" => $enum["SORT"],
			));

			$dependency = clone $this->getDependency("PROPERTY_ID");
			$dependency->setValue(
				Property::getInstance()->getXmlId(RecordId::createNumericId($enum["PROPERTY_ID"]))
			);
			$record->setDependency("PROPERTY_ID", $dependency);

			$result[] = $record;
		}

		return $result;
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsRaw();

		if ($propertyId = $record->getDependency("PROPERTY_ID")->getId())
		{
			$fields["PROPERTY_ID"] = $propertyId->getValue();
			$enumObject = new \CIBlockPropertyEnum();
			$isUpdated = $enumObject->update($record->getId()->getValue(), $fields);
			if (!$isUpdated)
			{
				throw new \Exception(ExceptionText::getUnknown());
			}
		}
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		if ($propertyId = $record->getDependency("PROPERTY_ID")->getId())
		{
			$fields["PROPERTY_ID"] = $propertyId->getValue();
			$fields["XML_ID"] = $record->getXmlId();

			$enumObject = new \CIBlockPropertyEnum();
			$enumId = $enumObject->add($fields);
			if ($enumId)
			{
				return $this->createId($enumId);
			}
			else
			{
				throw new \Exception(ExceptionText::getUnknown());
			}
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_PROPERTY_ENUM_NOT_PROPERTY', array(
				'#XML_ID#' => $record->getXmlId(),
			)));
		}
	}

	protected function deleteInner(RecordId $id)
	{
		if (!\CIBlockPropertyEnum::delete($id->getValue()))
		{
			throw new \Exception(ExceptionText::getUnknown());
		}
	}

	public function setXmlId($id, $xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$arFields = array(
			"XML_ID" => $fields[2],
		);
		$isUpdated = \CIBlockPropertyEnum::update($id->getValue(), $arFields);
		if (!$isUpdated)
		{
			throw new \Exception(ExceptionText::getUnknown());
		}
	}

	public function getXmlId($id)
	{
		$xmlId = '';
		$enum = PropertyEnumerationTable::getList(array(
			'filter' => array(
				'=ID' => $id->getValue(),
			),
			'select' => array(
				'ID',
				'XML_ID',
				'PROPERTY_XML_ID' => 'PROPERTY.XML_ID',
				'IBLOCK_XML_ID' => 'PROPERTY.IBLOCK.XML_ID',
			),
		))->fetch();
		if ($enum)
		{
			if ($enum['XML_ID'] && $enum['IBLOCK_XML_ID'])
			{
				$xmlId = $enum['IBLOCK_XML_ID'] . static::XML_ID_SEPARATOR . $enum['PROPERTY_XML_ID'] . static::XML_ID_SEPARATOR . $enum['XML_ID'];
			}
		}
		return $xmlId;
	}

	public function findRecord($xmlId)
	{
		$id = null;
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		if ($fields[0] && $fields[1] && $fields[2])
		{
			$filter = array(
				'=PROPERTY.IBLOCK.XML_ID' => $fields[0],
				'=PROPERTY.XML_ID' => $fields[1],
				'=XML_ID' => $fields[2],
			);
			$enum = PropertyEnumerationTable::getList(array('filter' => $filter))->fetch();
			if ($enum)
			{
				$id = $this->createId($enum['ID']);
			}
		}

		return $id;
	}

	public function validateXmlIdCustom($xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$isValid = (count($fields) == 3 && $fields[0] && $fields[1] && $fields[2]);
		if (!$isValid)
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.INVALID_IBLOCK_PROPERTY_ENUM_XML_ID'));
		}
	}
}
