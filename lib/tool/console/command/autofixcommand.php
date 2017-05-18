<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\XmlIdValidateError;

Loc::loadMessages(__FILE__);

class AutofixCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('autofix');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.AUTOFIX_DESCRIPTION'));
	}

	public function executeInner()
	{
		/**
		 * @var ValidateCommand $validateCommand
		 */
		$validateCommand = $this->runSubcommand('validatexmlid');
		$this->reportTypeCounter = array();
		$errors = $validateCommand->getLastExecuteResult();
		$fixed = $this->fixErrors($errors);
		$this->reportShortSummary();
		if (!$errors)
		{
			$this->customFinalReport = Loc::getMessage('INTERVOLGA_MIGRATO.AUTOFIX_NO_NEED');
		}
		else
		{
			if ($fixed == count($errors))
			{
				$this->customFinalReport = Loc::getMessage(
					'INTERVOLGA_MIGRATO.AUTOFIX_X_ALL',
					array(
						'#X#' => $fixed,
					)
				);
			}
			else
			{
				$this->customFinalReport = Loc::getMessage(
					'INTERVOLGA_MIGRATO.AUTOFIX_X_OF_Y',
					array(
						'#X#' => $fixed,
						'#Y#' => count($errors),
					)
				);
			}
		}
	}

	/**
	 * @param XmlIdValidateError[] $errors
	 *
	 * @return int
	 */
	public function fixErrors(array $errors)
	{
		$counter = 0;
		foreach ($errors as $error)
		{
			$counter += $this->fixError($error);
		}

		return $counter;
	}

	/**
	 * @param \Intervolga\Migrato\Tool\XmlIdValidateError $error
	 *
	 * @return int
	 */
	protected function fixError(XmlIdValidateError $error)
	{
		$result = 0;
		try
		{
			$xmlId = $error->getDataClass()->generateXmlId($error->getId());
			$error->setXmlId($xmlId);
			$this->logRecord(array(
				'XML_ID_ERROR' => $error,
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.AUTOFIX'),
			));
			$result = 1;
		}
		catch (\Exception $exception)
		{
			$this->logRecord(array(
				'XML_ID_ERROR' => $error,
				'EXCEPTION' => $exception,
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.AUTOFIX'),
				'RESULT' => false,
			));
		}
		return $result;
	}
}