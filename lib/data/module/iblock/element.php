<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Runtime;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Data\Values;
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
		$properties = \CIBlockElement::GetProperty($element["IBLOCK_ID"], $element["ID"]);
		while ($property = $properties->fetch())
		{
			if (strlen($property["VALUE"]))
			{
				$value = new Value($property["VALUE"]);
				$value->setDescription($property["DESCRIPTION"]);
				$runtimeFields[$property["XML_ID"]] = new Values($value);
			}
		}
		if ($runtimeFields)
		{
			$runtime = clone $this->getRuntime("PROPERTY");
			$runtime->setFields($runtimeFields);
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
}