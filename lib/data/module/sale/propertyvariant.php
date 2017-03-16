<?namespace Intervolga\Migrato\Data\Module\Sale;

use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\OrderPropsVariantTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\XmlIdProvider\UfXmlIdProvider;

class PropertyVariant extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("sale");
		$this->xmlIdProvider = new UfXmlIdProvider($this, "ORDERPOPENUM");
	}

	public function getFilesSubdir()
	{
		return "/persontype/propertygroup/property/";
	}

	public function getDependencies()
	{
		return array(
			"ORDER_PROPS_ID" => new Link(Property::getInstance()),
		);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = OrderPropsVariantTable::getList();
		while ($variant = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($variant["ID"]);
			$record->setId($id);
			$record->setXmlId(
				$this->getXmlIdProvider()->getXmlId($id)
			);
			$record->addFieldsRaw(array(
				"NAME" => $variant["NAME"],
				"VALUE" => $variant["VALUE"],
				"SORT" => $variant["SORT"],
				"DESCRIPTION" => $variant["DESCRIPTION"],
			));

			$link = clone $this->getDependency("ORDER_PROPS_ID");
			$propId = Property::getInstance()->createId($variant["ORDER_PROPS_ID"]);
			$propXmlId = Property::getInstance()->getXmlIdProvider()->getXmlId($propId);
			$link->setValue($propXmlId);
			$record->setDependency("ORDER_PROPS_ID", $link);

			$result[] = $record;
		}

		return $result;
	}
}