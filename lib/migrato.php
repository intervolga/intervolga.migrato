<?namespace Intervolga\Migrato;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\XmlIdValidateError;

class Migrato
{
	/**
	 * @return string[]
	 */
	public static function run()
	{
		return array();
	}

	/**
	 * @param BaseData[] $dataClasses
	 * @return BaseData[]
	 */
	protected static function recursiveGetDependentDataClasses(array $dataClasses)
	{
		foreach ($dataClasses as $dataClass)
		{
			$dependencies = $dataClass->getDependencies();
			if ($dependencies)
			{
				foreach ($dependencies as $dependency)
				{
					$dependentDataClass = $dependency->getTargetData();
					if (!in_array($dependentDataClass, $dataClasses))
					{
						$dataClasses[] = $dependentDataClass;
					}
				}
			}
			$references = $dataClass->getReferences();
			if ($references)
			{
				foreach ($references as $reference)
				{
					$dependentDataClass = $reference->getTargetData();
					if (!in_array($dependentDataClass, $dataClasses))
					{
						$dataClasses[] = $dependentDataClass;
					}
				}
			}
		}
		return $dataClasses;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param array|string[] $filter
	 *
	 * @return array|\Intervolga\Migrato\Tool\XmlIdValidateError[]
	 */
	protected static function validateXmlIds(BaseData $dataClass, array $filter = array())
	{
		$errors = array();
		$records = $dataClass->getList($filter);
		$xmlIds[] = array();
		foreach ($records as $record)
		{
			$errorType = 0;
			if ($record->getXmlId())
			{
				$matches = array();
				if (preg_match_all("/^[a-z0-9\-_]*$/i", $record->getXmlId(), $matches))
				{
					if (!in_array($record->getXmlId(), $xmlIds))
					{
						$xmlIds[] = $record->getXmlId();
					}
					else
					{
						$errorType = XmlIdValidateError::TYPE_REPEAT;
					}
				}
				else
				{
					$errorType = XmlIdValidateError::TYPE_INVALID;
				}
			}
			else
			{
				$errorType = XmlIdValidateError::TYPE_EMPTY;

			}
			if ($errorType)
			{
				$errors[] = new XmlIdValidateError($errorType, $record->getId(), $record->getXmlId());
			}
		}
		return $errors;
	}

	/**
	 * @param array|XmlIdValidateError[] $errors
	 */
	protected static function fixErrors(BaseData $dataClass, array $errors)
	{
		foreach ($errors as $error)
		{
			if ($error->getType() == XmlIdValidateError::TYPE_EMPTY)
			{
				$dataClass->getXmlIdProvider()->generateXmlId($error->getId());
			}
			elseif ($error->getType() == XmlIdValidateError::TYPE_INVALID)
			{
				$xmlId = $dataClass->getXmlIdProvider()->getXmlId($error->getId());
				$xmlId = preg_replace("/[^a-z0-9\-_]/", "-", $xmlId);
				$dataClass->getXmlIdProvider()->setXmlId($error->getId(), $xmlId);
			}
			elseif ($error->getType() == XmlIdValidateError::TYPE_REPEAT)
			{
				$dataClass->getXmlIdProvider()->generateXmlId($error->getId());
			}
		}
	}

	/**
	 * @param string $module
	 * @return string
	 */
	protected static function getModuleOptionsDirectory($module)
	{
		return INTERVOLGA_MIGRATO_DIRECTORY . $module . "/";
	}
}