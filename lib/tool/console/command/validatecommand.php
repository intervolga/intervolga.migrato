<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataTree\Builder;
use Intervolga\Migrato\Tool\XmlIdValidateError;

Loc::loadMessages(__FILE__);

class ValidateCommand extends BaseCommand
{
	protected $lastExecuteResult = array();
	protected $allXmlIds = array();
	/**
	 * @var \Intervolga\Migrato\Tool\DataTree\Tree
	 */
	protected $tree;

	/**
	 * @return \Intervolga\Migrato\Tool\XmlIdValidateError[]
	 */
	public function getLastExecuteResult()
	{
		return $this->lastExecuteResult;
	}

	protected function configure()
	{
		$this->setHidden(true);
		$this->setName('validatexmlid');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.VALIDATE_DESCRIPTION'));
	}

	public function executeInner()
	{
		$result = array();
		$this->tree = Builder::build();
		foreach ($this->tree->getDataClasses() as $data)
		{
			$result = array_merge($result, $this->checkDataClass($data));
		}
		$this->lastExecuteResult = $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 *
	 * @return array|\Intervolga\Migrato\Tool\XmlIdValidateError[]
	 * @throws \Bitrix\Main\LoaderException
	 */
	protected function checkDataClass(BaseData $data)
	{
		$result = array();
		if (Loader::includeModule($data->getModule()))
		{
			$filter = Config::getInstance()->getDataClassFilter($data);
			if (!$data->isXmlIdFieldExists())
			{
				$data->createXmlIdField();
			}
			$result = $this->validateData($data, $filter);
		}
		else
		{
			$this->dataModuleError($data);
		}

		return $result;
	}

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
		try
		{
			if ($errorType = $this->getErrorType($record))
			{
				$errors[] = new XmlIdValidateError($record->getData(), $errorType, $record->getId(), $record->getXmlId());
				$this->logger->addDb(
					array(
						'RECORD' => $record,
						'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_VALIDATE'),
						'COMMENT' => XmlIdValidateError::typeToString($errorType),
					),
					Logger::TYPE_FAIL
				);
			}
			else
			{
				$this->logger->addDb(
					array(
						'RECORD' => $record,
						'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_VALIDATE'),
					),
					Logger::TYPE_OK
				);
			}
		}
		catch (\Exception $exception)
		{
			$errors[] = new XmlIdValidateError(
				$record->getData(),
				XmlIdValidateError::TYPE_INVALID_EXT,
				$record->getId(),
				$record->getXmlId(),
				$exception->getMessage()
			);
			$this->logger->addDb(
				array(
					'RECORD' => $record,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_VALIDATE'),
					'COMMENT' => $exception->getMessage(),
				),
				Logger::TYPE_FAIL
			);
		}

		return $errors;
	}

	protected function getErrorType(Record $record)
	{
		$errorType = 0;
		if ($record->getXmlId())
		{
			if ($this->isValidXmlId($record->getXmlId()))
			{
				$record->getData()->validateXmlIdCustom($record->getXmlId());
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

		return $errorType;
	}

	/**
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	protected function isValidXmlId($xmlId)
	{
		$matches = array();
		return !!preg_match_all('/^[a-z0-9\-_#.]+$/i', $xmlId, $matches);
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
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 *
	 * @throws \Bitrix\Main\LoaderException
	 */
	protected function dataModuleError(BaseData $data)
	{
		if ($this->tree->findNode($data)->isStrongNeed())
		{
			if ($this->tree->findNode($data)->isRoot())
			{
				$code = 'INTERVOLGA_MIGRTO.CONFIG_MODULE_NOT_INSTALLED';
			}
			else
			{
				$code = 'INTERVOLGA_MIGRTO.DEPENDANT_MODULE_NOT_INSTALLED';
			}
			throw new LoaderException(Loc::getMessage(
				$code,
				array(
					'#MODULE#' => $this->logger->getModuleMessage($data->getModule()),
				)
			));
		}
		else
		{
			$this->logger->add(
				Loc::getMessage(
					'INTERVOLGA_MIGRTO.REFERENCED_MODULE_NOT_INSTALLED',
					array(
						'#MODULE#' => $this->logger->getModuleMessage($data->getModule()),
						'#ENTITY#' => $this->logger->getEntityMessage($data->getEntityName()),
					)
				),
				Logger::LEVEL_NORMAL,
				Logger::TYPE_INFO
			);
		}
	}
}