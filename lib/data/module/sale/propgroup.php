<?namespace Intervolga\Migrato\Data\Module\Sale;

use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\OrderPropsGroupTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\UfXmlIdProvider;

class PropGroup extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("sale");
		$this->xmlIdProvider = new UfXmlIdProvider($this);
	}

	public function getFilesSubdir()
	{
		return "/persontype/";
	}

	public function getDependencies()
	{
		return array(
			"PERSON_TYPE_ID" => new Link(PersonType::getInstance()),
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
		$getList = OrderPropsGroupTable::getList();
		while ($propGroup = $getList->fetch())
		{
			$record = new Record();
			$id = RecordId::createNumericId($propGroup["ID"]);
			$record->setId($id);
			$record->setXmlId(
				$this->getXmlIdProvider()->getXmlId($id)
			);
			$record->setFields(array(
				"NAME" => $propGroup["NAME"],
				"SORT" => $propGroup["SORT"],
			));

			$link = clone $this->getDependency("PERSON_TYPE_ID");
			$personTypeXmlId = PersonType::getInstance()->getXmlIdProvider()->getXmlId(
				RecordId::createNumericId($propGroup["PERSON_TYPE_ID"])
			);
			$link->setValue($personTypeXmlId);
			$record->addDependency("PERSON_TYPE_ID", $link);

			$result[] = $record;
		}

		return $result;
	}
}