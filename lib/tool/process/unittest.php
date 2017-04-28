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
		static::beforeImport();
		static::copyData();
		static::importExportData();
		static::compareDirectories();

		static::finalReport();
	}

	protected static function beforeImport()
	{
		static::startStep(Loc::getMessage("INTERVOLGA_MIGRATO.BEFORE_IMPORT"));
		AutoFix::run();
		ExportData::run();
	}

	protected static function copyData()
	{
		static::startStep(Loc::getMessage("INTERVOLGA_MIGRATO.COPY_MIGRATION_DATA"));
		$copyDir = preg_replace("/\/$/", "_old", INTERVOLGA_MIGRATO_DIRECTORY);
		DeleteDirFilesEx($copyDir);

		CopyDirFiles(INTERVOLGA_MIGRATO_DIRECTORY, $copyDir, false, true);
	}

	protected static function importExportData()
	{
		static::startStep(Loc::getMessage("INTERVOLGA_MIGRATO.IMPORT_EXPORT"));
		ImportData::run();
		ExportData::run();
	}

	protected static function compareDirectories()
	{
		static::startStep(Loc::getMessage("INTERVOLGA_MIGRATO.COMPARE_DIRECTORIES"));
		$copyDir = preg_replace("/\/$/", "_old", INTERVOLGA_MIGRATO_DIRECTORY);
		$query = "diff --suppress-common-lines -cr " . INTERVOLGA_MIGRATO_DIRECTORY . " " . $copyDir;
		$output = array();
		$returnVar = null;
		exec($query, $output, $returnVar);
		if($returnVar)
		{
			$reportFileName = $copyDir. "/report_" . time() . ".txt";
			file_put_contents($reportFileName, implode("\n", $output));
			static::report(Loc::getMessage("INTERVOLGA_MIGRATO.REPORT_FILE"), "ok");
		}
		else
		{
			static::report(Loc::getMessage("INTERVOLGA_MIGRATO.ERROR_WRITE_FILE"), "fail");
		}
	}
}