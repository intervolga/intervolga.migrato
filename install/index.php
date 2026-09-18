<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\IO\Directory;

Loc::loadMessages(__FILE__);

class intervolga_migrato extends CModule
{
	const MINIMAL_VERSION_PHP = '5.5.9';
	const MINIMAL_VERSION_BITRIX = '15.0.15';
	/**
	 * Страницы административного раздела, для которых создаются файлы-обертки в /bitrix/admin/
	 */
	const ADMIN_PAGES = array(
		'index',
		'run',
		'execute',
		'log',
		'config',
	);
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
		global $APPLICATION;
		if($this->checkMinRequirements())
		{
			try
			{
				$this->installDb();
				$this->copyPublicFiles();
				$this->installAdminFiles();
				Main\ModuleManager::registerModule($this->MODULE_ID);
				$this->installEvents();
			}
			catch (\Exception $e)
			{
				$APPLICATION->ThrowException($e->getMessage());

				return false;
			}

			if(!class_exists("\Symfony\Component\Console\Application"))
			{
				CAdminNotify::Add(
					[
						"MESSAGE" => Loc::getMessage('INTERVOLGA_MIGRATO_NOT_FIND_SYMFONY'),
						"TAG" => 'intervolga.migrato.notification',
						"MODULE_ID" => $this->MODULE_ID,
						"ENABLE_CLOSE" => "Y"
					]
				);
			}
			return true;
		}
		else
		{
			return false;
		}
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
        if (!Directory::isDirectoryExists(INTERVOLGA_MIGRATO_DIRECTORY))
        {
            Directory::createDirectory(INTERVOLGA_MIGRATO_DIRECTORY);
        }

        $files = scandir(__DIR__ . '/public');
        foreach ($files as $file)
        {
            if ($file === '.' || $file === '..')
            {
                continue;
            }

            $fileExist = file_exists(INTERVOLGA_MIGRATO_DIRECTORY . '/' . $file);
            if ($fileExist === false)
            {
                copy(__DIR__ . '/public/' . $file, INTERVOLGA_MIGRATO_DIRECTORY . '/' . $file);
            }
        }
	}

	/**
	 * Создает в /bitrix/admin/ файлы-обертки для страниц модуля
	 */
	public function installAdminFiles()
	{
		$adminDirectory = Main\Application::getDocumentRoot() . '/bitrix/admin/';
		if (!Directory::isDirectoryExists($adminDirectory))
		{
			Directory::createDirectory($adminDirectory);
		}
		foreach (self::ADMIN_PAGES as $page)
		{
			file_put_contents(
				$adminDirectory . $this->getAdminFileName($page),
				$this->getAdminFileContent($page)
			);
		}

		return true;
	}

	/**
	 * Удаляет из /bitrix/admin/ файлы-обертки страниц модуля
	 */
	public function unInstallAdminFiles()
	{
		$adminDirectory = Main\Application::getDocumentRoot() . '/bitrix/admin/';
		foreach (self::ADMIN_PAGES as $page)
		{
			$file = new Main\IO\File($adminDirectory . $this->getAdminFileName($page));
			if ($file->isExists())
			{
				$file->delete();
			}
		}

		return true;
	}

	/**
	 * @param string $page
	 *
	 * @return string
	 */
	protected function getAdminFileName($page)
	{
		return str_replace('.', '_', $this->MODULE_ID) . '_' . $page . '.php';
	}

	/**
	 * @param string $page
	 *
	 * @return string
	 */
	protected function getAdminFileContent($page)
	{
		$template = <<<'PHP'
<?php
$path = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/#MODULE_ID#/admin/#PAGE#.php';
if (!file_exists($path))
{
	$path = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/#MODULE_ID#/admin/#PAGE#.php';
}
require($path);

PHP;

		return str_replace(
			array('#MODULE_ID#', '#PAGE#'),
			array($this->MODULE_ID, $page),
			$template
		);
	}

	/**
	 * Уровни доступа к модулю
	 *
	 * @return array
	 */
	public function GetModuleRightList()
	{
		return array(
			'reference_id' => array('D', 'R', 'W'),
			'reference' => array(
				'[D] ' . Loc::getMessage('INTERVOLGA_MIGRATO_RIGHT_D'),
				'[R] ' . Loc::getMessage('INTERVOLGA_MIGRATO_RIGHT_R'),
				'[W] ' . Loc::getMessage('INTERVOLGA_MIGRATO_RIGHT_W'),
			),
		);
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

		/**
		 * @see \Intervolga\Migrato\Tool\EventHandlers\Catalog::onBeforeCatalogStoreAdd
		 */
		EventManager::getInstance()->registerEventHandler(
			'catalog',
			'OnBeforeCatalogStoreAdd',
			$this->MODULE_ID,
			'\Intervolga\Migrato\Tool\EventHandlers\Catalog',
			'onBeforeCatalogStoreAdd'
		);

		return true;
	}

	public function doUninstall()
	{
		try
		{
			$this->unInstallDb();
			$this->unInstallAdminFiles();
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

		/**
		 * @see \Intervolga\Migrato\Tool\EventHandlers\Catalog::onBeforeCatalogStoreAdd
		 */
		EventManager::getInstance()->unRegisterEventHandler(
			'catalog',
			'OnBeforeCatalogStoreAdd',
			$this->MODULE_ID,
			'\Intervolga\Migrato\Tool\EventHandlers\Catalog',
			'onBeforeCatalogStoreAdd'
		);

		return true;
	}

	private function checkMinRequirements()
	{
		global $APPLICATION;
		if(version_compare(phpversion(), self::MINIMAL_VERSION_PHP) < 0)
		{
			$APPLICATION->ThrowException(Loc::getMessage("INTERVOLGA_MIGRATO_SMALL_VERSION", array(
				"#OBJECT#" => "PHP",
				"#CURRENT_VERSION#" => phpversion(),
				"#MINIMAL_VERSION#" => self::MINIMAL_VERSION_PHP,
			)));
			return false;
		}
		if(version_compare(SM_VERSION, self::MINIMAL_VERSION_BITRIX) < 0)
		{
			$APPLICATION->ThrowException(Loc::getMessage("INTERVOLGA_MIGRATO_SMALL_VERSION", array(
				"#OBJECT#" => "Bitrix",
				"#CURRENT_VERSION#" => SM_VERSION,
				"#MINIMAL_VERSION#" => self::MINIMAL_VERSION_BITRIX,
			)));
			return false;
		}
		return true;
	}
}