<?namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Intervolga\Migrato\Tool\XmlIdValidateError;

Loc::loadMessages(__FILE__);

class AutoFix extends BaseProcess
{
	public static function run()
	{
		$errors = Validate::validate();
		parent::run();

		static::startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_AUTOFIX'));
		static::fixErrors($errors);
		static::reportStepLogs();

		static::finalReport();
	}

	public static function autofix()
	{
		$errors = Validate::validate();

		static::startStep(Loc::getMessage('INTERVOLGA_MIGRATO.STEP_AUTOFIX'));
		static::fixErrors($errors);
		static::reportStepLogs();
	}

	/**
	 * @param XmlIdValidateError[] $errors
	 *
	 * @return int
	 */
	public static function fixErrors(array $errors)
	{
		$counter = 0;
		foreach ($errors as $error)
		{
			try
			{
				$xmlId = $error->getDataClass()->generateXmlId($error->getId());
				$error->setXmlId($xmlId);
				LogTable::add(array(
					"XML_ID_ERROR" => $error,
					"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_AUTOFIX'),
					"STEP" => static::$step,
				));
				$counter++;
			}
			catch (\Exception $exception)
			{
				LogTable::add(array(
					"XML_ID_ERROR" => $error,
					"EXCEPTION" => $exception,
					"OPERATION" => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_AUTOFIX'),
					"STEP" => static::$step,
					"RESULT" => false,
				));
			}
		}

		return $counter;
	}
}