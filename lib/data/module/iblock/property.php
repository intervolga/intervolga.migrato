<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataRecordId;
use Intervolga\Migrato\Tool\DataLink;
use Intervolga\Migrato\Tool\XmlIdProviders\OrmXmlIdProvider;

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
			$record = new DataRecord($this);
			$record->setXmlId($property["XML_ID"]);
			$record->setId(DataRecordId::createNumericId($property["ID"]));
			$record->setFields(array(
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
				"USER_TYPE_SETTINGS" => $property["USER_TYPE_SETTINGS"],
				"HINT" => $property["HINT"],
			));

			$dependency = clone $this->getDependency("IBLOCK_ID");
			$dependency->setXmlId(
				Iblock::getInstance()->getXmlIdProvider()->getXmlId(DataRecordId::createNumericId($property["IBLOCK_ID"]))
			);
			$record->addDependency("IBLOCK_ID", $dependency);

			if ($property["LINK_IBLOCK_ID"])
			{
				$reference = clone $this->getReference("LINK_IBLOCK_ID");
				$reference->setXmlId(
					Iblock::getInstance()->getXmlIdProvider()->getXmlId(DataRecordId::createNumericId($property["LINK_IBLOCK_ID"]))
				);
				$record->addReference("LINK_IBLOCK_ID", $reference);
			}
			$result[] = $record;
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			"IBLOCK_ID" => new DataLink(Iblock::getInstance()),
		);
	}

	public function getReferences()
	{
		return array(
			"LINK_IBLOCK_ID" => new DataLink(Iblock::getInstance()),
		);
	}
}