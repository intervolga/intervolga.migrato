<? namespace Intervolga\Migrato\Data;

use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataLink;
use Intervolga\Migrato\Tool\XmlIdProviders\BaseXmlIdProvider;

abstract class BaseData
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
	 * @param array|string[] $filter
	 *
	 * @return array|\Intervolga\Migrato\Tool\DataRecord[]
	 */
	abstract public function getList(array $filter = array());

	/**
	 * @param DataRecord $record
	 */
	abstract public function update(DataRecord $record);

	/**
	 * @param DataRecord $record
	 */
	abstract public function create(DataRecord $record);

	/**
	 * @param $xmlId
	 */
	abstract public function delete($xmlId);

	/**
	 * @param string $xmlId
	 *
	 * @return \Intervolga\Migrato\Tool\DataRecordId|null
	 */
	public function findRecord($xmlId)
	{
		foreach (static::getList() as $dbRecord)
		{
			if ($dbRecord->getXmlId() == $xmlId)
			{
				return $dbRecord->getId();
			}
		}
		return null;
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
	 * @return string
	 */
	public function getFilesSubdir()
	{
		return "/";
	}

	/**
	 * @return array|DataLink[]
	 */
	public function getDependencies()
	{
		return array();
	}

	/**
	 * @param string $name
	 * @return DataLink
	 */
	public function getDependency($name)
	{
		$dependencies = $this->getDependencies();
		return $dependencies[$name];
	}

	/**
	 * @param array|DataLink[] $dependencies
	 * @return array|DataLink[]
	 */
	public function restoreDependenciesFromFile(array $dependencies)
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