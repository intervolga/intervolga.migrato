<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class UnitTest extends BaseProcess
{
	public static function run()
	{
		parent::run();
		static::prepareImport();
		static::copyData();
		static::importExportData();
		static::compareDirectories();

		static::finalReport();
	}

	protected static function prepareImport()
	{
		AutoFix::run();
		ExportData::run();
	}

	protected static function copyData()
	{
		static::startStep("копирование данных");
		$copyDir = preg_replace("/\/$/", "_old", INTERVOLGA_MIGRATO_DIRECTORY);
		DeleteDirFilesEx($copyDir);

		CopyDirFiles(INTERVOLGA_MIGRATO_DIRECTORY, $copyDir, false, true);
	}

	protected static function importExportData()
	{
		static::startStep("Импорт + экспорт данных");
		ImportData::run();
		ExportData::run();
	}

	protected static function compareDirectories()
	{
		static::startStep("Сравнивание директорий");
		$copyDir = preg_replace("/\/$/", "_old", INTERVOLGA_MIGRATO_DIRECTORY);
		$query = "diff --suppress-common-lines -cr " . INTERVOLGA_MIGRATO_DIRECTORY . " " . $copyDir;
		$output = array();
		$returnVar = null;
		exec($query, $output, $returnVar);
		if($returnVar)
		{
			file_put_contents($copyDir. "/report." . time(), implode("\n", $output));
		}
		static::report("Команда завершена: ", $returnVar ? "ok" : "fail");
	}
}