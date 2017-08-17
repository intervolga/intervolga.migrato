<?namespace Intervolga\Migrato\Tool;

use Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;

class PublicCache
{
	const CACHE_TIME = 86400;
	protected $cache = array();

	/**
	 * @var static
	 */
	protected static $instance = null;

	/**
	 * @return static
	 */
	public static function getInstance()
	{
		if (!static::$instance)
		{
			static::$instance = new static;
		}
		return static::$instance;
	}

	protected function __construct()
	{
	}

	public function getId(BaseData $dataClass, $xmlId = '', $code = '')
	{
		$module = $dataClass->getModule();
		$entity = $dataClass->getEntityName();
		if (!$this->cache[$module])
		{
			$this->cache[$module] = array();
		}
		if (!array_key_exists($entity, $this->cache[$module]))
		{
			$this->cacheData($dataClass);
		}
		if ($xmlId)
		{
			return $this->cache[$module][$entity]['XML_ID'][$xmlId];
		}
		if ($code)
		{
			return $this->cache[$module][$entity]['CODE'][$code];
		}
		return '';
	}

	public function cacheData(BaseData $dataClass)
	{
		$module = $dataClass->getModule();
		$entity = $dataClass->getEntityName();
		$cacheObject = Cache::createInstance();
		$cacheDir = '/intervolga.migrato/' . $module . '/' . $entity . '/';
		if ($cacheObject->initCache(static::CACHE_TIME, '', $cacheDir))
		{
			$this->cache[$module][$entity] = $cacheObject->getVars();
		}
		elseif ($cacheObject->startDataCache())
		{
			global $CACHE_MANAGER;
			$CACHE_MANAGER->startTagCache($cacheDir);
			$this->getAll($dataClass);
			$CACHE_MANAGER->registerTag('intervolga_migrato');
			$CACHE_MANAGER->endTagCache();
			$cacheObject->endDataCache($this->cache[$module][$entity]);
		}
	}

	protected function getAll(BaseData $dataClass)
	{
		$module = $dataClass->getModule();
		$entity = $dataClass->getEntityName();
		if (Loader::includeModule($module))
		{
			$allRecords = $dataClass->getList();
			foreach ($allRecords as $dbRecord)
			{
				if ($xmlId = $dbRecord->getXmlId())
				{
					$this->cache[$module][$entity]['XML_ID'][$xmlId] = $dbRecord->getId()->getValue();
				}
				if ($code = $dbRecord->getFieldRaw('CODE'))
				{
					$this->cache[$module][$entity]['CODE'][$code] = $dbRecord->getId()->getValue();
				}
			}
		}
	}

	public function clearTagCache()
	{
		global $CACHE_MANAGER;
		$CACHE_MANAGER->clearByTag('intervolga_migrato');
	}
}