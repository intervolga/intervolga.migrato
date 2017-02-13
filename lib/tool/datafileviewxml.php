<? namespace Intervolga\Migrato\Tool;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;

class DataFileViewXml
{
	const FILE_PREFIX = "data-";
	const FILE_EXT = "xml";

	/**
	 * @param \Intervolga\Migrato\Tool\DataRecord $data
	 * @param string $path
	 */
	public static function writeToFileSystem(DataRecord $data, $path)
	{
		$content = "";
		$content .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		$content .= "<data>\n";
		$content .= "\t<xml_id>" . $data->getXmlId() . "</xml_id>\n";
		$content .= static::dependencyToXml($data->getDependencies());
		$content .= static::fieldToXml($data->getFields());
		$content .= "</data>";

		$filePath = $path . static::FILE_PREFIX . $data->getXmlId() . "." . static::FILE_EXT;
		File::deleteFile($filePath);
		File::putFileContents($filePath, $content);
	}

	/**
	 * @param array $dependencies
	 * @return string
	 */
	protected static function dependencyToXml(array $dependencies)
	{
		return static::mapToXml("dependency", $dependencies);
	}

	/**
	 * @param string $tag
	 * @param array $map
	 * @return string
	 */
	protected static function mapToXml($tag, array $map)
	{
		$content = "";
		foreach ($map as $name => $value)
		{
			$content .= "\t<{$tag}>\n";
			$content .= "\t\t<name>" . htmlspecialchars($name) . "</name>\n";
			if (!is_array($value))
			{
				$value = array($value);
			}
			foreach ($value as $valueItem)
			{
				$content .= static::valueToXml(2, $valueItem);
			}
			$content .= "\t</{$tag}>\n";
		}

		return $content;
	}

	/**
	 * @param int $level
	 * @param string $value
	 * @return string
	 */
	protected static function valueToXml($level, $value)
	{
		$content = str_repeat("\t", $level);
		if (strlen($value))
		{
			$content .= "<value>" . htmlspecialchars($value) . "</value>";
		}
		else
		{
			$content .= "<value/>";
		}
		$content .= "\n";

		return $content;
	}

	/**
	 * @param array $fields
	 * @return string
	 */
	protected static function fieldToXml(array $fields)
	{
		return static::mapToXml("field", $fields);
	}

	/**
	 * @param string $path
	 *
	 * @return array|DataRecord[]
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	public static function readFromFileSystem($path)
	{
		$result = array();
		foreach (static::getFiles($path) as $fileSystemEntry)
		{
			$result[] = static::parseFile($fileSystemEntry);
		}

		return $result;
	}

	/**
	 * @param string $path
	 * @return array|File[]
	 */
	protected static function getFiles($path)
	{
		$result = array();
		$directory = new Directory($path);
		if ($directory->isExists())
		{
			foreach ($directory->getChildren() as $fileSystemEntry)
			{
				if ($fileSystemEntry instanceof File)
				{
					$name = strtolower($fileSystemEntry->getName());
					$extension = strtolower($fileSystemEntry->getExtension());
					if ((strpos($name, static::FILE_PREFIX) === 0) && $extension == static::FILE_EXT)
					{
						$result[] = $fileSystemEntry;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @param File $file
	 * @return DataRecord
	 */
	protected static function parseFile(File $file)
	{
		$xmlParser = new \CDataXML();
		$xmlParser->Load($file->getPath());
		$xmlArray = $xmlParser->getArray();
		$record = new DataRecord();
		$record->setXmlId($xmlArray["data"]["#"]["xml_id"][0]["#"]);

		$fields = array();
		foreach ($xmlArray["data"]["#"]["field"] as $field)
		{
			$fields[$field["#"]["name"][0]["#"]] = $field["#"]["value"][0]["#"];
		}
		$record->setFields($fields);

		$dependencies = array();
		foreach ($xmlArray["data"]["#"]["dependency"] as $dependency)
		{
			foreach ($dependency["#"]["value"] as $valueItem)
			{
				if ($dependencies[$dependency["#"]["name"][0]["#"]])
				{
					if (!is_array($dependencies[$dependency["#"]["name"][0]["#"]]))
					{
						$dependencies[$dependency["#"]["name"][0]["#"]] = array(
							$dependencies[$dependency["#"]["name"][0]["#"]]
						);
					}
					$dependencies[$dependency["#"]["name"][0]["#"]][] = $valueItem["#"];
				}
				else
				{
					$dependencies[$dependency["#"]["name"][0]["#"]] = $valueItem["#"];
				}
			}
		}
		$record->setDependencies($dependencies);

		return $record;
	}
}