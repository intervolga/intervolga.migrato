<?namespace Intervolga\Migrato;

use Bitrix\Main\IO\Directory;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataRecordsResolveList;
use Intervolga\Migrato\Tool\OptionFileViewXml;
use Intervolga\Migrato\Tool\XmlIdValidateError;

class Migrato
{
	protected static $baseBeforeImport = array();
	protected static $imported = array();
	/**
	 * @return array|string[]
	 */
	public static function exportData()
	{
		$result = array();
		foreach (Config::getInstance()->getDataClasses() as $data)
		{
			$filter = Config::getInstance()->getDataClassFilter($data);
			try
			{
				if (!$data->getXmlIdProvider()->isXmlIdFieldExists())
				{
					$data->getXmlIdProvider()->createXmlIdField();
				}
				$errors = static::validateXmlIds($data, $filter);
				if ($errors)
				{
					static::fixErrors($data, $errors);
				}
				$errors = static::validateXmlIds($data, $filter);
				if (!$errors)
				{
					static::exportToFile($data, $filter);
					$result[] = "Data " . $data->getModule() . "/" . $data->getEntityName() . " exported to files";
				}
				else
				{
					$result[] = "Data " . $data->getModule() . "/" . $data->getEntityName() . " exported with errors (" . count($errors) . ")";
				}
			}
			catch (\Exception $exception)
			{
				$result[] = "Data " . $data->getModule() . "/" . $data->getEntityName() . " exported with exception: " . $exception->getMessage();
			}
		}
		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param array|string[] $filter
	 *
	 * @return array|\Intervolga\Migrato\Tool\XmlIdValidateError[]
	 */
	protected static function validateXmlIds(BaseData $dataClass, array $filter = array())
	{
		$errors = array();
		$records = $dataClass->getList($filter);
		$xmlIds[] = array();
		foreach ($records as $record)
		{
			$errorType = 0;
			if ($record->getXmlId())
			{
				$matches = array();
				if (preg_match_all("/^[a-z0-9\-_]*$/i", $record->getXmlId(), $matches))
				{
					if (!in_array($record->getXmlId(), $xmlIds))
					{
						$xmlIds[] = $record->getXmlId();
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
				$errors[] = new XmlIdValidateError($errorType, $record->getId(), $record->getXmlId());
			}
		}
		return $errors;
	}

	/**
	 * @param array|XmlIdValidateError[] $errors
	 */
	protected static function fixErrors(BaseData $dataClass, array $errors)
	{
		foreach ($errors as $error)
		{
			if ($error->getType() == XmlIdValidateError::TYPE_EMPTY)
			{
				$dataClass->getXmlIdProvider()->generateXmlId($error->getId());
			}
			elseif ($error->getType() == XmlIdValidateError::TYPE_INVALID)
			{
				$xmlId = $dataClass->getXmlIdProvider()->getXmlId($error->getId());
				$xmlId = preg_replace("/[^a-z0-9\-_]/", "-", $xmlId);
				$dataClass->getXmlIdProvider()->setXmlId($error->getId(), $xmlId);
			}
			elseif ($error->getType() == XmlIdValidateError::TYPE_REPEAT)
			{
				$dataClass->getXmlIdProvider()->generateXmlId($error->getId());
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param array|string[] $filter
	 */
	protected static function exportToFile(BaseData $dataClass, array $filter = array())
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . "/";
		Directory::deleteDirectory($path);
		checkDirPath($path);

		$records = $dataClass->getList($filter);
		foreach ($records as $record)
		{
			DataFileViewXml::writeToFileSystem($record, $path);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return array|\Intervolga\Migrato\Tool\DataRecord[]
	 */
	protected static function readFromFile(BaseData $dataClass)
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . "/";

		$data = DataFileViewXml::readFromFileSystem($path);
		foreach ($data as $i => $dataItem)
		{
			$data[$i]->setData($dataClass);
			if ($dependencies = $data[$i]->getDependencies())
			{
				$dependencies = $dataClass->restoreDependenciesFromFile($dependencies);
				$data[$i]->setDependencies($dependencies);
			}
		}

		return $data;
	}

	/**
	 * @return array|string[]
	 */
	public static function importData()
	{
		$result = array();
		$list = new DataRecordsResolveList();
		foreach (Config::getInstance()->getDataClasses() as $data)
		{
			foreach ($data->getList() as $dataRecord)
			{
				if ($dataRecord->getXmlId())
				{
					static::$baseBeforeImport[$data->getModule()][$data->getEntityName()][] = $dataRecord->getXmlId();
				}
			}

			$list->addDataRecords(static::readFromFile($data));
		}

		for ($i = 0; $i < count(Config::getInstance()->getDataClasses()) * 2; $i++)
		{
			$creatableDataRecords = $list->getCreatableDataRecords();
			if ($creatableDataRecords)
			{
				foreach ($creatableDataRecords as $dataRecord)
				{
					$result[] = static::saveDataRecord($dataRecord);
					$list->setCreated($dataRecord);
				}
			}
			else
			{
				break;
			}
		}
		$result = array_merge($result, static::deleteNotImported());

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Tool\DataRecord $dataRecord
	 *
	 * @return string
	 */
	protected static function saveDataRecord(DataRecord $dataRecord)
	{
		$nameForReport = "Data " . $dataRecord->getData()->getModule() . "/" . $dataRecord->getData()->getEntityName() . " (" . $dataRecord->getXmlId() . ")";
		if ($dataRecordId = $dataRecord->getData()->findRecord($dataRecord->getXmlId()))
		{
			try
			{
				$dataRecord->setId($dataRecordId);
				$dataRecord->getData()->update($dataRecord);
				static::$imported[$dataRecord->getData()->getModule()][$dataRecord->getData()->getEntityName()][] = $dataRecord->getXmlId();
				return $nameForReport . " updated";
			}
			catch (\Exception $exception)
			{
				return $nameForReport . " update exception: " . $exception->getMessage();
			}
		}
		else
		{
			try
			{
				$dataRecord->getData()->create($dataRecord);
				static::$imported[$dataRecord->getData()->getModule()][$dataRecord->getData()->getEntityName()][] = $dataRecord->getXmlId();
				return $nameForReport . " created";
			}
			catch (\Exception $exception)
			{
				return $nameForReport . " create exception: " . $exception->getMessage();
			}
		}
	}

	/**
	 * @return array|string[]
	 */
	protected static function deleteNotImported()
	{
		$result = array();
		foreach (Config::getInstance()->getDataClasses() as $data)
		{
			foreach (static::$baseBeforeImport[$data->getModule()][$data->getEntityName()] as $xmlId)
			{
				$nameForReport = "Data " . $data->getModule() . "/" . $data->getEntityName() . " (" . $xmlId . ")";
				if (!in_array($xmlId, static::$imported[$data->getModule()][$data->getEntityName()]))
				{
					try
					{
						$data->delete($xmlId);
						$result[] = $nameForReport . " deleted";
					}
					catch (\Exception $exception)
					{
						$result[] = $nameForReport . " delete error: " . $exception->getMessage();
					}
				}
			}
		}

		return $result;
	}

	public static function exportOptions()
	{
		$options = Config::getInstance()->getModulesOptions();
		foreach ($options as $module => $moduleOptions)
		{
			$export = array();
			foreach ($moduleOptions as $option)
			{
				$optionValue = \Bitrix\Main\Config\Option::get($module, $option);
				$export[$option] = $optionValue;
			}
			ksort($export);

			$path = static::getModuleOptionsDirectory($module);
			OptionFileViewXml::writeToFileSystem($export, $path);
		}
	}

	/**
	 * @param string $module
	 * @return string
	 */
	protected static function getModuleOptionsDirectory($module)
	{
		return INTERVOLGA_MIGRATO_DIRECTORY . $module . "/";
	}

	public static function importOptions()
	{
		$modules = Config::getInstance()->getModules();
		foreach ($modules as $module)
		{
			$path = static::getModuleOptionsDirectory($module);
			$options = OptionFileViewXml::readFromFileSystem($path);
			foreach ($options as $name => $value)
			{
				\Bitrix\Main\Config\Option::set($module, $name, $value);
			}
		}
	}
}