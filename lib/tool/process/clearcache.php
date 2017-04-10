<?namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Tool\Orm\LogTable;
use Intervolga\Migrato\Tool\XmlIdValidateError;

class ClearCache extends BaseProcess
{
	public static function run()
	{
		parent::run();

		static::startStep("clearcache");

		static::clear();

		static::reportStepLogs();

		static::finalReport();
	}

	/**
	 * @param XmlIdValidateError[] $errors
	 *
	 * @return int
	 */
	public static function clear()
	{
		try
		{
			BXClearCache(true);
			$GLOBALS["CACHE_MANAGER"]->CleanAll();
			$GLOBALS["stackCacheManager"]->CleanAll();
			$staticHtmlCache = \Bitrix\Main\Data\StaticHtmlCache::getInstance();
			$staticHtmlCache->deleteAll();

			$errors = $_SESSION["CACHE_STAT"]["errors"] ? $_SESSION["CACHE_STAT"]["errors"] : 0;
			$reports = array(
				"scaned" => $_SESSION["CACHE_STAT"]["scanned"],
				"scaned size" => \CFile::FormatSize($_SESSION["CACHE_STAT"]["space_total"]),
				"deleted" => $_SESSION["CACHE_STAT"]["deleted"],
				"deleted size" => \CFile::FormatSize($_SESSION["CACHE_STAT"]["space_freed"]),
				"errors" => $errors,
			);
			foreach($reports as $key => $report)
			{
				static::report($key . ": " . $report);
			}
			LogTable::add(array(
				"OPERATION" => "success clear cache",
				"STEP" => static::$step,
				"COMMENT" => implode("; ", $reports),
			));
		}
		catch (\Exception $exception)
		{
			LogTable::add(array(
				"EXCEPTION" => $exception,
				"OPERATION" => "clear cache",
				"STEP" => static::$step,
				"RESULT" => false,
			));
		}
	}
}