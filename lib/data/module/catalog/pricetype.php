<? namespace Intervolga\Migrato\Data\Module\Catalog;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

class PriceType extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("catalog");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Catalog\\GroupTable");
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \Bitrix\Catalog\GroupTable::getList();
		while ($priceType = $getList->fetch())
		{
			$record = new Record($this);
			$record->setId(RecordId::createNumericId($priceType["ID"]));
			$record->setXmlId($priceType["XML_ID"]);
			$record->setFields(array(
				"NAME" => $priceType["NAME"],
				"BASE" => $priceType["BASE"],
				"SORT" => $priceType["SORT"],
			));
			$result[] = $record;
		}
		return $result;
	}
}