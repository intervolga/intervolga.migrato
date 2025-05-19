<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
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
		$this->runSubcommand('autofix');
		$this->runSubcommand('exportdata');
		$this->runSubcommand('exportoptions');
		$this->copyData();
		$this->runSubcommand('importdata');
		$this->runSubcommand('importoptions');
		$this->runSubcommand('exportdata');
		$this->runSubcommand('exportoptions');
		$this->compareDirectories();
	}

	protected function copyData()
	{
		$copyDir = preg_replace('/\/$/', '_old/', INTERVOLGA_MIGRATO_DIRECTORY);
		Directory::deleteDirectory($copyDir);

		CopyDirFiles(INTERVOLGA_MIGRATO_DIRECTORY, $copyDir, false, true);
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
			File::putFileContents($reportFileName, implode("\n", $output));
			$this->logger->registerFinal(
				Loc::getMessage('INTERVOLGA_MIGRATO.REPORT_FILE', array('#FILE#' => $reportFileName)),
				Logger::TYPE_FAIL
			);
		}
		else
		{
			$this->logger->registerFinal(
				Loc::getMessage('INTERVOLGA_MIGRATO.TEST_OK'),
				Logger::TYPE_OK
			);
		}
	}
}