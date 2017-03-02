<? namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Runtime;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

class Element extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Iblock\\ElementTable");
	}

	public function getFilesSubdir()
	{
		return "/type/iblock/";
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = ElementTable::getList();
		while ($element = $getList->fetch())
		{
			$record = new Record($this);
			$record->setXmlId($element["XML_ID"]);
			$record->setId(RecordId::createNumericId($element["ID"]));
			$record->setFields(array(
				"NAME" => $element["NAME"],
				"ACTIVE" => $element["ACTIVE"],
				"SORT" => $element["SORT"],
				"CODE" => $element["CODE"],
			));

			$dependency = clone $this->getDependency("IBLOCK_ID");
			$dependency->setValue(
				Iblock::getInstance()->getXmlIdProvider()->getXmlId(RecordId::createNumericId($element["IBLOCK_ID"]))
			);
			$record->addDependency("IBLOCK_ID", $dependency);

			$this->addRuntime($record, $element);

			$result[] = $record;
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			"IBLOCK_ID" => new Link(Iblock::getInstance()),
		);
	}

	public function getRuntimes()
	{
		return array(
			"PROPERTY" => new Runtime(Property::getInstance()),
		);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param array $element
	 */
	protected function addRuntime(Record $record, array $element)
	{
		$runtimeFields = array();
		$runtimeReferences = array();
		$properties = \CIBlockElement::GetProperty($element["IBLOCK_ID"], $element["ID"]);
		while ($property = $properties->fetch())
		{
			if (strlen($property["VALUE"]))
			{
				if ($property["PROPERTY_TYPE"] == "F")
				{
					continue;
				}
				elseif ($property["PROPERTY_TYPE"] == "L")
				{
					$link = new Link(Enum::getInstance(), $property["VALUE_XML_ID"]);
					$link->setDescription($property["DESCRIPTION"]);
					$runtimeReferences[$property["XML_ID"]] = $link;
				}
				elseif ($property["PROPERTY_TYPE"] == "E")
				{
					$valueElementId = RecordId::createNumericId($property["VALUE"]);
					$valueElementXmlId = Element::getInstance()->getXmlIdProvider()->getXmlId($valueElementId);

					$link = new Link(Element::getInstance(), $valueElementXmlId);
					$link->setDescription($property["DESCRIPTION"]);
					$runtimeReferences[$property["XML_ID"]] = $link;
				}
				else
				{
					$value = new Value($property["VALUE"]);
					$value->setDescription($property["DESCRIPTION"]);
					$runtimeFields[$property["XML_ID"]] = $value;
				}
			}
		}
		if ($runtimeFields || $runtimeReferences)
		{
			$runtime = clone $this->getRuntime("PROPERTY");
			$runtime->setFields($runtimeFields);
			$runtime->setReferences($runtimeReferences);
			$record->setRuntime("PROPERTY", $runtime);
		}
	}

	public function findRecord($xmlId)
	{
		$parameters = array(
			"select" => array(
				"ID",
			),
			"filter" => array(
				"=XML_ID" => $xmlId,
			),
		);
		$element = ElementTable::getList($parameters)->fetch();
		if ($element["ID"])
		{
			return RecordId::createNumericId($element["ID"]);
		}
		else
		{
			return null;
		}
	}

	/**
	 * @param Runtime $runtime
	 *
	 * @return array properties
	 */
	private function getRuntimesFields($properties, Runtime $runtime)
	{
		foreach($runtime->getFields() as $xmlId => $arField)
		{
			if($property = Property::getInstance()->findRecord($xmlId))
				$properties[$property->getValue()] = $arField->getValue();
		}
		return $properties;
	}

	/**
	 * @param Runtime $runtime
	 * @param string $IBlockId
	 *
	 * @return array properties
	 */
	private function getRuntimesReferences($properties, Runtime $runtime, $IBlockId)
	{
		foreach($runtime->getReferences() as $xmlId => $arField)
		{
			if($property = Property::getInstance()->findRecord($xmlId))
			{
				$rsProperty = \CIBlockProperty::GetByID($property->getValue(), $IBlockId);
				if($arProperty = $rsProperty->Fetch())
				{
					switch($arProperty["PROPERTY_TYPE"])
					{
						case "N":
							$properties[$property->getValue()] = $arField->getValue();
							break;
						case "S":
							if(array_search($arProperty["USER_TYPE"], array("HTML", "video", "DateTime", "map_yandex", "map_google", "FileMan", "ElementXmlID")) !== false)
							{
								$properties[$property->getValue()] = $arField->getValue();
							}
							break;
						case "L":
							if($enumId = (Enum::getInstance()->findRecord($arField->getValue())))
							{
								$properties[$property->getValue()] = $enumId->getValue();
							}
							break;
						case "G":
							if($sectionId = (Section::getInstance()->findRecord($arField->getValue())))
							{
								$properties[$property->getValue()] = $sectionId->getValue();
							}
							break;
						case "E":
							if($elementId = (Element::getInstance()->findRecord($arField->getValue())))
							{
								$properties[$property->getValue()] = $elementId->getValue();
							}
							break;
					}
				}
			}
		}
		return $properties;
	}

	public function getIBlock(Record $record)
	{
		$iblockId = null;
		if($iblockIdXml = $record->getDependency("IBLOCK_ID"))
		{
			$iblockId = Iblock::getInstance()->findRecord($iblockIdXml->getValue())->getValue();
		}
		else
		{
			$rsSection = \CIBlockElement::GetByID($record->getId()->getValue());
			if($arSection = $rsSection->Fetch())
				$iblockId = intval($arSection["IBLOCK_ID"]);
		}
		if(!$iblockId)
		{
			throw new \Exception("Not found IBlock for the element " . $record->getId()->getValue());
		}
		return $iblockId;
	}

	public function generateProperties($iblockId, Record $record)
	{
		$properties = array();
		$rsProperties = \CIBlockElement::GetProperty($iblockId, $record->getId()->getValue());
		while($arProperty = $rsProperties->Fetch())
		{
			$properties[strval($arProperty["ID"])]= $arProperty["VALUE"];
		}

		$properties = $this->getRuntimesFields($properties, $record->getRuntime("PROPERTY"));

		$properties = $this->getRuntimesReferences($properties, $record->getRuntime("PROPERTY"), $iblockId);

		return $properties;
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsStrings();
		$IBlockId = $this->getIBlock($record);

		$properties = $this->generateProperties($IBlockId, $record);

		if(count($properties))
			$fields["PROPERTY_VALUES"] = $properties;

		$elementObject = new \CIBlockElement();
		$isUpdated = $elementObject->Update($record->getId()->getValue(), $fields);
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($elementObject->LAST_ERROR)));
		}
	}

	public function create(Record $record)
	{
		$fields = $record->getFieldsStrings();

		$fields["IBLOCK_ID"] = $this->getIBlock($record);

		$properties = $this->generateProperties($fields["IBLOCK_ID"], $record);

		if(count($properties))
			$fields["PROPERTY_VALUES"] = $properties;

		$elementObject = new \CIBlockElement();
		$elementId = $elementObject->add($fields);
		if ($elementId)
		{
			$id = RecordId::createNumericId($elementId);
			$this->getXmlIdProvider()->setXmlId($id, $record->getXmlId());

			return $id;
		}
		else
		{
			throw new \Exception(trim(strip_tags($elementObject->LAST_ERROR)));
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$elementObject = new \CIBlockType();
		if (!$elementObject->delete($id->getValue()))
		{
			throw new \Exception("Unknown error");
		}
	}
}