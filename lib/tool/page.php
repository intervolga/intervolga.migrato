<?namespace Intervolga\Migrato\Tool;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class Page
{
	/**
	 * @throws \Exception
	 */
	public static function checkRights()
	{
		global $USER;
		if(static::isCli())
		{
			$USER->Authorize(1);
		}
		else
		{
			throw new \Exception('Use command line interface');
		}
	}

	/**
	 * @param \Exception $exception
	 */
	public static function handleException(\Exception $exception)
	{
		$formattedName = Loc::getMessage(
			'INTERVOLGA_MIGRATO.EXCEPTION',
			array(
				'#CLASS#' => get_class($exception)
			)
		);
		$formattedMessage = Loc::getMessage(
			'INTERVOLGA_MIGRATO.EXCEPTION_MESSAGE_CODE',
			array(
				'#MESSAGE#' => $exception->getMessage(),
				'#CODE#' => $exception->getCode(),
			)
		);
		$report = array(
			ColorLog::getColoredString($formattedName, 'fail'),
			ColorLog::getColoredString($formattedMessage, 'fail'),
			'',
			Loc::getMessage('INTERVOLGA_MIGRATO.BACKTRACE'),
			'## ' . $exception->getFile() . '(' . $exception->getLine() . ')',
			$exception->getTraceAsString(),
		);
		static::showReport($report);
	}

	/**
	 * @param \Error $error
	 */
	public static function handleError(\Error $error)
	{
		$formattedName = Loc::getMessage(
			'INTERVOLGA_MIGRATO.EXCEPTION',
			array(
				'#CLASS#' => get_class($error)
			)
		);
		$formattedMessage = Loc::getMessage(
			'INTERVOLGA_MIGRATO.EXCEPTION_MESSAGE_CODE',
			array(
				'#MESSAGE#' => $error->getMessage(),
				'#CODE#' => $error->getCode(),
			)
		);
		$report = array(
			ColorLog::getColoredString($formattedName, 'fail'),
			ColorLog::getColoredString($formattedMessage, 'fail'),
			'',
			Loc::getMessage('INTERVOLGA_MIGRATO.BACKTRACE'),
			'## ' . $error->getFile() . '(' . $error->getLine() . ')',
			$error->getTraceAsString(),
		);
		static::showReport($report);
	}

	/**
	 * @param string[] $report
	 */
	public static function showReport(array $report, $encodingUtf8 = false)
	{
		if(!$encodingUtf8)
		{
			foreach ($report as $i => $string)
			{
				$report[$i] = iconv('UTF-8', 'cp1251', $string);
			}
		}

		echo implode("\r\n", $report)."\r\n";
		global $USER;
		$USER->Logout();
	}

	/**
	 * @return bool
	 */
	public static function isCli()
	{
		return php_sapi_name() == 'cli';
	}
}