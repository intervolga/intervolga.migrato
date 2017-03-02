<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

class Enum extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Iblock\\PropertyEnumerationTable");
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
		$dependency = $record->getDependency("PROPERTY_ID");
		if($propertyId = Property::getInstance()->findRecord($dependency->getValue()))
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
		$dependency = $record->getDependency("PROPERTY_ID");
		if($propertyId = Property::getInstance()->findRecord($dependency->getValue()))
		{
			$fields["PROPERTY_ID"] = $propertyId->getValue();
			$enumObject = new \CIBlockPropertyEnum();
			$enumId = $enumObject->add($fields);
			if ($enumId)
			{
				$id = RecordId::createNumericId($enumId);
				$this->getXmlIdProvider()->setXmlId($id, $record->getXmlId());

				return $id;
			}
			else
			{
				throw new \Exception("Unknown error");
			}
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if (!\CIBlockPropertyEnum::Delete($id->getValue()))
		{
			throw new \Exception("Unknown error");
		}
	}
}