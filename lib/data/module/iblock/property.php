<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionPropertyTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

Loc::loadMessages(__FILE__);

class Property extends BaseData
{
	const XML_ID_SEPARATOR = '.';

	protected function configure()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Iblock\\PropertyTable");
	}

	public function getEntityNameLoc()
	{
		return Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_PROPERTY');
	}

	public function getFilesSubdir()
	{
		return "/type/iblock/";
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = PropertyTable::getList();
		while ($property = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($property['ID']);
			$record->setXmlId($this->getXmlId($id));
			$record->setId($id);

			$smartFilterOptions = $this->getSmartFilterOptions($property["ID"]);
			$property = $this->exportDefaultValue($property);

			$record->addFieldsRaw(array_merge(
				$smartFilterOptions,
				array(
					"NAME" => $property["NAME"],
					"ACTIVE" => $property["ACTIVE"],
					"SORT" => $property["SORT"],
					"CODE" => $property["CODE"],
					"DEFAULT_VALUE" => $property["DEFAULT_VALUE"],
					"PROPERTY_TYPE" => $property["PROPERTY_TYPE"],
					"ROW_COUNT" => $property["ROW_COUNT"],
					"COL_COUNT" => $property["COL_COUNT"],
					"LIST_TYPE" => $property["LIST_TYPE"],
					"MULTIPLE" => $property["MULTIPLE"],
					"FILE_TYPE" => $property["FILE_TYPE"],
					"MULTIPLE_CNT" => $property["MULTIPLE_CNT"],
					"WITH_DESCRIPTION" => $property["WITH_DESCRIPTION"],
					"SEARCHABLE" => $property["SEARCHABLE"],
					"FILTRABLE" => $property["FILTRABLE"],
					"IS_REQUIRED" => $property["IS_REQUIRED"],
					"USER_TYPE" => $property["USER_TYPE"],
					"HINT" => $property["HINT"],
				)
			));

			if ($property["USER_TYPE_SETTINGS"])
			{
				if ($userTypeSettings = unserialize($property["USER_TYPE_SETTINGS"]))
				{
					$record->addFieldsRaw(Value::treeToList($userTypeSettings, "USER_TYPE_SETTINGS"));
				}
			}

			$dependency = clone $this->getDependency("IBLOCK_ID");
			$dependency->setValue(
				Iblock::getInstance()->getXmlId(RecordId::createNumericId($property["IBLOCK_ID"]))
			);
			$record->setDependency("IBLOCK_ID", $dependency);

			if ($property["LINK_IBLOCK_ID"])
			{
				$reference = clone $this->getReference("LINK_IBLOCK_ID");
				$reference->setValue(
					Iblock::getInstance()->getXmlId(RecordId::createNumericId($property["LINK_IBLOCK_ID"]))
				);
				$record->setReference("LINK_IBLOCK_ID", $reference);
			}
			$result[] = $record;
		}

		return $result;
	}

	/**
	 * @param array $property
	 *
	 * @return array
	 */
	protected function exportDefaultValue(array $property)
	{
		if ($property['USER_TYPE'] == 'HTML' && $property['DEFAULT_VALUE'])
		{
			$defaultValue = unserialize($property['DEFAULT_VALUE']);
			if (is_array($defaultValue))
			{
				if (!strlen($defaultValue['TEXT']))
				{
					$property['DEFAULT_VALUE'] = false;
				}
			}
		}

		return $property;
	}

	/**
	 * @param int $propertyId
	 *
	 * @return array
	 */
	private function getSmartFilterOptions($propertyId)
	{
		$sectionPropertyGetList = SectionPropertyTable::getList(array(
			"filter" => array(
				"PROPERTY_ID" => $propertyId,
				"SECTION_ID" => 0,
			),
		));

		$result = array();
		if ($property = $sectionPropertyGetList->fetch())
		{
			$result["IS_ROOT_SMART_FILTER"] = "Y";
			$result["SMART_FILTER"] = $property["SMART_FILTER"];
			$result["DISPLAY_TYPE"] = $property["DISPLAY_TYPE"];
			$result["DISPLAY_EXPANDED"] = $property["DISPLAY_EXPANDED"];
			$result["FILTER_HINT"] = $property["FILTER_HINT"];
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			"IBLOCK_ID" => new Link(Iblock::getInstance()),
		);
	}

	public function getReferences()
	{
		return array(
			"LINK_IBLOCK_ID" => new Link(Iblock::getInstance()),
		);
	}

	public function update(Record $record)
	{
		$fields = $this->recordToArray($record);
		$smartFilterSettingsBeforeUpdate = $this->getDbRootSmartFilter($record->getId()->getValue());
		$propertyObject = new \CIBlockProperty();
		if (!$propertyObject->update($record->getId()->getValue(), $fields))
		{
			throw new \Exception(ExceptionText::getLastError($propertyObject));
		}
		if ($record->isReferenceUpdate())
		{
			$this->restoreDbRootSmartFilter($record->getId()->getValue(), $smartFilterSettingsBeforeUpdate);
		}
		else
		{
			$this->updateSmartFilter($fields["IBLOCK_ID"], $record->getId()->getValue(), $fields);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return \string[]
	 * @throws \Exception
	 */
	protected function recordToArray(Record $record)
	{
		$fields = $record->getFieldsRaw(array("USER_TYPE_SETTINGS"));
		if ($iblock = $record->getDependency("IBLOCK_ID"))
		{
			if ($iblock->getId())
			{
				$fields["IBLOCK_ID"] = $iblock->getId()->getValue();
			}
			else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_NOT_FOUND', array(
					'#IBLOCK#' => '$iblock->getValue()',
				)));
			}
		}
		if ($reference = $record->getReference("LINK_IBLOCK_ID"))
		{
			if ($reference->getId())
			{
				$fields["LINK_IBLOCK_ID"] = $reference->getId()->getValue();
			}
		}
		if ($fields["MULTIPLE_CNT"] === "")
		{
			$fields["MULTIPLE_CNT"] = false;
		}
		$fields = $this->importDefaultValue($fields);

		return $fields;
	}

	/**
	 * @param array $fields
	 *
	 * @return array
	 */
	protected function importDefaultValue(array $fields)
	{
		if ($fields['USER_TYPE'] == 'HTML' && $fields['DEFAULT_VALUE'])
		{
			$fields['DEFAULT_VALUE'] = unserialize($fields['DEFAULT_VALUE']);
		}

		return $fields;
	}

	/**
	 * @param int $propertyId
	 *
	 * @return array
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function getDbRootSmartFilter($propertyId)
	{
		$smartFilterSettings = SectionPropertyTable::getList(array(
			"filter" => array(
				"PROPERTY_ID" => $propertyId,
				"SECTION_ID" => 0,
			),
		))->fetch();
		if ($smartFilterSettings)
		{
			return $smartFilterSettings;
		}
		else
		{
			return array();
		}
	}

	/**
	 * @param int $propertyId
	 * @param array $smartFilterSettings
	 *
	 * @throws \Exception
	 */
	protected function restoreDbRootSmartFilter($propertyId, array $smartFilterSettings)
	{
		$this->deleteRootSmartFilter($propertyId);
		if ($smartFilterSettings)
		{
			SectionPropertyTable::add($smartFilterSettings);
		}
	}

	/**
	 * @param $iblockId
	 * @param $propertyId
	 * @param $property
	 *
	 * @throws \Exception
	 */
	protected function updateSmartFilter($iblockId, $propertyId, $property)
	{
		$this->deleteRootSmartFilter($propertyId);
		if ($property['IS_ROOT_SMART_FILTER'])
		{
			if ($property['IS_ROOT_SMART_FILTER'] == 'Y')
			{
				$fields = array(
					'IBLOCK_ID' => $iblockId,
					'PROPERTY_ID' => $propertyId,
					'SECTION_ID' => 0,
					'SMART_FILTER' => $property['SMART_FILTER'],
					'DISPLAY_TYPE' => $property['DISPLAY_TYPE'],
					'DISPLAY_EXPANDED' => $property['DISPLAY_EXPANDED'],
					'FILTER_HINT' => $property['FILTER_HINT'],
				);
				SectionPropertyTable::add($fields);
			}
		}
	}

	/**
	 * @param int $propertyId
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Exception
	 */
	protected function deleteRootSmartFilter($propertyId)
	{
		$getList = SectionPropertyTable::getList(array(
			'filter' => array(
				'PROPERTY_ID' => $propertyId,
				'SECTION_ID' => 0,
			),
			'select' => array(
				'IBLOCK_ID',
				'PROPERTY_ID',
				'SECTION_ID',
			),
		));
		while ($record = $getList->fetch())
		{
			SectionPropertyTable::delete($record);
		}
	}

	protected function createInner(Record $record)
	{
		$fields = $this->recordToArray($record);
		$propertyObject = new \CIBlockProperty();
		$propertyId = $propertyObject->add($fields);
		if ($propertyId)
		{
			$this->updateSmartFilter($fields["IBLOCK_ID"], $propertyId, $fields);

			return RecordId::createNumericId($propertyId);
		}
		else
		{
			throw new \Exception(ExceptionText::getLastError($propertyObject));
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$propertyObject = new \CIBlockProperty();
		if ($id && !$propertyObject->delete($id->getValue()))
		{
			throw new \Exception(ExceptionText::getUnknown());
		}
	}

	public function setXmlId($id, $xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$propertyObject = new \CIBlockProperty();
		$isUpdated = $propertyObject->update($id->getValue(), array('XML_ID' => $fields[1]));
		if (!$isUpdated)
		{
			throw new \Exception(ExceptionText::getLastError($propertyObject));
		}
	}

	public function getXmlId($id)
	{
		$xmlId = '';
		$property = PropertyTable::getList(array(
			'filter' => array('=ID' => $id->getValue()),
			'select' => array(
				'ID',
				'XML_ID',
				'IBLOCK_XML_ID' => 'IBLOCK.XML_ID',
			),
		))->fetch();
		if ($property)
		{
			if ($property['XML_ID'] && $property['IBLOCK_XML_ID'])
			{
				$xmlId = $property['IBLOCK_XML_ID'] . static::XML_ID_SEPARATOR . $property['XML_ID'];
			}
		}

		return $xmlId;
	}

	public function findRecord($xmlId)
	{
		$id = null;
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		if ($fields[0] && $fields[1])
		{
			$filter = array(
				'=IBLOCK.XML_ID' => $fields[0],
				'=XML_ID' => $fields[1],
			);
			$property = PropertyTable::getList(array('filter' => $filter))->fetch();
			if ($property)
			{
				$id = $this->createId($property['ID']);
			}
		}

		return $id;
	}

	public function validateXmlIdCustom($xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$isValid = (count($fields) == 2 && $fields[0] && $fields[1]);
		if (!$isValid)
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.INVALID_XML_ID'));
		}
	}

	public function getValidationXmlId($xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		return $fields[1];
	}
}