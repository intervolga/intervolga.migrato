<? namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Runtime;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\XmlIdProvider\IblockElementXmlIdProvider;

class Element extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new IblockElementXmlIdProvider($this);
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
			$record->addFields(array(
				"NAME" => $element["NAME"],
				"ACTIVE" => $element["ACTIVE"],
				"SORT" => $element["SORT"],
				"CODE" => $element["CODE"],
			));

			$dependency = clone $this->getDependency("IBLOCK_ID");
			$dependency->setValue(
				Iblock::getInstance()->getXmlIdProvider()->getXmlId(RecordId::createNumericId($element["IBLOCK_ID"]))
			);
			$record->setDependency("IBLOCK_ID", $dependency);

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
	 * @param Link[] $runtimeReferences
	 * @param array $property
	 * @param BaseData $instance
	 * @return Link
	 */
	protected function getPropertyLink($runtimeReferences, $property, $instance = null)
	{
		$valueElementId = RecordId::createNumericId($property["VALUE"]);
		$valueElementXmlId = $instance->getXmlIdProvider()->getXmlId($valueElementId);

		if($property["MULTIPLE"] == "Y" && $runtimeReferences[$property["XML_ID"]])
		{
			$link = $runtimeReferences[$property["XML_ID"]];
			$values = $link->isMultiple() ? $link->getValues() : array($link->getValue());
			$values[] = $valueElementXmlId;
			$link->setValues($values);
		} else
		{
			$link = new Link($instance, $valueElementXmlId);
		}
		// TODO сделать для множественного поля
		if($property["DESCRIPTION"])
		{
			$link->setDescription($property["DESCRIPTION"]);
		}
		return $link;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param array $element
	 */
	protected function addRuntime(Record $record, array $element)
	{
		$runtimeFields = array();
		$runtimeReferences = array();
        $runtimeDependencies = array();
		$properties = \CIBlockElement::GetProperty($element["IBLOCK_ID"], $element["ID"]);
		while ($property = $properties->fetch())
		{
			if (strlen($property["VALUE"]))
			{
				$link = null;
				if ($property["PROPERTY_TYPE"] == "F")
				{
					continue;
				}
				elseif ($property["PROPERTY_TYPE"] == "L") {
					$link = $this->getPropertyLink($runtimeReferences, $property, Enum::getInstance());
				}
				elseif ($property["PROPERTY_TYPE"] == "E")
				{
					$link = $this->getPropertyLink($runtimeReferences, $property, Element::getInstance());
				}
				elseif($property["PROPERTY_TYPE"] == "G")
				{
					$link = $this->getPropertyLink($runtimeReferences, $property, Section::getInstance());
				}
				else
				{
					$value = new Value($property["VALUE"]);
					$value->setDescription($property["DESCRIPTION"]);
					$runtimeFields[$property["XML_ID"]] = $value;
				}
				if($link)
				{
					if ($property["IS_REQUIRED"] == "Y")
					{
						$runtimeDependencies[$property["XML_ID"]] = $link;
					}
					else
					{
						$runtimeReferences[$property["XML_ID"]] = $link;
					}
				}
			}
		}
		if ($runtimeFields || $runtimeReferences)
		{
			$runtime = clone $this->getRuntime("PROPERTY");
			$runtime->setFields($runtimeFields);
			$runtime->setReferences($runtimeReferences);
			$runtime->setDependencies($runtimeDependencies);
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
			{
				$properties[$property->getValue()] = array(
					"VALUE" => $arField->getValue(),
					"DESCRIPTION" => $arField->getDescription()
				);
			}
		}
		return $properties;
	}

	/**
	 * @param Link[] $links
	 * @param string $IBlockId
	 *
	 * @return array properties
	 */
	private function getRuntimesLinks($properties, array $links, $IBlockId)
	{
		foreach($links as $xmlId => $link)
		{
			if($property = Property::getInstance()->findRecord($xmlId))
			{
				$rsProperty = \CIBlockProperty::GetByID($property->getValue(), $IBlockId);
				if($arProperty = $rsProperty->Fetch())
				{
					switch($arProperty["PROPERTY_TYPE"])
					{
						case "N":
							$properties[$property->getValue()] = $link->getValue();
							break;
						case "S":
							if(array_search($arProperty["USER_TYPE"], array("HTML", "video", "DateTime", "map_yandex", "map_google", "FileMan", "ElementXmlID")) !== false)
							{
								$properties[$property->getValue()] = $link->getValue();
							}
							break;
						case "L":
						case "G":
						case "E":
							$values = $link->isMultiple() ? $link->getIds() : $link->getId()->getValue();
							$properties[$property->getValue()] = $values;
							break;
					}
				}
			}
		}
		return $properties;
	}

	public function getIBlock(Record $record)
	{
		$iblock = $record->getDependency("IBLOCK_ID");
		if($iblock && $iblock->getId())
		{
            return $iblock->getId()->getValue();
		}
		else
		{
            return \CIBlockElement::GetIBlockByID($record->getId()->getValue());
		}
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsStrings();
		$IBlockId = $this->getIBlock($record);

		$fields["PROPERTY_VALUES"] = array();
		$rsProperties = \CIBlockElement::GetProperty($IBlockId, $record->getId()->getValue());
		while($arProperty = $rsProperties->Fetch())
		{
			if($arProperty["PROPERTY_TYPE"] != "F" )
			{
				$fields["PROPERTY_VALUES"][strval($arProperty["ID"])]= $arProperty["VALUE"];
			}
		}

		$fields["PROPERTY_VALUES"] = $this->getRuntimesFields($fields["PROPERTY_VALUES"],
			$record->getRuntime("PROPERTY"));

		$fields["PROPERTY_VALUES"] = $this->getRuntimesLinks($fields["PROPERTY_VALUES"],
			$record->getRuntime("PROPERTY")->getReferences(), $IBlockId);

		$fields["PROPERTY_VALUES"] = $this->getRuntimesLinks($fields["PROPERTY_VALUES"],
			$record->getRuntime("PROPERTY")->getDependencies(), $IBlockId);

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
		$fields["PROPERTY_VALUES"] = array();

		$fields["PROPERTY_VALUES"] = $this->getRuntimesFields($fields["PROPERTY_VALUES"],
			$record->getRuntime("PROPERTY"));

		$fields["PROPERTY_VALUES"] = $this->getRuntimesLinks($fields["PROPERTY_VALUES"],
			$record->getRuntime("PROPERTY")->getDependencies(), $fields["IBLOCK_ID"]);

		$elementObject = new \CIBlockElement();
		$elementId = $elementObject->add($fields);
		if ($elementId)
		{
			return $this->createId($elementId);
		}
		else
		{
			throw new \Exception(trim(strip_tags($elementObject->LAST_ERROR)));
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if ($id)
		{
			$elementObject = new \CIBlockElement();
			if (!$elementObject->delete($id->getValue()))
			{
				throw new \Exception("Unknown error");
			}
		}
	}
}