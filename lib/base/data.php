<? namespace Intervolga\Migrato\Base;

use Bitrix\Main\IO\Directory;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\Dependency;
use Intervolga\Migrato\Tool\XmlIdValidateError;

abstract class Data
{
	protected static $instances = array();

	/**
	 * @return static
	 */
	public static function getInstance()
	{
		if (!static::$instances[get_called_class()])
		{
			static::$instances[get_called_class()] = new static();
		}

		return static::$instances[get_called_class()];
	}
	/**
	 * @return array|DataRecord[]
	 */
	abstract public function getFromDatabase();
	/**
	 * @param int|string $id
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	abstract public function setXmlId($id, $xmlId);

	/**
	 * @param int|string $id
	 *
	 * @return string
	 */
	abstract public function getXmlId($id);

	/**
	 * @param DataRecord $record
	 */
	abstract protected function update(DataRecord $record);

	/**
	 * @param DataRecord $record
	 */
	abstract protected function create(DataRecord $record);

	/**
	 * @param $xmlId
	 */
	abstract protected function delete($xmlId);
	/**
	 * @return string
	 */
	public function getModule()
	{
		$class = get_called_class();
		$tmp = str_replace("Intervolga\\Migrato\\Module\\", "", $class);
		$tmp = substr($tmp, 0, strpos($tmp, "\\"));
		$tmp = strtolower($tmp);

		return $tmp;
	}

	/**
	 * @return string
	 */
	public function getEntityName()
	{
		$class = get_called_class();
		$tmp = substr($class, strrpos($class, "\\") + 1);
		$tmp = strtolower($tmp);

		return $tmp;
	}

	/**
	 * @return bool
	 */
	public function isXmlIdFieldExists()
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function createXmlIdField()
	{
		return true;
	}

	/**
	 * @return array|string[]
	 */
	public function getXmlIdsFromDatabase()
	{
		$result = array();
		$records = $this->getFromDatabase();
		foreach ($records as $record)
		{
			if ($record->getXmlId())
			{
				$result[$record->getXmlId()] = $record->getXmlId();
			}
		}

		return array_values($result);
	}

	/**
	 * @return array|XmlIdValidateError[]
	 */
	public function validateXmlIds()
	{
		$errors = array();
		$records = $this->getFromDatabase();
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
				$errors[] = new XmlIdValidateError($errorType, $record->getLocalDbId(), $record->getXmlId());
			}
		}
		return $errors;
	}

	/**
	 * @param array|XmlIdValidateError[] $errors
	 */
	public function fixErrors(array $errors)
	{
		foreach ($errors as $error)
		{
			if ($error->getType() == XmlIdValidateError::TYPE_EMPTY)
			{
				$this->setXmlId($error->getId(), $this->makeXmlId());
			}
			elseif ($error->getType() == XmlIdValidateError::TYPE_INVALID)
			{
				$xmlId = $this->getXmlId($error->getId());
				$xmlId = preg_replace("/[^a-z0-9\-_]/", "-", $xmlId);
				$this->setXmlId($error->getId(), $xmlId);
			}
			elseif ($error->getType() == XmlIdValidateError::TYPE_REPEAT)
			{
				$this->setXmlId($error->getId(), $this->makeXmlId());
			}
		}
	}

	/**
	 * @return string
	 */
	protected function makeXmlId()
	{
		$xmlid = uniqid("", true);
		$xmlid = str_replace(".", "", $xmlid);
		$xmlid = str_split($xmlid, 6);
		$xmlid = implode("-", $xmlid);
		return $xmlid;
	}

	public function exportToFile()
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $this->getModule() . "/" . $this->getEntityName() . "/";
		Directory::deleteDirectory($path);
		checkDirPath($path);

		$records = $this->getFromDatabase();
		foreach ($records as $record)
		{
			DataFileViewXml::writeToFileSystem($record, $path);
		}
	}

	/**
	 * @return array|\Intervolga\Migrato\Tool\DataRecord[]
	 */
	public function readFromFile()
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $this->getModule() . "/" . $this->getEntityName() . "/";

		$data = DataFileViewXml::readFromFileSystem($path);
		foreach ($data as $i => $dataItem)
		{
			$data[$i]->setData($this);
			if ($dependencies = $data[$i]->getDependencies())
			{
				$dependencies = $this->restoreDependenciesFromFile($dependencies);
				$data[$i]->setDependencies($dependencies);
			}
		}

		return $data;
	}

	/**
	 * @param array|Dependency[] $dependencies
	 * @return array|Dependency[]
	 */
	protected function restoreDependenciesFromFile(array $dependencies)
	{
		return array();
	}
}