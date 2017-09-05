<? namespace Intervolga\Migrato\Data;

use Bitrix\Main\NotImplementedException;
use Intervolga\Migrato\Tool\PublicCache;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

abstract class BaseData
{
	protected static $instances = array();
	/**
	 * @var \Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider
	 */
	protected $xmlIdProvider = null;

	protected $cache = array();
	protected $isAllCached = false;

	protected $virtualXmlId = false;
	protected $entityNameLoc;
	protected $filesSubdir;
	protected $dependencies = array();
	protected $references = array();

	/**
	 * @return static
	 */
	public static function getInstance()
	{
		if (!static::$instances[get_called_class()])
		{
			$dataClass = new static();
			static::$instances[get_called_class()] = $dataClass;
			$dataClass->configure();
		}

		return static::$instances[get_called_class()];
	}

	protected function configure()
	{
		$this->setEntityNameLoc($this->getEntityName());
		$this->setFilesSubdir('/');
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	abstract public function getList(array $filter = array());

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	public function update(Record $record)
	{
		throw new NotImplementedException("Update for " . $record->getData()->getModule() . "/" . $record->getData()->getEntityName() . " is not yet implemented");
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return \Intervolga\Migrato\Data\RecordId
	 * @throws \Exception
	 */
	public function create(Record $record)
	{
		$recordId = $this->createInner($record);
		if ($record->getXmlId())
		{
			$this->cache[$record->getXmlId()] = $recordId;
		}
		return $recordId;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @throws \Bitrix\Main\NotImplementedException
	 *
	 * @return \Intervolga\Migrato\Data\RecordId
	 */
	protected function createInner(Record $record)
	{
		throw new NotImplementedException("Create for " . $record->getData()->getModule() . "/" . $record->getData()->getEntityName() . " is not yet implemented");
	}

	/**
	 * @param string $xmlId
	 *
	 * @throws \Exception
	 */
	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if ($id)
		{
			$this->deleteInner($id);
		}
		$this->cache[$xmlId] = null;
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 *
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	protected function deleteInner(RecordId $id)
	{
		throw new NotImplementedException("Delete for " . $this->getModule() . "/" . $this->getEntityName() . " (" . $id->getValue() . ") is not yet implemented");
	}

	/**
	 * @param string $xmlId
	 *
	 * @return \Intervolga\Migrato\Data\RecordId|null
	 */
	public function findRecord($xmlId)
	{
		$id = null;
		if ($this->xmlIdProvider)
		{
			$id = $this->xmlIdProvider->findRecord($xmlId);
		}
		else
		{
			$id = $this->findRecordExhaustive($xmlId);
		}

		return $id;
	}

	protected function findRecordExhaustive($xmlId)
	{
		if (array_key_exists($xmlId, $this->cache))
		{
			return $this->cache[$xmlId];
		}
		else
		{
			if ($this->isAllCached)
			{
				$this->cache[$xmlId] = null;
			}
			else
			{
				$this->cache[$xmlId] = null;
				$this->cacheIds();
				$this->isAllCached = true;
			}

			return $this->cache[$xmlId];
		}
	}

	protected function cacheIds()
	{
		$allRecords = static::getList();
		foreach ($allRecords as $dbRecord)
		{
			if ($dbRecord->getXmlId())
			{
				$this->cache[$dbRecord->getXmlId()] = $dbRecord->getId();
			}
		}
		$this->isAllCached = true;
	}

	/**
	 * @return string
	 */
	public function getModule()
	{
		$class = get_called_class();
		$tmp = str_replace("Intervolga\\Migrato\\Data\\Module\\", "", $class);
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
	 * @param string $entityNameLoc
	 */
	protected function setEntityNameLoc($entityNameLoc)
	{
		$this->entityNameLoc = $entityNameLoc;
	}

	/**
	 * @return string
	 */
	public function getEntityNameLoc()
	{
		return $this->entityNameLoc;
	}

	/**
	 * @param string $filesSubdir
	 */
	public function setFilesSubdir($filesSubdir)
	{
		$this->filesSubdir = $filesSubdir;
	}

	/**
	 * @return string
	 */
	public function getFilesSubdir()
	{
		return $this->filesSubdir;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Runtime[]
	 */
	public function getRuntimes()
	{
		return array();
	}

	/**
	 * @param string $name
	 *
	 * @return \Intervolga\Migrato\Data\Runtime
	 */
	public function getRuntime($name)
	{
		$runtimes = $this->getRuntimes();

		return $runtimes[$name];
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $dependencies
	 */
	protected function setDependencies(array $dependencies)
	{
		$this->dependencies = $dependencies;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Link[]
	 */
	public function getDependencies()
	{
		return $this->dependencies;
	}

	/**
	 * @param string $name
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 */
	public function getDependency($name)
	{
		$dependencies = $this->getDependencies();

		return $dependencies[$name];
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $references
	 */
	protected function setReferences(array $references)
	{
		$this->references = $references;
	}

	/**
	 * @return \Intervolga\Migrato\Data\Link[]
	 */
	public function getReferences()
	{
		return $this->references;
	}

	/**
	 * @param string $name
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 */
	public function getReference($name)
	{
		$references = $this->getReferences();

		return $references[$name];
	}

	/**
	 * @return \Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider
	 */
	public function getXmlIdProvider()
	{
		return $this->xmlIdProvider;
	}

	/**
	 * @param mixed $id
	 *
	 * @return \Intervolga\Migrato\Data\RecordId
	 */
	public function createId($id)
	{
		return RecordId::createNumericId($id);
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 * @param string $xmlId
	 *
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	public function setXmlId($id, $xmlId)
	{
		if ($this->xmlIdProvider)
		{
			$this->xmlIdProvider->setXmlId($id, $xmlId);
		}
		else
		{
			if (!$this->isVirtualXmlId())
			{
				throw new NotImplementedException("setXmlId for " . $this->getModule() . "/" . $this->getEntityName() . " is not yet implemented");
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 *
	 * @return string
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	public function getXmlId($id)
	{
		if ($this->xmlIdProvider)
		{
			return $this->xmlIdProvider->getXmlId($id);
		}
		else
		{
			throw new NotImplementedException("getXmlId for " . $this->getModule() . "/" . $this->getEntityName() . " is not yet implemented");
		}
	}

	/**
	 * @return bool
	 */
	public function isXmlIdFieldExists()
	{
		if ($this->xmlIdProvider)
		{
			return $this->xmlIdProvider->isXmlIdFieldExists();
		}
		else
		{
			return true;
		}
	}

	public function createXmlIdField()
	{
		if ($this->xmlIdProvider)
		{
			$this->xmlIdProvider->createXmlIdField();
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 *
	 * @return string
	 */
	public function generateXmlId($id)
	{
		if ($this->xmlIdProvider)
		{
			return $this->xmlIdProvider->generateXmlId($id);
		}
		else
		{
			$xmlId = $this->makeXmlId();
			$this->setXmlId($id, $xmlId);
			return $xmlId;
		}
	}

	/**
	 * @return string
	 */
	protected function makeXmlId()
	{
		if ($this->xmlIdProvider)
		{
			return $this->xmlIdProvider->makeXmlId();
		}
		else
		{
			return BaseXmlIdProvider::makeDefaultXmlId($this);
		}
	}

	/**
	 * @param string $xmlId
	 * @param string $code
	 *
	 * @return string
	 */
	public static function getPublicId($xmlId = '', $code = '')
	{
		return PublicCache::getInstance()->getId(static::getInstance(), $xmlId, $code);
	}

	/**
	 * @param string $xmlId
	 */
	public function validateXmlIdCustom($xmlId)
	{
	}

	/**
	 * @param string $xmlId
	 *
	 * @return string
	 */
	public function getValidationXmlId($xmlId)
	{
		return $xmlId;
	}

	/**
	 * @param bool $virtualXmlId
	 */
	public function setVirtualXmlId($virtualXmlId)
	{
		$this->virtualXmlId = (bool) $virtualXmlId;
	}

	/**
	 * @return bool
	 */
	public function isVirtualXmlId()
	{
		return $this->virtualXmlId;
	}
}