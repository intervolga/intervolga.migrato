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
	 *
	 * @return string[]
	 */
	public static function handleException(\Exception $exception)
	{
		$report = array(
			"EXCEPTION (Class: " . get_class($exception) . ")",
			"Message: " . $exception->getMessage() . " (Code: " . $exception->getCode() . ")",
			"Location: " . $exception->getFile() . ":" . $exception->getLine()
		);
		return $report;
	}

	/**
	 * @param string[] $report
	 */
	public static function showReport($report)
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