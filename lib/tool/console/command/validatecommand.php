<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\XmlIdValidateError;

Loc::loadMessages(__FILE__);

class ValidateCommand extends BaseCommand
{
	protected function configure()
	{
		parent::configure();
		$this
			->setName('validate')
			->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.VALIDATE_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_VALIDATE'));

		$result = array();
		$configDataClasses = Config::getInstance()->getDataClasses();

		$dataClasses = $this->recursiveGetDependentDataClasses($configDataClasses);
		foreach ($dataClasses as $data)
		{
			if (Loader::includeModule($data->getModule()))
			{
				$filter = Config::getInstance()->getDataClassFilter($data);
				if (!$data->isXmlIdFieldExists())
				{
					$data->createXmlIdField();
				}
				$result = array_merge($result, $this->validateData($data, $filter));
			}
			else
			{
				if (in_array($data, $configDataClasses))
				{
					$error = Loc::getMessage(
						'INTERVOLGA_MIGRTO.CONFIG_MODULE_NOT_INSTALLED',
						array(
							'#MODULE#' => $data->getModule(),
						)
					);
				}
				else
				{
					$error = Loc::getMessage(
						'INTERVOLGA_MIGRTO.DEPENDANT_MODULE_NOT_INSTALLED',
						array(
							'#MODULE#' => $data->getModule(),
						)
					);
				}
				throw new LoaderException($error);
			}
		}
		$this->reportStepLogs();
		$this->report(self::getValidateMessage("warning"), "warning");
		$this->report(INTERVOLGA_MIGRATO_TABLE_PATH
			. "?set_filter=Y&adm_filter_applied=0&table_name=intervolga_migrato_log&find=0&find_type=RESULT&lang=" . LANGUAGE_ID);

		return $result;
	}

	protected $allXmlIds = array();

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError[]
	 */
	protected function validateData(BaseData $dataClass, array $filter = array())
	{
		$errors = array();
		$records = $dataClass->getList($filter);
		$this->allXmlIds = array();
		foreach ($records as $record)
		{
			$errors = array_merge($errors, $this->getRecordXmlIdErrors($record));
		}

		return $errors;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError[]|null
	 */
	protected function getRecordXmlIdErrors(Record $record)
	{
		$errors = array();
		$errorType = 0;
		if ($record->getXmlId())
		{
			if ($this->isValidXmlId($record->getXmlId()))
			{
				if (!in_array($record->getXmlId(), $this->allXmlIds))
				{
					$this->allXmlIds[] = $record->getXmlId();
					if ($this->isSimpleXmlId($record->getXmlId()))
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
			$this->addLog(array(
				"RECORD" => $record,
				"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_VALIDATE'),
				"COMMENT" => XmlIdValidateError::typeToString($errorType),
				"STEP" => $this->step,
				"RESULT" => false,
			));
		}
		else
		{
			$this->addLog(array(
				"RECORD" => $record,
				"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_VALIDATE'),
				"STEP" => $this->step,
			));
		}
		return $errors;
	}

	/**
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	protected function isValidXmlId($xmlId)
	{
		$matches = array();
		return !!preg_match_all("/^[a-z0-9\-_#.]+$/i", $xmlId, $matches);
	}

	/**
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	protected function isSimpleXmlId($xmlId)
	{
		return is_numeric($xmlId);
	}

	/**
	 * @param string $message
	 * @return string
	 */
	protected function getValidateMessage($message)
	{
		return Loc::getMessage("INTERVOLGA_MIGRATO.VALIDATE." . strtoupper($message));
	}
}