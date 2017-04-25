<? namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Tool\Orm\LogTable;

class Reindex extends BaseProcess
{
	public static function run()
	{
		parent::run();

		static::startStep("reindex");

		static::reindex();

		static::reportStepLogs();

		static::finalReport();
	}

	/**
	 * Полная переиндексация
	 */
	public static function reindex()
	{
		if(\CModule::IncludeModule("search"))
		{
			try
			{
				$count = \CSearch::ReIndexAll(true);

				static::report("reindex urlrewrite: " . $count . " шт.");

				LogTable::add(array(
					"OPERATION" => "success reindex",
					"STEP" => static::$step,
					"COMMENT" => "Переиндексировано документов: " . $count,
				));
			}
			catch (\Exception $exception)
			{
				LogTable::add(array(
					"EXCEPTION" => $exception,
					"OPERATION" => "reindex",
					"STEP" => static::$step,
					"RESULT" => false,
				));
			}
		}
	}
}