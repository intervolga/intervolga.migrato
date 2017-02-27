<? namespace Intervolga\Migrato\Tool;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\Runtime;
use Intervolga\Migrato\Data\Value;

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
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $path
	 */
	public static function writeToFileSystem(Record $record, $path)
	{
		$content = "";
		$content .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		$content .= "<data>\n";
		$content .= static::tag("xml_id", $record->getXmlId(), 1);
		if ($record->getDependencies())
		{
			$content .= static::dependencyToXml($record->getDependencies());
		}
		if ($record->getReferences())
		{
			$content .= static::referenceToXml($record->getReferences());
		}
		$content .= static::valuesToXml($record->getFields(), "field", 1);
		foreach ($record->getRuntimes() as $name => $runtime)
		{
			if ($runtime->getFields() || $runtime->getReferences() || $runtime->getDependencies())
			{
				$content .= "\t<runtime>\n";
				$content .= static::tag("name", $name, 2);
				if ($runtime->getFields())
				{
					$content .= static::valuesToXml($runtime->getFields(), "field", 2);
				}
				if ($runtime->getReferences())
				{
					$content .= static::valuesToXml($runtime->getReferences(), "reference", 2);
				}
				if ($runtime->getDependencies())
				{
					$content .= static::valuesToXml($runtime->getDependencies(), "dependency", 2);
				}
				$content .= "\t</runtime>\n";
			}
		}

		$content .= "</data>";

		$filePath = $path . static::FILE_PREFIX . $record->getXmlId() . "." . static::FILE_EXT;
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
			$content .= static::tag("value", $dependency->getValue(), 2);
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
			$content .= static::tag("value", $dependency->getValue(), 2);
			$content .= "\t</reference>\n";
		}

		return $content;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Value[] $fieldsValues
	 * @param string $type
	 * @param int $level
	 *
	 * @return string
	 */
	protected static function valuesToXml(array $fieldsValues, $type = "field", $level = 0)
	{
		$content = "";
		foreach ($fieldsValues as $name => $fieldValue)
		{
			if ($fieldValue->isMultiple())
			{
				$descriptions = $fieldValue->getDescriptions();
				foreach ($fieldValue->getValues() as $i => $value)
				{
					$content .= str_repeat("\t", $level) . "<$type>\n";
					if ($fieldValue instanceof Link)
					{
						$content .= static::tag("module", $fieldValue->getTargetData()->getModule(), $level + 1);
						$content .= static::tag("entity", $fieldValue->getTargetData()->getEntityName(), $level + 1);
					}
					$content .= static::tag("name", $name . "[]", $level + 1);
					$content .= static::tag("value", $value, $level + 1);
					if (array_key_exists($i, $descriptions))
					{
						$content .= static::tag("description", $descriptions[$i], $level + 1);
					}
					$content .= str_repeat("\t", $level) . "</$type>\n";
				}
			}
			else
			{
				$content .= str_repeat("\t", $level) . "<$type>\n";
				if ($fieldValue instanceof Link)
				{
					$content .= static::tag("module", $fieldValue->getTargetData()->getModule(), $level + 1);
					$content .= static::tag("entity", $fieldValue->getTargetData()->getEntityName(), $level + 1);
				}
				$content .= static::tag("name", $name, $level + 1);
				$content .= static::tag("value", $fieldValue->getValue(), $level + 1);
				if ($fieldValue->isDescriptionSet())
				{
					$content .= static::tag("description", $fieldValue->getDescription(), $level + 1);
				}
				$content .= str_repeat("\t", $level) . "</$type>\n";
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
			$fields[$field["#"]["name"][0]["#"]] = trim($field["#"]["value"][0]["#"]);
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
			$runtimes = static::parseRuntimes($xmlArray["data"]["#"]["runtime"]);
			$record->setRuntimes($runtimes);
		}

		return $record;
	}

	/**
	 * @param array $runtimeNodes
	 *
	 * @return \Intervolga\Migrato\Data\Runtime[]
	 */
	protected static function parseRuntimes(array $runtimeNodes)
	{
		$result = array();
		foreach ($runtimeNodes as $runtimeNode)
		{
			$runtime = new Runtime();
			$runtimeName = $runtimeNode["#"]["name"][0]["#"];
			foreach ($runtimeNode["#"]["field"] as $fieldNode)
			{
				$name = $fieldNode["#"]["name"][0]["#"];
				$value = $fieldNode["#"]["value"][0]["#"];
				$description = $fieldNode["#"]["description"][0]["#"];
				$valueObject = new Value($value);
				$valueObject->setDescription($description);
				$runtime->setField($name, $valueObject);
			}
			foreach ($runtimeNode["#"]["reference"] as $referenceNode)
			{
				$module = $referenceNode["#"]["module"][0]["#"];
				$entity = $referenceNode["#"]["entity"][0]["#"];
				$name = $referenceNode["#"]["name"][0]["#"];
				$value = $referenceNode["#"]["value"][0]["#"];
				$description = $referenceNode["#"]["description"][0]["#"];
				$data = static::getDataClass($module, $entity);
				if ($data && strlen($value))
				{
					$link = new Link($data, $value);
					$link->setDescription($description);
					$runtime->setReference($name, $link);
				}
			}
			foreach ($runtimeNode["#"]["dependency"] as $dependencyNode)
			{
				$module = $dependencyNode["#"]["module"][0]["#"];
				$entity = $dependencyNode["#"]["entity"][0]["#"];
				$name = $dependencyNode["#"]["name"][0]["#"];
				$value = $dependencyNode["#"]["value"][0]["#"];
				$description = $dependencyNode["#"]["description"][0]["#"];
				$data = static::getDataClass($module, $entity);
				if ($data && strlen($value))
				{
					$link = new Link($data, $value);
					$link->setDescription($description);
					$runtime->setDependency($name, $link);
				}
			}
			$result[$runtimeName] = $runtime;
		}

		return $result;
	}

	/**
	 * @param string $module
	 * @param string $entity
	 *
	 * @return null|\Intervolga\Migrato\Data\BaseData
	 */
	protected static function getDataClass($module, $entity)
	{
		$name = "\\Intervolga\\Migrato\\Data\\Module\\" . $module . "\\" . $entity;
		if (class_exists($name))
		{
			/**
			 * @var \Intervolga\Migrato\Data\BaseData $name
			 */
			return $name::getInstance();
		}
		return null;
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