<?namespace Intervolga\Migrato\Data\Module\Sale;

use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\OrderPropsTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\XmlIdProvider\TableXmlIdProvider;

class Property extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("sale");
		$this->xmlIdProvider = new TableXmlIdProvider($this);
	}

	public function getFilesSubdir()
	{
		return "/persontype/propertygroup/";
	}

	public function getDependencies()
	{
		return array(
			"PERSON_TYPE_ID" => new Link(PersonType::getInstance()),
		);
	}

	public function getReferences()
	{
		return array(
			"PROPS_GROUP_ID" => new Link(PropertyGroup::getInstance()),
		);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = OrderPropsTable::getList();
		while ($property = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($property["ID"]);
			$record->setId($id);
			$record->setXmlId(
				$this->getXmlId($id)
			);
			$record->addFieldsRaw(array(
				"NAME" => $property["NAME"],
				"TYPE" => $property["TYPE"],
				"REQUIRED" => $property["REQUIRED"],
				"DEFAULT_VALUE" => $property["DEFAULT_VALUE"],
				"SORT" => $property["SORT"],
				"USER_PROPS" => $property["USER_PROPS"],
				"IS_LOCATION" => $property["IS_LOCATION"],
				"DESCRIPTION" => $property["DESCRIPTION"],
				"IS_EMAIL" => $property["IS_EMAIL"],
				"IS_PROFILE_NAME" => $property["IS_PROFILE_NAME"],
				"IS_PAYER" => $property["IS_PAYER"],
				"IS_LOCATION4TAX" => $property["IS_LOCATION4TAX"],
				"IS_FILTERED" => $property["IS_FILTERED"],
				"CODE" => $property["CODE"],
				"IS_ZIP" => $property["IS_ZIP"],
				"IS_PHONE" => $property["IS_PHONE"],
				"IS_ADDRESS" => $property["IS_ADDRESS"],
				"ACTIVE" => $property["ACTIVE"],
				"UTIL" => $property["UTIL"],
				"INPUT_FIELD_LOCATION" => $property["INPUT_FIELD_LOCATION"],
				"MULTIPLE" => $property["MULTIPLE"],
			));
			$record->addFieldsRaw(Value::treeToList($property["SETTINGS"], "SETTINGS"));
			$this->addLinks($record, $property);
			$result[] = $record;
		}

		return $result;
	}

	/**
	 * @param Record $record
	 * @param array $property
	 */
	protected function addLinks(Record $record, array $property)
	{
		$link = clone $this->getDependency("PERSON_TYPE_ID");
		$personTypeId = PersonType::getInstance()->createId($property["PERSON_TYPE_ID"]);
		$personTypeXmlId = PersonType::getInstance()->getXmlId($personTypeId);
		$link->setValue($personTypeXmlId);
		$record->setDependency("PERSON_TYPE_ID", $link);

		$link = clone $this->getReference("PROPS_GROUP_ID");
		$propertyGroupId = PropertyGroup::getInstance()->createId($property["PROPS_GROUP_ID"]);
		$propertyGroupXmlId = PropertyGroup::getInstance()->getXmlId($propertyGroupId);
		$link->setValue($propertyGroupXmlId);
		$record->setReference("PROPS_GROUP_ID", $link);
	}

	public function update(Record $record)
	{
		$array = $this->recordToArray($record);
		$object = new \CSaleOrderProps();
		$updateResult = $object->update($record->getId()->getValue(), $array);
		if (!$updateResult)
		{
			global $APPLICATION;
			throw new \Exception($APPLICATION->getException()->getString());
		}
	}

	public function create(Record $record)
	{
		$array = $this->recordToArray($record);
		$object = new \CSaleOrderProps();
		$id = $object->add($array);
		if ($id)
		{
			return $this->createId($id);
		}
		else
		{
			global $APPLICATION;
			throw new \Exception($APPLICATION->getException()->getString());
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if ($id)
		{
			$object = new \CSaleOrderProps();
			if (!$object->delete($id->getValue()))
			{
				throw new \Exception("Unknown error");
			}
		}
	}

	protected function recordToArray(Record $record)
	{
		$array = $record->getFieldsRaw(array("SETTINGS"));
		if ($link = $record->getDependency("PERSON_TYPE_ID"))
		{
			if ($id = $link->findId())
			{
				$array["PERSON_TYPE_ID"] = $id->getValue();
			}
		}
		if ($link = $record->getReference("PROPS_GROUP_ID"))
		{
			if ($idObject = $link->findId())
			{
				$array["PROPS_GROUP_ID"] = $idObject->getValue();
			}
			else
			{
				$array["PROPS_GROUP_ID"] = false;
			}
		}

		return $array;
	}
}