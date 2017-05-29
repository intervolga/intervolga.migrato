<? namespace Intervolga\Migrato\Data\Module\Iblock;

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

			$smartFilterOptions = $this->getSmartFilterOptions($property["ID"]);

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
			$result["HAS_SMART_FILTER_SETTINGS"] = "Y";
			$result["SMART_FILTER"] = $property["SMART_FILTER"];
			$result["DISPLAY_TYPE"] = $property["DISPLAY_TYPE"];
			$result["DISPLAY_EXPANDED"] = $property["DISPLAY_EXPANDED"];
			$result["FILTER_HINT"] = $property["FILTER_HINT"];
		}
		else
		{
			$result = array(
				"HAS_SMART_FILTER_SETTINGS" => "N",
				"SMART_FILTER" => "",
				"DISPLAY_TYPE" => "",
				"DISPLAY_EXPANDED" => "",
				"FILTER_HINT" => "",
			);
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
		$propertyObject = new \CIBlockProperty();
		$isUpdated = $propertyObject->update($record->getId()->getValue(), $fields);
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($propertyObject->LAST_ERROR)));
		}
		if ($fields["IBLOCK_ID"])
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
				throw new \Exception("Not found IBlock " . $iblock->getValue());
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
		if ($fields['USER_TYPE'] == 'HTML' && $fields['DEFAULT_VALUE'])
		{
			$fields['DEFAULT_VALUE'] = unserialize($fields['DEFAULT_VALUE']);
		}

		return $fields;
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
		if ($property['HAS_SMART_FILTER_SETTINGS'])
		{
			$this->deleteSmartFilterSettings($propertyId);
			if ($property['HAS_SMART_FILTER_SETTINGS'] == 'Y')
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
	protected function deleteSmartFilterSettings($propertyId)
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
			throw new \Exception(trim(strip_tags($propertyObject->LAST_ERROR)));
		}
	}

	protected function deleteInner($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$propertyObject = new \CIBlockProperty();
		if ($id && !$propertyObject->delete($id->getValue()))
		{
			throw new \Exception("Unknown error");
		}
	}
}