<?php
namespace Intervolga\Migrato\Data;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

abstract class BaseUserFieldEnum extends BaseData
{
	const XML_ID_SEPARATOR = '.';

	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.USER_FIELD_ENUM'));
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$result = array();
		$enumFieldObject = new \CUserFieldEnum();
		$rsEnum = $enumFieldObject->GetList(array(), $filter);
		while ($enum = $rsEnum->Fetch())
		{
			$record = new Record($this);
			$id = $this->createId($enum['ID']);
			$record->setXmlId($this->getXmlId($id));
			$record->setId(RecordId::createNumericId($enum["ID"]));
			$record->addFieldsRaw(array(
				"VALUE" => $enum["VALUE"],
				"DEF" => $enum["DEF"],
				"SORT" => $enum["SORT"],
			));

			$dependency = clone $this->getDependency("USER_FIELD_ID");
			$dependency->setValue(
				$dependency->getTargetData()->getXmlId(RecordId::createNumericId($enum["USER_FIELD_ID"]))
			);
			$record->setDependency("USER_FIELD_ID", $dependency);

			$result[] = $record;
		}

		return $result;
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsRaw();
		if ($fieldId = $record->getDependency("USER_FIELD_ID")->getId())
		{
			$fields["XML_ID"] = static::getEnumXmlIdWithoutProp($record->getXmlId());
			$enumObject = new \CUserFieldEnum();

			$isUpdated = $enumObject->setEnumValues($fieldId->getValue(), array($record->getId()->getValue() => $fields));
			if (!$isUpdated)
			{
				throw new \Exception(ExceptionText::getFromApplication());
			}
		}
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		if ($fieldId = $record->getDependency("USER_FIELD_ID")->getId())
		{
			$fields["XML_ID"] = static::getEnumXmlIdWithoutProp($record->getXmlId());
			$fields["USER_FIELD_ID"] = $fieldId->getValue();
			$enumObject = new \CUserFieldEnum();

			$isUpdated = $enumObject->setEnumValues($fieldId->getValue(), array("n" => $fields));
			if ($isUpdated)
			{
				$recordId = $this->findRecordForField($fieldId->getValue(), $record->getXmlId());
				if ($recordId)
				{
					return $this->createId($recordId->getValue());
				}
				else
				{
					throw new \Exception(ExceptionText::getUnknown());
				}
			}
			else
			{
				throw new \Exception(ExceptionText::getFromApplication());
			}
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.CREATE_NOT_USER_FIELD', array('#XML_ID#' => $record->getXmlId())));
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$fieldenumObject = new \CUserFieldEnum();
		$obEnum = $fieldenumObject->GetList(array(), array('ID' => $id->getValue()));
		if($arEnum = $obEnum->Fetch())
		{
			$fieldenumObject->SetEnumValues($arEnum['USER_FIELD_ID'], array($arEnum['ID'] => array('DEL' => 'Y')));
		}
	}

	public function setXmlId($id, $xmlId)
	{
		$obEnum = new \CUserFieldEnum();
		$rsEnum = $obEnum->getList(array(), array("ID" => $id->getValue()));
		if ($arEnum = $rsEnum->fetch())
		{
			$userFieldObject = new \CUserFieldEnum();
			$userFieldObject->setEnumValues(
				$arEnum["USER_FIELD_ID"],
				array(
					$arEnum['ID'] => array(
						'XML_ID' => static::getEnumXmlIdWithoutProp($xmlId),
						'VALUE' => $arEnum['VALUE'],
					),
				)
			);
		}
	}

	public function getXmlId($id)
	{
		$xmlId = "";
		if ($id = $id->getValue())
		{
			$obEnum = new \CUserFieldEnum();
			$rsEnum = $obEnum->getList(array(), array("ID" => $id));
			if ($arEnum = $rsEnum->fetch())
			{
				$userField = \CUserTypeEntity::getById($arEnum['USER_FIELD_ID']);
				$md5 = md5(serialize(array(
					$userField['ENTITY_ID'],
					$userField['FIELD_NAME'],
				)));

				$propXml = BaseXmlIdProvider::formatXmlId($md5);
				$xmlId = $propXml . static::XML_ID_SEPARATOR . $arEnum["XML_ID"];
			}
		}
		return $xmlId;
	}

	/**
	 * @param int $fieldId
	 * @param string $xmlId
	 * @return \Intervolga\Migrato\Data\RecordId|null
	 */
	public function findRecordForField($fieldId, $xmlId)
	{
		$enum = new \CUserFieldEnum();
		$result = $enum->getList(
			array(),
			array(
				"USER_FIELD_ID" => $fieldId,
				"XML_ID" => static::getEnumXmlIdWithoutProp($xmlId),
			)
		)->fetch();

		if ($result['ID'])
		{
			return static::createId($result['ID']);
		}
		else
		{
			return null;
		}
	}

	/**
	 * @param string $xmlId
	 * @return mixed
	 */
	protected static function getEnumXmlIdWithoutProp($xmlId)
	{
		$arXml = explode(static::XML_ID_SEPARATOR, $xmlId);
		return $arXml[1];
	}
}