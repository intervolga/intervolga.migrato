<? namespace Intervolga\Migrato\Base;

use Bitrix\Main\IO\Directory;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\Dependency;
use Intervolga\Migrato\Tool\XmlIdProviders\BaseXmlIdProvider;
use Intervolga\Migrato\Tool\XmlIdValidateError;

abstract class Data
{
	protected static $instances = array();
	protected $xmlIdProvider = null;

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
				$errors[] = new XmlIdValidateError($errorType, $record->getId(), $record->getXmlId());
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
				$this->getXmlIdProvider()->generateXmlId($error->getId());
			}
			elseif ($error->getType() == XmlIdValidateError::TYPE_INVALID)
			{
				$xmlId = $this->getXmlIdProvider()->getXmlId($error->getId());
				$xmlId = preg_replace("/[^a-z0-9\-_]/", "-", $xmlId);
				$this->getXmlIdProvider()->setXmlId($error->getId(), $xmlId);
			}
			elseif ($error->getType() == XmlIdValidateError::TYPE_REPEAT)
			{
				$this->getXmlIdProvider()->generateXmlId($error->getId());
			}
		}
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

	/**
	 * @return BaseXmlIdProvider
	 */
	public function getXmlIdProvider()
	{
		return $this->xmlIdProvider;
	}
}