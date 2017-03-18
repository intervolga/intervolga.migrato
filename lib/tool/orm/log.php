<?namespace Intervolga\Migrato\Tool\Orm;

use Bitrix\Main\Entity\BooleanField;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Type\DateTime;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

class LogTable extends DataManager
{
	protected static $migrationTime = 0;
	public static function getTableName()
	{
		return "intervolga_migrato_log";
	}

	public static function getMap()
	{
		return array(
			new IntegerField("ID", array(
				"primary" => true,
			)),
			new DateTimeField("MIGRATION_DATETIME"),
			new DateTimeField("TIMESTAMP_X"),
			new StringField("MODULE_NAME"),
			new StringField("ENTITY_NAME"),
			new StringField("DATA_XML_ID"),
			new IntegerField("DATA_ID_NUM"),
			new StringField("DATA_ID_STR"),
			new StringField("DATA_ID_COMPLEX", array(
				"serialized" => true,
			)),
			new StringField("OPERATION"),
			new BooleanField("RESULT"),
			new StringField("COMMENT"),
		);
	}

	public static function getList(array $parameters = array())
	{
		if ($parameters["filter"]["DATA_ID_COMPLEX"])
		{
			$parameters["filter"]["DATA_ID_COMPLEX"] = serialize($parameters["filter"]["DATA_ID_COMPLEX"]);
		}
		if ($parameters["filter"]["=DATA_ID_COMPLEX"])
		{
			$parameters["filter"]["=DATA_ID_COMPLEX"] = serialize($parameters["filter"]["=DATA_ID_COMPLEX"]);
		}
		return parent::getList($parameters);
	}

	public static function getCount(array $filter = array())
	{
		if ($filter["DATA_ID_COMPLEX"])
		{
			$filter["DATA_ID_COMPLEX"] = serialize($filter["DATA_ID_COMPLEX"]);
		}
		if ($filter["=DATA_ID_COMPLEX"])
		{
			$filter["=DATA_ID_COMPLEX"] = serialize($filter["=DATA_ID_COMPLEX"]);
		}
		return parent::getCount($filter);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $operation
	 * @param \Exception|null $exception
	 */
	public static function logException(Record $record, $operation, \Exception $exception)
	{
		$log = static::prepareLog($record);
		$log["RESULT"] = false;
		$log["OPERATION"] = $operation;
		$log["COMMENT"] = get_class($exception) . ": " . $exception->getMessage();
		if ($exception->getCode())
		{
			$log["COMMENT"] .= "(" . $exception->getCode() . ")";
		}
		static::add($log);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $operation
	 */
	public static function log(Record $record, $operation)
	{
		$log = static::prepareLog($record);
		$log["RESULT"] = true;
		$log["OPERATION"] = $operation;
		static::add($log);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected static function prepareLog(Record $record)
	{
		if (!static::$migrationTime)
		{
			static::$migrationTime = time();
		}
		$log = array(
			"MIGRATION_DATETIME" => DateTime::createFromTimestamp(static::$migrationTime),
			"MODULE_NAME" => $record->getData()->getModule(),
			"ENTITY_NAME" => $record->getData()->getEntityName(),
			"DATA_XML_ID" => $record->getXmlId(),
		);
		if ($id = $record->getId())
		{
			if ($id->getType() == RecordId::TYPE_NUMERIC)
			{
				$log["DATA_ID_NUM"] = $id->getValue();
			}
			if ($id->getType() == RecordId::TYPE_STRING)
			{
				$log["DATA_ID_STR"] = $id->getValue();
			}
			if ($id->getType() == RecordId::TYPE_COMPLEX)
			{
				$log["DATA_ID_COMPLEX"] = $id->getValue();
			}
		}
		return $log;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $operation
	 * @param string $error
	 *
	 * @throws \Exception
	 */
	public static function logError(Record $record, $operation, $error)
	{
		$log = static::prepareLog($record);
		$log["RESULT"] = false;
		$log["OPERATION"] = $operation;
		$log["COMMENT"] = $error;
		static::add($log);
	}

	/**
	 * @return \Bitrix\Main\DB\Result
	 */
	public static function deleteAll()
	{
		$entity = static::getEntity();
		$connection = $entity->getConnection();

		$tableName = $entity->getDBTableName();

		$sql = "DELETE FROM $tableName";
		return $connection->query($sql);
	}
}