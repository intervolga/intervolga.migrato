<? namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProviders\UfXmlIdProvider;

class EventType extends BaseData
{
	public function __construct()
	{
		$this->xmlIdProvider = new UfXmlIdProvider($this);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CEventType::getList();
		while ($type = $getList->fetch())
		{
			$record = new Record($this);
			$id = RecordId::createNumericId($type["ID"]);
			$record->setXmlId($this->getXmlIdProvider()->getXmlId($id));
			$record->setId($id);
			$record->setFields(array(
				"LID" => $type["LID"],
				"EVENT_NAME" => $type["EVENT_NAME"],
				"NAME" => $type["NAME"],
				"DESCRIPTION" => $type["DESCRIPTION"],
				"SORT" => $type["SORT"],
			));
			$result[] = $record;
		}
		return $result;
	}
}