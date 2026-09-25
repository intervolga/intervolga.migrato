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
	const PAGE_OVERVIEW = 'intervolga_migrato_overview.php';
	const PAGE_CONFIG = 'intervolga_migrato_config.php';
	const PAGE_SETTINGS = 'settings.php';

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
			static::PAGE_INDEX => array(
				'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_COMMANDS'),
				'LINK' => static::getUrl(static::PAGE_INDEX),
			),
			static::PAGE_OVERVIEW => array(
				'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_OVERVIEW'),
				'LINK' => static::getUrl(static::PAGE_OVERVIEW),
			),
			static::PAGE_LOG => array(
				'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_LOG'),
				'LINK' => static::getUrl(static::PAGE_LOG),
			),
			static::PAGE_SETTINGS => array(
				'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_SETTINGS'),
				'LINK' => static::getSettingsUrl(),
			),
			static::PAGE_CONFIG => array(
				'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MENU_CONFIG'),
				'LINK' => static::getFileManUrl(),
			),
		);
		$result = array();
		foreach ($pages as $page => $item)
		{
			$item['ICON'] = 'btn';
			$item['DEFAULT'] = ($page === $current);
			$result[] = $item;
		}

		return $result;
	}

	/**
	 * Адрес страницы настроек модуля в стандартном разделе Битрикс
	 *
	 * @return string
	 */
	public static function getSettingsUrl()
	{
		return static::PAGE_SETTINGS . '?lang=' . LANGUAGE_ID
			. '&mid=' . urlencode(static::MODULE_ID) . '&mid_menu=1';
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
		return static::getRelativePath(static::getConfigPath());
	}

	/**
	 * Путь относительно корня сайта
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public static function getRelativePath($path)
	{
		$documentRoot = str_replace('\\', '/', \Bitrix\Main\Application::getDocumentRoot());
		$path = str_replace('\\', '/', $path);

		return str_replace($documentRoot, '', $path);
	}

	/**
	 * Файл слепка структуры БД, который создает команда snapshot
	 *
	 * @return string
	 */
	public static function getSnapshotPath()
	{
		return INTERVOLGA_MIGRATO_DIRECTORY . 'snapshot.xml';
	}

	/**
	 * Сведения о файле слепка
	 *
	 * @return array array('EXISTS' =>, 'PATH' =>, 'RELATIVE_PATH' =>, 'TIME' =>, 'SIZE' =>)
	 */
	public static function getSnapshotInfo()
	{
		$path = static::getSnapshotPath();
		$exists = file_exists($path);

		return array(
			'EXISTS' => $exists,
			'PATH' => $path,
			'RELATIVE_PATH' => static::getRelativePath($path),
			'TIME' => $exists ? (int)filemtime($path) : 0,
			'SIZE' => $exists ? (int)filesize($path) : 0,
		);
	}

	/**
	 * Ссылка на config.xml в стандартном разделе "Файлы и папки".
	 * Если модуль fileman недоступен, возвращает адрес страницы модуля
	 * с ручным редактором.
	 *
	 * @return string
	 */
	public static function getFileManUrl()
	{
		if (!static::isFileManAvailable())
		{
			return static::getUrl(static::PAGE_CONFIG);
		}

		return static::getFileManEditUrl(static::getConfigRelativePath(), static::getSettingsUrl());
	}

	/**
	 * @return bool
	 */
	public static function isFileManAvailable()
	{
		return \Bitrix\Main\ModuleManager::isModuleInstalled('fileman');
	}

	/**
	 * Редактирование файла в разделе "Файлы и папки"
	 *
	 * @param string $relativePath путь от корня сайта
	 * @param string $backUrl
	 *
	 * @return string
	 */
	public static function getFileManEditUrl($relativePath, $backUrl = '')
	{
		return static::getFileManPageUrl('fileman_file_edit.php', $relativePath, $backUrl);
	}

	/**
	 * Просмотр файла в разделе "Файлы и папки"
	 *
	 * @param string $relativePath путь от корня сайта
	 * @param string $backUrl
	 *
	 * @return string
	 */
	public static function getFileManViewUrl($relativePath, $backUrl = '')
	{
		return static::getFileManPageUrl('fileman_file_view.php', $relativePath, $backUrl);
	}

	/**
	 * @param string $page
	 * @param string $relativePath
	 * @param string $backUrl
	 *
	 * @return string
	 */
	protected static function getFileManPageUrl($page, $relativePath, $backUrl = '')
	{
		if (!static::isFileManAvailable())
		{
			return $relativePath;
		}
		$url = '/bitrix/admin/' . $page . '?lang=' . LANGUAGE_ID
			. '&site=' . urlencode(static::getSiteId())
			. '&path=' . urlencode($relativePath);
		if ($backUrl)
		{
			$url .= '&back_url=' . urlencode($backUrl);
		}

		return $url;
	}

	/**
	 * Сайт, от корня которого отсчитываются пути в разделе "Файлы и папки"
	 *
	 * @return string
	 */
	protected static function getSiteId()
	{
		if (defined('SITE_ID') && SITE_ID)
		{
			return SITE_ID;
		}

		return (string)\CSite::GetDefSite();
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
