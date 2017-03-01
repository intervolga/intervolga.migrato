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

	public function update(Record $record)
	{
		$fields = $record->getFieldsStrings();
		$properties = array();

		// TODO По моему сильно тормозит каждый раз делать такую вот выборку...
		// TODO получить все свойства элемента
		foreach($record->getRuntime("PROPERTY")->getFields() as $xmlId => $arFiled)
		{
			if($property = Property::getInstance()->findRecord($xmlId))
				$properties[$property->getValue()] = $arFiled->getValue();
		}
		foreach($record->getRuntime("PROPERTY")->getReferences() as $xmlId => $arReference)
		{
			dump($arReference);
			if(($property = Property::getInstance()->findRecord($xmlId)))
			{
				dump($property->getValue());
				die();
				//$properties[$property->getValue()] = $arValue->getValue();
			}
		}
		if(count($properties))
			$fields["PROPERTY_VALUES"] = $properties;

		//$el = new \CIBlockElement();
		//$res = $el->Update($record->getId()->getValue(), $arLoadProductArray);
		parent::update($record);
	}

	public function create(Record $record)
	{
		$dependency = null;
		$fields = $record->getFieldsStrings();
		if($dependencies = $record->getDependencies())
		{
			$fields["IBLOCK_ID"] = $dependencies["IBLOCK_ID"]->getValue();
		}

		foreach($record->getRuntime("PROPERTY")->getFields() as $xmlId => $arFiled)
		{
			if($property = Property::getInstance()->findRecord($xmlId))
				$properties[$property->getValue()] = $arFiled->getValue();
		}

		// TODO referencies
		$elementObject = new \CIBlockElement();
		$elementId = $elementObject->add($fields);
		parent::create($record); // TODO: Change the autogenerated stub
		/*if ($elementId)
		{
			$id = RecordId::createNumericId($elementId);
			$this->getXmlIdProvider()->setXmlId($id, $record->getXmlId());

			return $id;
		}
		else
		{
			throw new \Exception(trim(strip_tags($elementObject->LAST_ERROR)));
		}*/
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