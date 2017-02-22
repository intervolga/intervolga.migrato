<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
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
		while ($property = $getList->fetch())
		{
			$record = new Record($this);
			$record->setXmlId($property["XML_ID"]);
			$record->setId(RecordId::createNumericId($property["ID"]));
			$record->setFields(array(
				"NAME" => $property["NAME"],
				"ACTIVE" => $property["ACTIVE"],
				"SORT" => $property["SORT"],
				"CODE" => $property["CODE"],
			));

			$dependency = clone $this->getDependency("IBLOCK_ID");
			$dependency->setXmlId(
				Iblock::getInstance()->getXmlIdProvider()->getXmlId(RecordId::createNumericId($property["IBLOCK_ID"]))
			);
			$record->addDependency("IBLOCK_ID", $dependency);

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
}