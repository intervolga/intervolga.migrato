<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\PublicCache;
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
		$this->logger->separate();
		$this->logger->resetTypesCounter();
		$errors = $validateCommand->getLastExecuteResult();
		$fixed = $this->fixErrors($errors);
		if (!$errors)
		{
			$this->logger->registerFinal(
				Loc::getMessage('INTERVOLGA_MIGRATO.AUTOFIX_NO_NEED'),
				Logger::TYPE_OK
			);
		}
		else
		{
			if ($fixed == count($errors))
			{
				$this->logger->registerFinal(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.AUTOFIX_X_ALL',
						array(
							'#X#' => $fixed,
						)
					),
					Logger::TYPE_OK
				);
			}
			else
			{
				$this->logger->registerFinal(Loc::getMessage(
						'INTERVOLGA_MIGRATO.AUTOFIX_X_OF_Y',
						array(
							'#X#' => $fixed,
							'#Y#' => count($errors),
						)
					),
					Logger::TYPE_FAIL
				);
			}
		}
		PublicCache::getInstance()->clearTagCache();
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
			if ($xmlId)
			{
				$error->setXmlId($xmlId);
				$this->logger->addDb(
					array(
						'XML_ID_ERROR' => $error,
						'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.AUTOFIX'),
					),
					Logger::TYPE_OK
				);
				$result = 1;
			}
			else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.AUTOFIX_ERROR_NOT_SET'));
			}
		}
		catch (\Exception $exception)
		{
			$this->logger->addDb(
				array(
					'XML_ID_ERROR' => $error,
					'EXCEPTION' => $exception,
					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.AUTOFIX'),
				),
				Logger::TYPE_FAIL
			);
		}
		return $result;
	}
}