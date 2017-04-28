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
		static::report(Loc::getMessage("INTERVOLGA_MIGRATO.BEFORE_IMPORT"), "info");
		AutoFix::autofix();
		ExportData::export();
	}

	protected static function copyData()
	{
		static::report(Loc::getMessage("INTERVOLGA_MIGRATO.COPY_MIGRATION_DATA"), "info");
		$copyDir = preg_replace("/\/$/", "_old/", INTERVOLGA_MIGRATO_DIRECTORY);
		Directory::deleteDirectory($copyDir);

		CopyDirFiles(INTERVOLGA_MIGRATO_DIRECTORY, $copyDir, false, true);
	}

	protected static function importExportData()
	{
		static::report(Loc::getMessage("INTERVOLGA_MIGRATO.IMPORT_EXPORT"), "info");
		ImportData::import();
		ExportData::export();
	}

	protected static function compareDirectories()
	{
		static::startStep(Loc::getMessage("INTERVOLGA_MIGRATO.COMPARE_DIRECTORIES"));
		$copyDir = preg_replace("/\/$/", "_old/", INTERVOLGA_MIGRATO_DIRECTORY);
		$query = "diff --suppress-common-lines -cr " . INTERVOLGA_MIGRATO_DIRECTORY . " " . $copyDir;
		$output = array();
		exec($query, $output);
		if(count($output) > 0)
		{
			$reportFileName = $copyDir. "report_" . time() . ".txt";
			file_put_contents($reportFileName, implode("\n", $output));
			static::report(Loc::getMessage("INTERVOLGA_MIGRATO.REPORT_FILE", array("#FILE#" => $reportFileName)), "ok");
		}
		else
		{
			static::report(Loc::getMessage("INTERVOLGA_MIGRATO.ERROR_WRITE_FILE"), "ok");
		}
	}
}