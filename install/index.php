<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\IO\Directory;

Loc::loadMessages(__FILE__);

class intervolga_migrato extends CModule
{
	/**
	 * @return string
	 */
	public static function getModuleId()
	{
		return basename(dirname(__DIR__));
	}

	public function __construct()
	{
		$arModuleVersion = array();
		include(dirname(__DIR__) . "/include.php");
		include(dirname(__FILE__) . "/version.php");
		$this->MODULE_ID = self::getModuleId();
		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		$this->MODULE_NAME = Loc::getMessage("INTERVOLGA_MIGRATO_MODULE_NAME");
		$this->MODULE_DESCRIPTION = Loc::getMessage("INTERVOLGA_MIGRATO_MODULE_DESC");

		$this->PARTNER_NAME = Loc::getMessage("INTERVOLGA_MIGRATO_PARTNER_NAME");
		$this->PARTNER_URI = Loc::getMessage("INTERVOLGA_MIGRATO_PARTNER_URI");
	}

	public function doInstall()
	{
		try
		{
			$this->installDb();
			$this->copyPublicFiles();
			Main\ModuleManager::registerModule($this->MODULE_ID);
			$this->installEvents();
		}
		catch (\Exception $e)
		{
			global $APPLICATION;
			$APPLICATION->ThrowException($e->getMessage());

			return false;
		}

		return true;
	}

	public function installDb()
	{
		global $DB, $DBType;
		$errors = $DB->RunSQLBatch(__DIR__. "/db/" . strtolower($DBType) . "/install.sql");
		if ($errors)
		{
			throw new \Exception(implode("<br>", $errors));
		}

		return true;
	}

	public function copyPublicFiles()
	{
		if(!Directory::isDirectoryExists(INTERVOLGA_MIGRATO_DIRECTORY))
		{
			Directory::createDirectory(INTERVOLGA_MIGRATO_DIRECTORY);

			CopyDirFiles(__DIR__ . "/public", INTERVOLGA_MIGRATO_DIRECTORY);
		}
	}

	function installEvents()
	{
		/**
		 * @see \Intervolga\Migrato\Tool\EventHandlers\Sale::onBeforePersonTypeUpdate
		 */
		EventManager::getInstance()->registerEventHandler(
			'sale',
			'OnBeforePersonTypeUpdate',
			$this->MODULE_ID,
			'\Intervolga\Migrato\Tool\EventHandlers\Sale',
			'onBeforePersonTypeUpdate'
		);

		/**
		 * @see \Intervolga\Migrato\Tool\EventHandlers\Sale::onBeforeUpdateOrderPropsTable
		 */
		EventManager::getInstance()->registerEventHandler(
			'sale',
			'\Bitrix\Sale\Internals\OrderProps::OnBeforeUpdate',
			$this->MODULE_ID,
			'\Intervolga\Migrato\Tool\EventHandlers\Sale',
			'onBeforeUpdateOrderPropsTable'
		);

		return true;
	}

	public function doUninstall()
	{
		try
		{
			$this->unInstallDb();
			Main\ModuleManager::unRegisterModule($this->MODULE_ID);
			$this->unInstallEvents();
		}
		catch (\Exception $e)
		{
			global $APPLICATION;
			$APPLICATION->ThrowException($e->getMessage());

			return false;
		}

		return true;
	}

	public function unInstallDb()
	{
		global $DB, $DBType;
		$errors = $DB->RunSQLBatch(__DIR__. "/db/" . strtolower($DBType) . "/uninstall.sql");
		if ($errors)
		{
			throw new \Exception(implode("<br>", $errors));
		}

		return true;
	}

	function unInstallEvents()
	{
		/**
		 * @see \Intervolga\Migrato\Tool\EventHandlers\Sale::onBeforePersonTypeUpdate
		 */
		EventManager::getInstance()->unRegisterEventHandler(
			'sale',
			'OnBeforePersonTypeUpdate',
			$this->MODULE_ID,
			'\Intervolga\Migrato\Tool\EventHandlers\Sale',
			'onBeforePersonTypeUpdate'
		);

		/**
		 * @see \Intervolga\Migrato\Tool\EventHandlers\Sale::onBeforeUpdateOrderPropsTable
		 */
		EventManager::getInstance()->unRegisterEventHandler(
			'sale',
			'\Bitrix\Sale\Internals\OrderProps::OnBeforeUpdate',
			$this->MODULE_ID,
			'\Intervolga\Migrato\Tool\EventHandlers\Sale',
			'onBeforeUpdateOrderPropsTable'
		);

		return true;
	}
}