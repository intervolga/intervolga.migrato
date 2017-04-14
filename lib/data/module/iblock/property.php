<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionPropertyTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

class Property extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Iblock\\PropertyTable");
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
			$record->setXmlId($property["XML_ID"]);
			$record->setId(RecordId::createNumericId($property["ID"]));

			$smartFilterOptions = $this->getSmartFilterOptions($property["IBLOCK_ID"], $property["ID"]);

			$record->addFieldsRaw(array_merge(
				$smartFilterOptions,
				array (
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
	 * @param $iblockId
	 * @param $propertyId
	 *
	 * @return
	 */
	private function getSmartFilterOptions($iblockId, $propertyId)
	{
		$rsPropertySmartFilter = SectionPropertyTable::getList(array(
			"filter" => array("IBLOCK_ID" => $iblockId, "PROPERTY_ID" => $propertyId)
		));

		$properties = array();
		if($prop = $rsPropertySmartFilter->fetch())
		{
			$properties["SECTION_ID"]       = $prop["SECTION_ID"];
			$properties["SMART_FILTER"]     = $prop["SMART_FILTER"];
			$properties["DISPLAY_TYPE"]     = $prop["DISPLAY_TYPE"];
			$properties["DISPLAY_EXPANDED"] = $prop["DISPLAY_EXPANDED"];
			$properties["FILTER_HINT"]      = $prop["FILTER_HINT"];
		}
		return $properties;
	}

	public function updateSmartFilterOptions($iblockId, $propertyId, $options)
	{
		$smartfilterOptions = array(
			"SMART_FILTER"     => $options["SMART_FILTER"],
			"DISPLAY_TYPE"     => $options["DISPLAY_TYPE"],
			"DISPLAY_EXPANDED" => $options["DISPLAY_EXPANDED"],
			"FILTER_HINT"      => $options["FILTER_HINT"]
		);

		$id = array("IBLOCK_ID" => $iblockId, "PROPERTY_ID" => $propertyId);
		if($options["SECTION_ID"])
		{
			$id["SECTION_ID"] = intval($options["SECTION_ID"]);
		}
		$result = SectionPropertyTable::update($id, $smartfilterOptions);
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

	public function getIBlock(Record $record)
	{
		$iblockId = null;
		if($iblock = $record->getDependency("IBLOCK_ID"))
		{
			if($iblock->getId())
			{
				$iblockId = $iblock->getId()->getValue();
			}
			else
				throw new \Exception("Not found IBlock " . $iblock->getValue());
		}
		elseif($record->getId())
		{
			$rsProperty = \CIBlockProperty::GetByID($record->getId()->getValue());
			if($arProperty = $rsProperty->Fetch())
				$iblockId = intval($arProperty["IBLOCK_ID"]);
		}
		if(!$iblockId)
		{
			throw new \Exception("Not found IBlock for the element " . $record->getXmlId());
		}
		return $iblockId;
	}

	public function update(Record $record)
	{
		$fields = $this->recordToArray($record);
		$propertyObject = new \CIBlockProperty();
		$isUpdated = $propertyObject->update($record->getId()->getValue(), $fields);
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($propertyObject->LAST_ERROR)));
		}
		$this->updateSmartFilterOptions($fields["IBLOCK_ID"], $record->getId()->getValue(), $fields);
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
		$fields["IBLOCK_ID"] = $this->getIBlock($record);
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

		return $fields;
	}

	public function create(Record $record)
	{
		$fields = $this->recordToArray($record);
		$propertyObject = new \CIBlockProperty();
		$propertyId = $propertyObject->add($fields);
		if ($propertyId)
		{
			$this->updateSmartFilterOptions($fields["IBLOCK_ID"], $propertyId, $fields);
			return RecordId::createNumericId($propertyId);
		}
		else
		{
			throw new \Exception(trim(strip_tags($propertyObject->LAST_ERROR)));
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$propertyObject = new \CIBlockProperty();
		if ($id && !$propertyObject->delete($id->getValue()))
		{
			throw new \Exception("Unknown error");
		}
	}
}