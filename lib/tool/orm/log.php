<?namespace Intervolga\Migrato\Tool\Orm;

use Bitrix\Main\Entity\BooleanField;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Type\DateTime;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdValidateError;

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
			new StringField("STEP"),
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

	public static function add(array $data)
	{
		if (!array_key_exists("RESULT", $data))
		{
			$data["RESULT"] = true;
		}
		if (!array_key_exists("MIGRATION_DATETIME", $data))
		{
			$data["MIGRATION_DATETIME"] = static::getMigrationDateTime();
		}
		if ($data["XML_ID_ERROR"])
		{
			$data = array_merge($data, static::xmlIdErrorToLog($data["XML_ID_ERROR"]));
			unset($data["XML_ID_ERROR"]);
		}
		if ($data["RECORD"])
		{
			$data = array_merge($data, static::recordToLog($data["RECORD"]));
			unset($data["RECORD"]);
		}
		if ($data["EXCEPTION"])
		{
			$data = array_merge($data, static::exceptionToLog($data["EXCEPTION"]));
			unset($data["EXCEPTION"]);
		}
		parent::add($data);
	}

	/**
	 * @return DateTime
	 */
	protected static function getMigrationDateTime()
	{
		if (!static::$migrationTime)
		{
			static::$migrationTime = time();
		}
		return DateTime::createFromTimestamp(static::$migrationTime);
	}

	/**
	 * @param \Exception $exception
	 *
	 * @return array
	 */
	protected static function exceptionToLog(\Exception $exception)
	{
		$log = array(
			"RESULT" => false,
			"COMMENT" => get_class($exception) . ": " . $exception->getMessage(),
		);
		if ($exception->getCode())
		{
			$log["COMMENT"] .= " (" . $exception->getCode() . ")";
		}
		$log["COMMENT"] .= "\n\n" . $exception->getTraceAsString();
		return $log;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected static function recordToLog(Record $record)
	{
		$log = static::prepareLog();
		$log["DATA_XML_ID"] = $record->getXmlId();
		$log = array_merge($log, static::dataToLog($record->getData()));
		if ($id = $record->getId())
		{
			$log = array_merge($log, static::idToLog($id));
		}
		return $log;
	}

	/**
	 * @return array
	 */
	protected static function prepareLog()
	{
		if (!static::$migrationTime)
		{
			static::$migrationTime = time();
		}
		$log = array(
			"MIGRATION_DATETIME" => DateTime::createFromTimestamp(static::$migrationTime),
			"RESULT" => true,
		);
		return $log;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 *
	 * @return array
	 */
	protected static function dataToLog(BaseData $data)
	{
		return array(
			"MODULE_NAME" => $data->getModule(),
			"ENTITY_NAME" => $data->getEntityName(),
		);
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected static function idToLog(RecordId $id)
	{
		$log = array();
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
		return $log;
	}

	/**
	 * @param \Intervolga\Migrato\Tool\XmlIdValidateError $error
	 *
	 * @return array
	 */
	protected static function xmlIdErrorToLog(XmlIdValidateError $error)
	{
		$log = array();
		if ($error->getDataClass())
		{
			$log = array_merge($log, static::dataToLog($error->getDataClass()));
		}
		if ($error->getId())
		{
			$log = array_merge($log, static::idToLog($error->getId()));
		}
		$log["DATA_XML_ID"] = $error->getXmlId();
		return $log;
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