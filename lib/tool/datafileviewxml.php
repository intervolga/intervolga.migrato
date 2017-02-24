<? namespace Intervolga\Migrato\Tool;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\Runtime;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Data\Values;

class DataFileViewXml
{
	const FILE_PREFIX = "data-";
	const FILE_EXT = "xml";
	const TABS_LENGTH = 4;
	const MAX_LENGTH_BEFORE_LINE_BREAK_TAG = 80;

	/**
	 * @param string $path
	 */
	public static function markDataDeleted($path)
	{
		foreach (static::getFiles($path) as $file)
		{
			static::markDataFileDeleted($file);
		}
	}

	/**
	 * @param \Bitrix\Main\IO\File $file
	 *
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	protected static function markDataFileDeleted(File $file)
	{
		$content = $file->getContents();
		$content = str_replace("<data>", "<data deleted=\"true\">", $content);
		$file->putContents($content);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $data
	 * @param string $path
	 */
	public static function writeToFileSystem(Record $data, $path)
	{
		$content = "";
		$content .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		$content .= "<data>\n";
		$content .= static::tag("xml_id", $data->getXmlId(), 1);
		if ($data->getDependencies())
		{
			$content .= static::dependencyToXml($data->getDependencies());
		}
		if ($data->getReferences())
		{
			$content .= static::referenceToXml($data->getReferences());
		}
		$content .= static::fieldToXml($data->getFields(), 1);
		foreach ($data->getRuntimes() as $name => $runtime)
		{
			$content .= "\t<runtime>\n";
			$content .= static::tag("name", $name, 2);
			$content .= static::valuesToXml($runtime->getFields(), 2);
			$content .= "\t</runtime>\n";
		}

		$content .= "</data>";

		$filePath = $path . static::FILE_PREFIX . $data->getXmlId() . "." . static::FILE_EXT;
		File::deleteFile($filePath);
		File::putFileContents($filePath, $content);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $links
	 *
	 * @return string
	 */
	protected static function dependencyToXml(array $links)
	{
		$content = "";
		foreach ($links as $name => $dependency)
		{
			$content .= "\t<dependency>\n";
			$content .= static::tag("name", $name, 2);
			$content .= static::tag("value", $dependency->getXmlId(), 2);
			$content .= "\t</dependency>\n";
		}

		return $content;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $links
	 *
	 * @return string
	 */
	protected static function referenceToXml(array $links)
	{
		$content = "";
		foreach ($links as $name => $dependency)
		{
			$content .= "\t<reference>\n";
			$content .= static::tag("name", $name, 2);
			$content .= static::tag("value", $dependency->getXmlId(), 2);
			$content .= "\t</reference>\n";
		}

		return $content;
	}

	/**
	 * @param array $fields
	 * @param int $level
	 *
	 * @return string
	 */
	protected static function fieldToXml(array $fields, $level = 0)
	{
		$content = "";
		foreach ($fields as $name => $value)
		{
			$content .= str_repeat("\t", $level) . "<field>\n";
			$content .= static::tag("name", $name, $level + 1);
			if (!is_array($value))
			{
				$value = array($value);
			}
			foreach ($value as $valueItem)
			{
				$content .= static::tag("value", $valueItem, $level + 1);
			}
			$content .= str_repeat("\t", $level) . "</field>\n";
		}

		return $content;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Values[] $fieldsValues
	 * @param int $level
	 *
	 * @return string
	 */
	protected static function valuesToXml(array $fieldsValues, $level = 0)
	{
		$content = "";
		foreach ($fieldsValues as $name => $fieldValues)
		{
			foreach ($fieldValues->getValues() as $value)
			{
				if (strlen($value->getValue()))
				{
					$content .= str_repeat("\t", $level) . "<field>\n";
					$content .= static::tag("name", $name, $level + 1);
					$content .= static::tag("value", $value->getValue(), $level + 1);
					if ($value->getDescription())
					{
						$content .= static::tag("description", $value->getDescription(), $level + 1);
					}
					$content .= str_repeat("\t", $level) . "</field>\n";
				}
			}
		}

		return $content;
	}

	/**
	 * @param string $path
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
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
	 *
	 * @return \Bitrix\Main\IO\File[]
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
	 * @param \Bitrix\Main\IO\File $file
	 *
	 * @return \Intervolga\Migrato\Data\Record
	 */
	protected static function parseFile(File $file)
	{
		$xmlParser = new \CDataXML();
		$xmlParser->Load($file->getPath());
		$xmlArray = $xmlParser->getArray();
		$record = new Record();
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
				$dependencies[$dependency["#"]["name"][0]["#"]] = new Link(null, $valueItem["#"]);
			}
		}
		$record->setDependencies($dependencies);

		$references = array();
		foreach ($xmlArray["data"]["#"]["reference"] as $reference)
		{
			foreach ($reference["#"]["value"] as $valueItem)
			{
				$references[$reference["#"]["name"][0]["#"]] = new Link(null, $valueItem["#"]);
			}
		}
		$record->setReferences($references);

		if ($xmlArray["data"]["#"]["runtime"])
		{
			$runtimes = static::parseReferences($xmlArray["data"]["#"]["runtime"]);
			$record->setRuntimes($runtimes);
		}

		return $record;
	}

	/**
	 * @param array $referenceNodes
	 *
	 * @return \Intervolga\Migrato\Data\Runtime[]
	 */
	protected static function parseReferences(array $referenceNodes)
	{
		$result = array();
		foreach ($referenceNodes as $referenceNode)
		{
			$runtime = new Runtime();
			$runtimeName = $referenceNode["#"]["name"][0]["#"];
			foreach ($referenceNode["#"]["field"] as $fieldNode)
			{
				$name = $fieldNode["#"]["name"][0]["#"];
				$value = $fieldNode["#"]["value"][0]["#"];
				$description = $fieldNode["#"]["description"][0]["#"];
				$valueObject = new Value($value);
				$valueObject->setDescription($description);
				$runtime->setField($name, new Values($valueObject));
			}
			$result[$runtimeName] = $runtime;
		}

		return $result;
	}

	/**
	 * @param string $tag
	 * @param string $value
	 * @param int $level
	 *
	 * @return string
	 */
	protected static function tag($tag, $value = "", $level = 0)
	{
		if (!strlen($value))
		{
			return str_repeat("\t", $level) . "<$tag/>\n";
		}
		else
		{
			$tagsSymbolsLength = 5;
			$inlineLength = $level*static::TABS_LENGTH
				+ $tagsSymbolsLength
				+ strlen($tag)*2
				+ strlen(htmlspecialchars($value));
			if ($inlineLength > static::MAX_LENGTH_BEFORE_LINE_BREAK_TAG)
			{
				return str_repeat("\t", $level)
				. "<$tag>\n"
				. str_repeat("\t", $level + 1)
				. htmlspecialchars($value)
				. "\n"
				. str_repeat("\t", $level)
				. "</$tag>\n";
			}
			else
			{
				return str_repeat("\t", $level) . "<$tag>" . htmlspecialchars($value) . "</$tag>\n";
			}
		}
	}
}