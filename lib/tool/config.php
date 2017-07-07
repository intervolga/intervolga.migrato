<? namespace Intervolga\Migrato\Tool;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;

class Config
{
	protected $configArray = array();
	protected $dataClassesFilter = array();
	protected static $instance = null;

	/**
	 * @return bool
	 */
	public static function isExists()
	{
		return File::isFileExists(INTERVOLGA_MIGRATO_CONFIG_PATH);
	}

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

	protected function readFile()
	{
		$xmlParser = new \CDataXML();
		$xmlParser->load(INTERVOLGA_MIGRATO_CONFIG_PATH);
		$this->configArray = $xmlParser->getArray();
	}

	/**
	 * @return string[]
	 */
	public function getModules()
	{
		$result = array();
		foreach ($this->configArray["config"]["#"]["module"] as $moduleArray)
		{
			$moduleName = $moduleArray["#"]["name"][0]["#"];
			$result[] = $moduleName;
		}

		return $result;
	}

	/**
	 * @return string[]
	 */
	public function getOptionsRules()
	{
		$options = array();
		if ($optionsArray = $this->configArray["config"]["#"]["options"])
		{
			foreach ($optionsArray[0]["#"]["exclude"] as $excludeItem)
			{
				$options[] = $excludeItem["#"];
			}
		}

		return $options;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public static function isOptionIncluded($name)
	{
		$isIncluded = true;
		$rules = static::getInstance()->getOptionsRules();
		foreach ($rules as $rule)
		{
			$pattern = static::ruleToPattern($rule);
			$matches = array();
			if (preg_match_all($pattern, $name, $matches))
			{
				$isIncluded = false;
			}
		}
		return $isIncluded;
	}

	/**
	 * @param string $module
	 * @param string $name
	 * @param string[] $rule
	 *
	 * @return bool
	 */
	protected function isOptionMatchesRule($module, $name, $rule)
	{
		$result = false;
		$ruleModule = $rule['module'] ? $rule['module'] : '.*';
		$ruleModulePattern = static::ruleToPattern($ruleModule);

		$matches = array();
		if (preg_match_all($ruleModulePattern, $module, $matches))
		{
			$ruleName = $rule['name'];
			$ruleNamePattern = static::ruleToPattern($ruleName);
			if (preg_match_all($ruleNamePattern, $name, $matches))
			{
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * @param string $rule
	 *
	 * @return string
	 */
	protected static function ruleToPattern($rule)
	{
		$pattern = $rule;
		if ($pattern[0] != '/')
		{
			$pattern = '/' . $rule . '/';
		}

		return $pattern;
	}

	/**
	 * @return BaseData[]
	 */
	public function getAllDataClasses()
	{
		$entities = array();
		$dir = new Directory(\Bitrix\Main\Application::getDocumentRoot() . "/local/modules/intervolga.migrato/lib/data/module/");
		foreach ($dir->getChildren() as $module)
		{
			/**
			 * @var Directory $module директория с сущностями
			 */
			foreach ($module->getChildren() as $entityArray)
			{
				$entityName = str_replace(".php", "", $entityArray->getName());
				$name = "\\Intervolga\\Migrato\\Data\\Module\\" . $module->getName() . "\\" . $entityName;
				if (class_exists($name))
				{
					/**
					 * @var BaseData $name
					 */
					$dataObject = $name::getInstance();
					if (Loader::includeModule($dataObject->getModule()))
					{
						$entities[] = $dataObject;
					}
				}
			}
		}

		return $entities;
	}

	/**
	 * @return BaseData[]
	 */
	public function getDataClasses()
	{
		static $entities = array();
		if (!$entities)
		{
			foreach ($this->configArray["config"]["#"]["module"] as $moduleArray)
			{
				$moduleName = $moduleArray["#"]["name"][0]["#"];
				foreach ($moduleArray["#"]["entity"] as $entityArray)
				{
					$className = $entityArray["#"]["name"][0]["#"];
					$name = "\\Intervolga\\Migrato\\Data\\Module\\" . $moduleName . "\\" . $className;
					if (class_exists($name))
					{
						/**
						 * @var BaseData $name
						 */
						$dataObject = $name::getInstance();
						if (Loader::includeModule($dataObject->getModule()))
						{
							$entities[] = $dataObject;
							if ($entityArray["#"]["filter"])
							{
								$this->registerDataFilter($dataObject, $entityArray["#"]["filter"]);
							}
						}
					}
				}
			}
		}

		return $entities;
	}

	/**
	 * @param BaseData $dataObject
	 * @param array $filterNodes
	 */
	protected function registerDataFilter(BaseData $dataObject, array $filterNodes)
	{
		foreach ($filterNodes as $filterArray)
		{
			$this->dataClassesFilter[$dataObject->getModule()][$dataObject->getEntityName()][] = $filterArray["#"];
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return array|string[]
	 */
	public function getDataClassFilter(BaseData $dataClass)
	{
		if ($this->dataClassesFilter[$dataClass->getModule()][$dataClass->getEntityName()])
		{
			return array_unique($this->dataClassesFilter[$dataClass->getModule()][$dataClass->getEntityName()]);
		}
		else
		{
			return array();
		}
	}
}