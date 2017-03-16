<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\XmlIdProvider\EnumXmlIdProvider;

class Enum extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new EnumXmlIdProvider($this);
	}

	public function getFilesSubdir()
	{
		return "/type/iblock/property/";
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = PropertyEnumerationTable::getList();
		while ($enum = $getList->fetch())
		{
			$record = new Record($this);
			$record->setXmlId($enum["XML_ID"]);
			$record->setId(RecordId::createNumericId($enum["ID"]));
			$record->setFields(array(
				"VALUE" => $enum["VALUE"],
				"DEF" => $enum["DEF"],
				"SORT" => $enum["SORT"],
			));

			$dependency = clone $this->getDependency("PROPERTY_ID");
			$dependency->setValue(
				Property::getInstance()->getXmlIdProvider()->getXmlId(RecordId::createNumericId($enum["PROPERTY_ID"]))
			);
			$record->addDependency("PROPERTY_ID", $dependency);

			$result[] = $record;
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			"PROPERTY_ID" => new Link(Property::getInstance()),
		);
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsStrings();

		if($propertyId = $record->getDependency("PROPERTY_ID")->getId())
		{
			$fields["PROPERTY_ID"] = $propertyId->getValue();
			$enumObject = new \CIBlockPropertyEnum();
			$isUpdated = $enumObject->Update($record->getId()->getValue(), $fields);
			if (!$isUpdated)
			{
				throw new \Exception("Unknown error");
			}
		}
	}

	public function create(Record $record)
	{
		$fields = $record->getFieldsStrings();
		if($propertyId = $record->getDependency("PROPERTY_ID")->getId())
		{
			$fields["PROPERTY_ID"] = $propertyId->getValue();
			$fields["XML_ID"] = $record->getXmlId();

			$enumObject = new \CIBlockPropertyEnum();
			$enumId = $enumObject->add($fields);
			// TODO Ошибка с добавлением
			if ($enumId)
			{
				return $this->createId($enumId);
			}
			else
			{
				throw new \Exception("Unknown error");
			}
		}
		else
			throw new \Exception("Creating enum: not found property for record " . $record->getXmlId());
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if (!\CIBlockPropertyEnum::Delete($id))
		{
			throw new \Exception("Unknown error");
		}
	}
}