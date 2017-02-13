<? namespace Intervolga\Migrato\Base;

use Bitrix\Main\IO\Directory;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\DataRecord;

abstract class Data
{
	/**
	 * @return string
	 */
	public static function getModule()
	{
		$class = get_called_class();
		$tmp = str_replace("Intervolga\\Migrato\\Module\\", "", $class);
		$tmp = substr($tmp, 0, strpos($tmp, "\\"));
		$tmp = strtolower($tmp);

		return $tmp;
	}

	/**
	 * @return string
	 */
	public static function getEntityName()
	{
		$class = get_called_class();
		$tmp = substr($class, strrpos($class, "\\") + 1);
		$tmp = strtolower($tmp);

		return $tmp;
	}

	/**
	 * @return bool
	 */
	public static function isXmlIdFieldExists()
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public static function createXmlIdField()
	{
		return true;
	}

	/**
	 * @return array|DataRecord[]
	 */
	public static function getFromDatabase()
	{
		return array();
	}

	/**
	 * @return array|string[]
	 */
	public static function getXmlIdsFromDatabase()
	{
		$result = array();
		$records = static::getFromDatabase();
		foreach ($records as $record)
		{
			if ($record->getXmlId())
			{
				$result[$record->getXmlId()] = $record->getXmlId();
			}
		}

		return array_values($result);
	}

	/**
	 * @return array|string[]
	 */
	public static function validateXmlIds()
	{
		$errors = array();
		$records = static::getFromDatabase();
		$xmlIds[] = array();
		foreach ($records as $record)
		{
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
						$errors["repeat"][$record->getLocalDbId()] = "Repeat XML ID: " . $record->getXmlId();
					}
				}
				else
				{
					$errors["invalid"][$record->getLocalDbId()] = "Invalid XML ID: " . $record->getXmlId();
				}
			}
			else
			{
				$errors["empty"][$record->getLocalDbId()] = "No XML ID (ID=" . $record->getLocalDbId() . ")";
			}
		}
		return $errors;
	}

	/**
	 * @param array $errors
	 */
	public static function fixErrors(array $errors)
	{
		foreach ($errors["empty"] as $localId => $message)
		{
			static::setXmlId($localId, static::makeXmlId());
		}
		foreach ($errors["invalid"] as $localId => $message)
		{
			$xmlId = static::getXmlId($localId);
			$xmlId = preg_replace("/[^a-z0-9\-_]/", "-", $xmlId);
			static::setXmlId($localId, $xmlId);
		}
		foreach ($errors["repeat"] as $localId => $message)
		{
			static::setXmlId($localId, static::makeXmlId());
		}
	}

	/**
	 * @return string
	 */
	protected static function makeXmlId()
	{
		$xmlid = uniqid("", true);
		$xmlid = str_replace(".", "", $xmlid);
		$xmlid = str_split($xmlid, 6);
		$xmlid = implode("-", $xmlid);
		return $xmlid;
	}

	/**
	 * @param int|string $id
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	public static function setXmlId($id, $xmlId)
	{
		return false;
	}

	/**
	 * @param int|string $id
	 *
	 * @return string
	 */
	public static function getXmlId($id)
	{
		return "";
	}

	public static function exportToFile()
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . static::getModule() . "/" . static::getEntityName() . "/";
		Directory::deleteDirectory($path);
		checkDirPath($path);

		$records = static::getFromDatabase();
		foreach ($records as $record)
		{
			DataFileViewXml::writeToFileSystem($record, $path);
		}
	}

	/**
	 * @return array|\Intervolga\Migrato\Tool\DataRecord[]
	 */
	public static function importFromFile()
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . static::getModule() . "/" . static::getEntityName() . "/";

		$localXmlIds = static::getXmlIdsFromDatabase();
		$fileRecords = DataFileViewXml::readFromFileSystem($path);
		$fileXmlIds = array();
		foreach ($fileRecords as $fileRecord)
		{
			$fileXmlIds[] = $fileRecord->getXmlId();
			if (in_array($fileRecord->getXmlId(), $localXmlIds))
			{
				static::update($fileRecord);
			}
			else
			{
				static::create($fileRecord);
			}
		}
		foreach ($localXmlIds as $localXmlId)
		{
			if (!in_array($localXmlId, $fileXmlIds))
			{
				static::delete($localXmlId);
			}
		}

		return DataFileViewXml::readFromFileSystem($path);
	}

	protected static function update(DataRecord $record)
	{
		// todo
	}

	protected static function create(DataRecord $record)
	{
		// todo
	}

	protected static function delete($xmlId)
	{
		// todo
	}
}