<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Logger;

Loc::loadMessages(__FILE__);

class UnitTestCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('unittest');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.UNIT_TEST_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->beforeImport();
		$this->copyData();
		$this->importExportData();
		$this->compareDirectories();
	}

	protected function beforeImport()
	{
		$this->logger->add(Loc::getMessage('INTERVOLGA_MIGRATO.BEFORE_IMPORT'), Logger::TYPE_INFO);
		$this->runSubcommand('autofix');
		$this->runSubcommand('exportdata');
	}

	protected function copyData()
	{
		$this->logger->add(Loc::getMessage('INTERVOLGA_MIGRATO.COPY_MIGRATION_DATA'), Logger::TYPE_INFO);
		$copyDir = preg_replace('/\/$/', '_old/', INTERVOLGA_MIGRATO_DIRECTORY);
		Directory::deleteDirectory($copyDir);

		CopyDirFiles(INTERVOLGA_MIGRATO_DIRECTORY, $copyDir, false, true);
	}

	protected function importExportData()
	{
		$this->logger->add(
			Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_EXPORT'),
			Logger::TYPE_INFO
		);
		$this->runSubcommand('importdata');
		$this->runSubcommand('exportdata');
	}

	protected function compareDirectories()
	{
		$this->logger->startStep(Loc::getMessage('INTERVOLGA_MIGRATO.COMPARE_DIRECTORIES'));
		$copyDir = preg_replace('/\/$/', '_old/', INTERVOLGA_MIGRATO_DIRECTORY);
		$query = 'diff --suppress-common-lines -cr ' . INTERVOLGA_MIGRATO_DIRECTORY . ' ' . $copyDir;
		$output = array();
		exec($query, $output);
		if(count($output) > 0)
		{
			$reportFileName = $copyDir. 'report_' . time() . '.txt';
			file_put_contents($reportFileName, implode('\n', $output));
			$this->logger->add(
				Loc::getMessage('INTERVOLGA_MIGRATO.REPORT_FILE', array('#FILE#' => $reportFileName)),
				Logger::TYPE_OK
			);
		}
		else
		{
			$this->logger->add(
				Loc::getMessage('INTERVOLGA_MIGRATO.ERROR_WRITE_FILE'),
				Logger::TYPE_OK
			);
		}
	}
}