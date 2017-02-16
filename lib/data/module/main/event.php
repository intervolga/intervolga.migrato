<? namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataRecordId;
use Intervolga\Migrato\Tool\DataLink;
use Intervolga\Migrato\Tool\XmlIdProviders\UfXmlIdProvider;

class Event extends BaseData
{
	const DEPENDENCY_EVENT_NAME = "EVENT_NAME";

	public function __construct()
	{
		$this->xmlIdProvider = new UfXmlIdProvider($this);
	}

	public function getFilesSubdir()
	{
		return "/eventtype/";
	}

	public function getFromDatabase(array $filter = array())
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

			$dependency = clone $this->getDependency(static::DEPENDENCY_EVENT_NAME);
			$dependency->setXmlId($this->getEventTypeXmlId($message["EVENT_NAME"]));
			$record->addDependency(static::DEPENDENCY_EVENT_NAME, $dependency);

			if ($record->getDependencies())
			{
				$result[$message["ID"]] = $record;
			}
		}
		return array_values($result);
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	protected function getEventTypeXmlId($name)
	{
		static $eventTypes = array();
		if (!$eventTypes)
		{
			$eventTypes = EventType::getInstance()->getFromDatabase();
		}
		foreach ($eventTypes as $eventType)
		{
			/**
			 * @var DataRecord $eventType
			 */
			if ($eventType->getField("EVENT_NAME") == $name)
			{
				return $eventType->getXmlId();
			}
		}

		return "";
	}

	public function restoreDependenciesFromFile(array $dependencies)
	{
		/**
		 * @var array|DataLink[] $dependencies
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
	 * @return array|DataLink[]
	 */
	public function getDependencies()
	{
		return array(
			static::DEPENDENCY_EVENT_NAME => new DataLink(
				EventType::getInstance(),
				"",
				"EVENT_NAME"
			),
		);
	}

	/**
	 * @param DataRecord $record
	 */
	public function update(DataRecord $record)
	{
		// TODO: Implement update() method.
	}

	/**
	 * @param DataRecord $record
	 */
	public function create(DataRecord $record)
	{
		// TODO: Implement create() method.
	}

	/**
	 * @param $xmlId
	 */
	public function delete($xmlId)
	{
		// TODO: Implement delete() method.
	}
}