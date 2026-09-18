<?php namespace Intervolga\Migrato\Tool\Web;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Вспомогательные методы административных страниц модуля
 */
class Helper
{
	const MODULE_ID = 'intervolga.migrato';

	const PAGE_INDEX = 'intervolga_migrato_index.php';
	const PAGE_RUN = 'intervolga_migrato_run.php';
	const PAGE_EXECUTE = 'intervolga_migrato_execute.php';
	const PAGE_LOG = 'intervolga_migrato_log.php';
	const PAGE_CONFIG = 'intervolga_migrato_config.php';

	/**
	 * Уровень доступа текущего пользователя к модулю
	 *
	 * @return string D|R|W
	 */
	public static function getRight()
	{
		global $APPLICATION;

		return $APPLICATION->GetGroupRight(static::MODULE_ID);
	}

	/**
	 * @return bool
	 */
	public static function canRead()
	{
		return static::getRight() >= 'R';
	}

	/**
	 * @return bool
	 */
	public static function canWrite()
	{
		return static::getRight() >= 'W';
	}

	/**
	 * Прерывает работу страницы, если нет прав на чтение
	 */
	public static function checkReadRights()
	{
		if (!static::canRead())
		{
			static::accessDenied();
		}
	}

	/**
	 * Прерывает работу страницы, если нет прав на запуск команд
	 */
	public static function checkWriteRights()
	{
		if (!static::canWrite())
		{
			static::accessDenied();
		}
	}

	protected static function accessDenied()
	{
		global $APPLICATION;
		$APPLICATION->AuthForm(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_ACCESS_DENIED'));
	}

	/**
	 * Меню страниц модуля для CAdminTabControl/контекстного меню
	 *
	 * @param string $current текущая страница
	 *
	 * @return array
	 */
	public static function getPagesMenu($current = '')
	{
		$pages = array(
			static::PAGE_INDEX => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_COMMANDS'),
			static::PAGE_LOG => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_LOG'),
			static::PAGE_CONFIG => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_CONFIG'),
		);
		$result = array();
		foreach ($pages as $page => $text)
		{
			$result[] = array(
				'TEXT' => $text,
				'LINK' => static::getUrl($page),
				'ICON' => 'btn',
				'DEFAULT' => ($page === $current),
			);
		}

		return $result;
	}

	/**
	 * @param string $page
	 * @param array $params
	 *
	 * @return string
	 */
	public static function getUrl($page, array $params = array())
	{
		$params['lang'] = LANGUAGE_ID;
		$query = array();
		foreach ($params as $key => $value)
		{
			$query[] = urlencode($key) . '=' . urlencode($value);
		}

		return $page . '?' . implode('&', $query);
	}

	/**
	 * Установлен ли symfony/console, без которого команды не запускаются
	 *
	 * @return bool
	 */
	public static function isConsoleAvailable()
	{
		return class_exists('\Symfony\Component\Console\Application');
	}

	/**
	 * Существует ли конфигурационный файл модуля
	 *
	 * @return bool
	 */
	public static function isConfigExists()
	{
		return file_exists(INTERVOLGA_MIGRATO_CONFIG_PATH);
	}

	/**
	 * @return string
	 */
	public static function getConfigPath()
	{
		return INTERVOLGA_MIGRATO_CONFIG_PATH;
	}

	/**
	 * Путь к конфигу относительно корня сайта
	 *
	 * @return string
	 */
	public static function getConfigRelativePath()
	{
		$documentRoot = \Bitrix\Main\Application::getDocumentRoot();
		$path = str_replace('\\', '/', static::getConfigPath());
		$documentRoot = str_replace('\\', '/', $documentRoot);

		return str_replace($documentRoot, '', $path);
	}

	/**
	 * Файл конфига по умолчанию, поставляемый с модулем
	 *
	 * @return string
	 */
	public static function getDefaultConfigPath()
	{
		return dirname(dirname(dirname(__DIR__))) . '/install/public/config.xml';
	}

	/**
	 * Проверяет XML на синтаксические ошибки
	 *
	 * @param string $xml
	 *
	 * @return string текст ошибки либо пустая строка
	 */
	public static function getXmlError($xml)
	{
		$previous = libxml_use_internal_errors(true);
		libxml_clear_errors();
		simplexml_load_string($xml);
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$messages = array();
		foreach ($errors as $error)
		{
			$messages[] = Loc::getMessage(
				'INTERVOLGA_MIGRATO.WEB_XML_ERROR_LINE',
				array(
					'#LINE#' => $error->line,
					'#MESSAGE#' => trim($error->message),
				)
			);
		}

		return implode('; ', $messages);
	}
}
