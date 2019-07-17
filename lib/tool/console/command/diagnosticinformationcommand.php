<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Loader;
use Intervolga\Migrato\Tool\Console\Logger;
use \Bitrix\Main\Config\Option;

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
				'#VERSION_MIGRATION_MODULE#' => self::getVersionMigrationModule(),
				'#VERSION_MAIN_MODULE#' => self::getVersionMainModule(),
				'#EDITORIAL_BITRIX#' => self::getEditorialBitrix(),
				'#VERSION_PHP#' => phpversion(),
			)
		),
			Logger::TYPE_OK
		);
	}

	protected static function getVersionMigrationModule()
	{
		$arModuleVersion = array();
		include($_SERVER["DOCUMENT_ROOT"] . "/local/modules/intervolga.migrato/install/version.php");
		return $arModuleVersion["VERSION"];
	}

	protected static function getVersionMainModule()
	{
		if (defined("SM_VERSION"))
		{
			return SM_VERSION;
		}
		else
		{
			return Loc::getMessage('INTERVOLGA_MIGRATO.VERSION_MAIN_MODULE_NOT_DEFINED');
		}
	}

	protected static function getEditorialBitrix()
	{
		include($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/classes/general/update_client.php');
		$errors = null;
		$stableVersionsOnly = Option::get('main', 'stable_versions_only', 'Y');
		$updateList = \CUpdateClient::GetUpdatesList($errors, LANG, $stableVersionsOnly);

		return $updateList['CLIENT'][0]['@']['LICENSE'];
	}
}