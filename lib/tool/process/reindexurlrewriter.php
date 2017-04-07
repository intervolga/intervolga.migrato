<?namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Tool\Orm\LogTable;
use Intervolga\Migrato\Tool\XmlIdValidateError;

class ReindexUrlRewriter extends BaseProcess
{
	public static function run()
	{
		parent::run();

		static::startStep("reindexurlrewriter");

		static::reindex();

		static::reportStepLogs();

		static::finalReport();
	}

	/**
	 * @param XmlIdValidateError[] $errors
	 *
	 * @return int
	 */
	public static function reindex()
	{
		try
		{
			$res = \Bitrix\Main\UrlRewriter::reindexAll();

			static::report("reindex urlrewrite: " . $res);

			LogTable::add(array(
				"OPERATION" => "success clear cache",
				"STEP" => static::$step,
				"COMMENT" => "Обработано документов: " . $res,
			));
		}
		catch (\Exception $exception)
		{
			LogTable::add(array(
				"EXCEPTION" => $exception,
				"OPERATION" => "reindex url rewriter",
				"STEP" => static::$step,
				"RESULT" => false,
			));
		}
	}
}