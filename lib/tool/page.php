<?namespace Intervolga\Migrato\Tool;

use Bitrix\Main\AccessDeniedException;

class Page
{
	/**
	 * @throws \Bitrix\Main\AccessDeniedException
	 */
	public static function checkRights()
	{
		global $USER;
		$isCli = php_sapi_name() == "cli";
		if (!$isCli && !$USER->IsAdmin())
		{
			throw new AccessDeniedException("Need cli or admin login");
		}
	}

	/**
	 * @param \Exception $exception
	 */
	public static function handleException(\Exception $exception)
	{
		$report = array(
			"EXCEPTION (Class: " . get_class($exception) . ")",
			"Message: " . $exception->getMessage() . " (Code: " . $exception->getCode() . ")",
			"Location: " . $exception->getFile() . ":" . $exception->getLine()
		);
		static::showReport($report);
	}

	/**
	 * @param string[] $report
	 */
	public static function showReport(array $report)
	{
		$isCli = php_sapi_name() == "cli";
		if ($isCli)
		{
			echo implode("\r\n", $report)."\r\n";
		}
		else
		{
			echo "<pre>" . implode("<br>", $report) . "</pre>";
		}
	}
}