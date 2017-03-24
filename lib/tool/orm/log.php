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
		$log = static::recordToLog($record);
		$log = array_merge($log, static::exceptionToLog($exception));
		$log["OPERATION"] = $operation;
		static::add($log);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $operation
	 */
	public static function log(Record $record, $operation)
	{
		$log = static::recordToLog($record);
		$log["OPERATION"] = $operation;
		static::add($log);
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
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $operation
	 * @param string $error
	 *
	 * @throws \Exception
	 */
	public static function logError(Record $record, $operation, $error)
	{
		$log = static::recordToLog($record);
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

	/**
	 * @param \Intervolga\Migrato\Tool\XmlIdValidateError $error
	 * @param \Exception $exception
	 *
	 * @throws \Exception
	 */
	public static function logErrorFix(XmlIdValidateError $error, \Exception $exception = null)
	{
		$log = static::prepareLog();
		$log = array_merge($log, static::dataToLog($error->getDataClass()));
		if ($id = $error->getId())
		{
			$log = array_merge($log, static::idToLog($id));
		}
		if ($exception)
		{
			$log = array_merge($log, static::exceptionToLog($exception));
		}
		if ($xmlId = $error->getXmlId())
		{
			$log["DATA_XML_ID"] = $xmlId;
		}
		$log["OPERATION"] = "xmlid error fix";
		static::add($log);
	}
}