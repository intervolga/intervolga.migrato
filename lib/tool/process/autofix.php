<?namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Tool\Orm\LogTable;
use Intervolga\Migrato\Tool\XmlIdValidateError;

class AutoFix extends BaseProcess
{
	public static function run()
	{
		$errors = Validate::validate();
		parent::run();

		static::$step = "autofix";
		static::reportSeparator();
		static::report(static::$step);

		static::fixErrors($errors);
		static::reportStep(static::$step);

		static::finalReport();
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
					"OPERATION" => "xmlid error fix",
					"STEP" => static::$step,
				));
				$counter++;
			}
			catch (\Exception $exception)
			{
				LogTable::add(array(
					"XML_ID_ERROR" => $error,
					"EXCEPTION" => $exception,
					"OPERATION" => "xmlid error fix",
					"STEP" => static::$step,
					"RESULT" => false,
				));
			}
		}

		return $counter;
	}
}