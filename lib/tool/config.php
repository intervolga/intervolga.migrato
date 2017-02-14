<? namespace Intervolga\Migrato\Tool;

use Intervolga\Migrato\Base\Data;

class Config
{
	protected static $configArray = array();
	protected static $instance = null;

	/**
	 * @return Config
	 */
	public static function getInstance()
	{
		if (!static::$instance)
		{
			static::$instance = new static();
		}

		return static::$instance;
	}

	protected function __construct()
	{
		$this->readFile();
	}

	public function readFile()
	{
		$xmlParser = new \CDataXML();
		$xmlParser->load(INTERVOLGA_MIGRATO_CONFIG_PATH);
		static::$configArray = $xmlParser->getArray();
	}

	/**
	 * @return array|string[]
	 */
	public function getModules()
	{
		$result = array();
		foreach (static::$configArray["config"]["#"]["module"] as $moduleArray)
		{
			$moduleName = $moduleArray["#"]["name"][0]["#"];
			$result[] = $moduleName;
		}

		return $result;
	}

	/**
	 * @return array
	 */
	public function getModulesOptions()
	{
		$options = array();
		foreach (static::$configArray["config"]["#"]["module"] as $moduleArray)
		{
			$moduleName = $moduleArray["#"]["name"][0]["#"];
			foreach ($moduleArray["#"]["options"][0]["#"]["name"] as $optionArray)
			{
				$options[$moduleName][] = $optionArray["#"];
			}
		}

		return $options;
	}

	/**
	 * @return array|Data[]
	 */
	public function getDataClasses()
	{
		$entities = array();
		foreach (static::$configArray["config"]["#"]["module"] as $moduleArray)
		{
			$moduleName = $moduleArray["#"]["name"][0]["#"];
			foreach ($moduleArray["#"]["entities"][0]["#"]["name"] as $entityArray)
			{
				$className = $entityArray["#"];
				$name = "\\Intervolga\\Migrato\\Data\\Module\\" . $moduleName . "\\" . $className;
				if (class_exists($name))
				{
					/**
					 * @var Data $name
					 */
					$entities[] = $name::getInstance();
				}
			}
		}

		return $entities;
	}
}