<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Logger;

Loc::loadMessages(__FILE__);

class DiagnosticInformationCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('diagnostic');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.DIAGNOSTIC_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->logger->registerFinal(Loc::getMessage(
			'INTERVOLGA_MIGRATO.DIAGNOSTIC_INFORMATION',
			array(
				'#VERSION_MIGRATION_MODULE#' => '1',
				'#VERSION_MAIN_MODULE#' => '2',
				'#EDITORIAL_BITRIX#' => '3',
				'#VERSION_PHP#' => '4',
			)
		),
			Logger::TYPE_OK
		);
	}
}