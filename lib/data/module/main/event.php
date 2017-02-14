<? namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Mail\Internal\EventTypeTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataRecordId;
use Intervolga\Migrato\Tool\Dependency;
use Intervolga\Migrato\Tool\XmlIdProviders\UfXmlIdProvider;

class Event extends BaseData
{
	const DEPENDENCY_EVENT_NAME = "EVENT_NAME";

	public function __construct()
	{
		$this->xmlIdProvider = new UfXmlIdProvider($this);
	}

	public function getFromDatabase()
	{
		$result = array();
		$by = "ID";
		$order = "ASC";
		$getList = \CEventMessage::getList($by, $order);
		while ($message = $getList->fetch())
		{
			$record = new DataRecord();
			$id = DataRecordId::createNumericId($message["ID"]);
			$record->setXmlId($this->getXmlIdProvider()->getXmlId($id));
			$record->setId($id);
			$record->setFields(array(
				"LID" => $message["LID"],
				"ACTIVE" => $message["ACTIVE"],
				"EMAIL_FROM" => $message["EMAIL_FROM"],
				"EMAIL_TO" => $message["EMAIL_TO"],
				"SUBJECT" => $message["SUBJECT"],
				"MESSAGE" => $message["MESSAGE"],
				"BODY_TYPE" => $message["BODY_TYPE"],
				"SITE_TEMPLATE_ID" => $message["SITE_TEMPLATE_ID"],
			));

			// TODO getlist в цикле
			$eventTypeGetList = EventTypeTable::getList(array(
				"filter" => array(
					"=EVENT_NAME" => $message["EVENT_NAME"],
				),
				"select" => array("ID"),
			));
			while ($type = $eventTypeGetList->fetch())
			{
				$dependency = new Dependency(
					EventType::getInstance(),
					EventType::getInstance()->getXmlIdProvider()->getXmlId(DataRecordId::createNumericId($type["ID"])),
					"EVENT_NAME"
				);
				$record->addDependency(static::DEPENDENCY_EVENT_NAME, $dependency);
			}
			if ($record->getDependencies())
			{
				$result[$message["ID"]] = $record;
			}
		}
		return array_values($result);
	}

	protected function restoreDependenciesFromFile(array $dependencies)
	{
		/**
		 * @var array|Dependency[] $dependencies
		 */
		foreach ($dependencies as $key => $dependency)
		{
			if ($key == static::DEPENDENCY_EVENT_NAME)
			{
				$dependencies[$key]->setTargetData(EventType::getInstance());
				$dependencies[$key]->setToCustomField("EVENT_NAME");
			}
		}

		return $dependencies;
	}

	/**
	 * @param DataRecord $record
	 */
	protected function update(DataRecord $record)
	{
		// TODO: Implement update() method.
	}

	/**
	 * @param DataRecord $record
	 */
	protected function create(DataRecord $record)
	{
		// TODO: Implement create() method.
	}

	/**
	 * @param $xmlId
	 */
	protected function delete($xmlId)
	{
		// TODO: Implement delete() method.
	}
}