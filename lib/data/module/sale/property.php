<?namespace Intervolga\Migrato\Data\Module\Sale;

use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\OrderPropsTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\UfXmlIdProvider;

class Property extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("sale");
		$this->xmlIdProvider = new UfXmlIdProvider($this);
	}

	public function getFilesSubdir()
	{
		return "/persontype/propertygroup/";
	}

	public function getDependencies()
	{
		return array(
			"PERSON_TYPE_ID" => new Link(PersonType::getInstance()),
			"PROPS_GROUP_ID" => new Link(PropertyGroup::getInstance()),
		);
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$result = array();
		$getList = OrderPropsTable::getList();
		while ($property = $getList->fetch())
		{
			$record = new Record($this);
			$id = RecordId::createNumericId($property["ID"]);
			$record->setId($id);
			$record->setXmlId(
				$this->getXmlIdProvider()->getXmlId($id)
			);
			$record->setFields(array(
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
			$this->addSettings($record, $property["SETTINGS"]);
			$this->addDependencies($record, $property);
			$result[] = $record;
		}

		return $result;
	}

	/**
	 * @param Record $record
	 * @param string[] $settings
	 */
	protected function addSettings(Record $record, array $settings)
	{
		foreach ($settings as $name => $value)
		{
			$record->setField("SETTINGS.$name", $value);
		}
	}

	/**
	 * @param Record $record
	 * @param array $property
	 */
	protected function addDependencies(Record $record, array $property)
	{
		$link = clone $this->getDependency("PERSON_TYPE_ID");
		$personTypeId = RecordId::createNumericId($property["PERSON_TYPE_ID"]);
		$personTypeXmlId = PersonType::getInstance()->getXmlIdProvider()->getXmlId($personTypeId);
		$link->setValue($personTypeXmlId);
		$record->addDependency("PERSON_TYPE_ID", $link);

		$link = clone $this->getDependency("PROPS_GROUP_ID");
		$propertyGroupId = RecordId::createNumericId($property["PROPS_GROUP_ID"]);
		$propertyGroupXmlId = PropertyGroup::getInstance()->getXmlIdProvider()->getXmlId($propertyGroupId);
		$link->setValue($propertyGroupXmlId);
		$record->addDependency("PROPS_GROUP_ID", $link);
	}
}