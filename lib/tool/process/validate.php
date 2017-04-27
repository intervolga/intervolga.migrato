<?namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Intervolga\Migrato\Tool\XmlIdValidateError;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Orm\ColorLog;

Loc::loadMessages(__FILE__);

class Validate extends BaseProcess
{
	protected static $allXmlIds = array();

	public static function run()
	{
		parent::run();
		static::validate();
		static::findUseNotClasses();
		static::finalReport();
	}

	/**
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError[]
	 * @throws \Exception
	 */
	public static function validate()
	{
		static::startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_VALIDATE'));

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

		static::reportStepLogs();
		static::report(self::getValidateMessage("warning"), "warning");
		static::report(INTERVOLGA_MIGRATO_TABLE_PATH
			. "?set_filter=Y&adm_filter_applied=0&table_name=intervolga_migrato_log&find=0&find_type=RESULT&lang=" . LANGUAGE_ID);
		return $result;
	}

	public static function findUseNotClasses()
	{
		static::startStep(ColorLog::getColoredString(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_FIND_SKIPPED')));
		$configDataClasses = Config::getInstance()->getDataClasses();
		$allConfigDataClasses = Config::getInstance()->getAllDateClasses();

		$configDataClassesString = array();
		foreach($configDataClasses as $conf)
		{
			$configDataClassesString[] = $conf->getModule() . ":" . $conf->getEntityName();
		}

		foreach($allConfigDataClasses as $conf)
		{
			$entity = static::getModuleMessage($conf->getModule()) . ": " . static::getEntityMessage($conf->getEntityName());
			if(!in_array($entity, $configDataClassesString))
			{
				static::report(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.DATA_NOT_USED',
						array(
							'#ENTITY#' => $entity
						)
					),
					'info'
				);
			}
		}
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
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError[]|null
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
				"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_VALIDATE'),
				"COMMENT" => XmlIdValidateError::typeToString($errorType),
				"STEP" => static::$step,
				"RESULT" => false,
			));
		}
		else
		{
			LogTable::add(array(
				"RECORD" => $record,
				"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_VALIDATE'),
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

	/**
	 * @param string $message
	 * @return string
	 */
	protected static function getValidateMessage($message)
	{
		return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE." . strtoupper($message));
	}
}