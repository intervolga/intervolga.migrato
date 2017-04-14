<? namespace Intervolga\Migrato\Tool\Process;

class ImportDataHard extends ImportData
{
	public static function run()
	{
		BaseProcess::run();

		$errors = Validate::validate();
		if (!$errors)
		{
			static::init();
			static::importWithDependencies();
			static::logNotResolved();
			static::deleteNotImported();
			static::deleteMarked();
			static::resolveReferences();
		}

		parent::finalReport();
	}

	protected static function deleteNotImported()
	{
		static::startStep(__FUNCTION__);
		foreach (static::$list->getRecordsToDelete() as $dataRecord)
		{
			static::deleteRecordWithLog($dataRecord);
		}
		static::reportStepLogs();
	}
}