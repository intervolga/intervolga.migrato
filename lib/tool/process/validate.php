<?namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Intervolga\Migrato\Tool\XmlIdValidateError;

class Validate extends BaseProcess
{
	protected static $allXmlIds = array();

	public static function run()
	{
		parent::run();
		static::validate();
		static::finalReport();
	}

	/**
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError[]
	 * @throws \Exception
	 */
	public static function validate()
	{
		static::$step = __FUNCTION__;
		static::reportSeparator();
		static::report(static::$step);

		$result = array();
		$configDataClasses = Config::getInstance()->getDataClasses();
		$dataClasses = static::recursiveGetDependentDataClasses($configDataClasses);
		foreach ($dataClasses as $data)
		{
			$filter = Config::getInstance()->getDataClassFilter($data);
			if (!$data->isXmlIdFieldExists())
			{
				$data->createXmlIdField();
			}
			$result = array_merge($result, static::validateData($data, $filter));
		}

		static::reportStep(static::$step);
		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError[]
	 */
	protected static function validateData(BaseData $dataClass, array $filter = array())
	{
		$errors = array();
		$records = $dataClass->getList($filter);
		static::$allXmlIds = array();
		foreach ($records as $record)
		{
			$errors = array_merge($errors, static::getRecordXmlIdErrors($record));
		}

		return $errors;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError|null
	 */
	protected static function getRecordXmlIdErrors(Record $record)
	{
		$errors = array();
		$errorType = 0;
		if ($record->getXmlId())
		{
			if (static::isValidXmlId($record->getXmlId()))
			{
				if (!in_array($record->getXmlId(), static::$allXmlIds))
				{
					static::$allXmlIds[] = $record->getXmlId();
					if (static::isSimpleXmlId($record->getXmlId()))
					{
						$errorType = XmlIdValidateError::TYPE_SIMPLE;
					}
				}
				else
				{
					$errorType = XmlIdValidateError::TYPE_REPEAT;
				}
			}
			else
			{
				$errorType = XmlIdValidateError::TYPE_INVALID;
			}
		}
		else
		{
			$errorType = XmlIdValidateError::TYPE_EMPTY;
		}
		if ($errorType)
		{
			$errors[] = new XmlIdValidateError($record->getData(), $errorType, $record->getId(), $record->getXmlId());
			LogTable::add(array(
				"RECORD" => $record,
				"OPERATION" => "validate",
				"COMMENT" => XmlIdValidateError::typeToString($errorType),
				"STEP" => static::$step,
				"RESULT" => false,
			));
		}
		else
		{
			LogTable::add(array(
				"RECORD" => $record,
				"OPERATION" => "validate",
				"STEP" => static::$step,
			));
		}
		return $errors;
	}

	/**
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	protected static function isValidXmlId($xmlId)
	{
		$matches = array();
		return !!preg_match_all("/^[a-z0-9\-_#.]+$/i", $xmlId, $matches);
	}

	/**
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	protected static function isSimpleXmlId($xmlId)
	{
		return is_numeric($xmlId);
	}
}