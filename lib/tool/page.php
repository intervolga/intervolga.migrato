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
		if (!static::isCli() && !$USER->IsAdmin())
		{
			throw new \Exception('Need cli or admin login');
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
	 * @param string[] $report
	 */
	public static function showReport(array $report, $encodingUtf8 = false)
	{
		if (static::isCli())
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
		else
		{
			echo '<pre>' . implode('<br>', $report) . '</pre>';
		}
	}

	/**
	 * @return bool
	 */
	public static function isCli()
	{
		return php_sapi_name() == 'cli';
	}
}