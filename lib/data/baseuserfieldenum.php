<?php
namespace Intervolga\Migrato\Data;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

abstract class BaseUserFieldEnum extends BaseData
{
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
			$record->setXmlId($enum["XML_ID"]);
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
			$fields["XML_ID"] = $record->getXmlId();
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
			$fields["XML_ID"] = $record->getXmlId();
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
		$fieldenumObject->deleteFieldEnum($id->getValue());
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
						'XML_ID' => $xmlId,
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
				$xmlId = $arEnum["XML_ID"];
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
				"XML_ID" => $xmlId,
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
}