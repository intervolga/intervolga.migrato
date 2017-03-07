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
		$content .= static::tagValue("xml_id", $record->getXmlId(), 1);
		$content .= static::valuesToXml($record->getDependencies(), "dependency", 1, true);
		$content .= static::valuesToXml($record->getReferences(), "reference", 1, true);
		$content .= static::valuesToXml($record->getFields(), "field", 1);
		foreach ($record->getRuntimes() as $name => $runtime)
		{
			if ($runtime->getFields() || $runtime->getReferences() || $runtime->getDependencies())
			{
				$content .= "\t<runtime>\n";
				$content .= static::tagValue("name", $name, 2);
				$content .= static::valuesToXml($runtime->getDependencies(), "dependency", 2);
				$content .= static::valuesToXml($runtime->getReferences(), "reference", 2);
				$content .= static::valuesToXml($runtime->getFields(), "field", 2);
				$content .= "\t</runtime>\n";
			}
		}
		$content .= "</data>";

		$filePath = $path . static::FILE_PREFIX . $record->getXmlId() . "." . static::FILE_EXT;
		File::deleteFile($filePath);
		File::putFileContents($filePath, $content);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Value[] $values
	 * @param string $tag
	 * @param int $level
	 * @param bool $isSkipLinkExtra
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected static function valuesToXml(array $values, $tag = "field", $level = 0, $isSkipLinkExtra = false)
	{
		$content = "";
		if ($values)
		{
			foreach ($values as $name => $fieldValue)
			{
				if ($fieldValue->isMultiple())
				{
					$descriptions = $fieldValue->getDescriptions();
					foreach ($fieldValue->getValues() as $i => $value)
					{
						$map = array(
							"name" => $name . "[]",
							"value" => $value,
						);
						if (!$isSkipLinkExtra)
						{
							if ($fieldValue instanceof Link)
							{
								$map["module"] = $fieldValue->getTargetData()->getModule();
								$map["entity"] = $fieldValue->getTargetData()->getEntityName();
							}
						}
						if (array_key_exists($i, $descriptions))
						{
							$map["description"] = $descriptions[$i];
						}
						$content .= static::tagMap($tag, $map, $level);
					}
				}
				else
				{
					$map = array(
						"name" => $name,
						"value" => $fieldValue->getValue(),
					);
					if ($fieldValue->isDescriptionSet())
					{
						$map["description"] = $fieldValue->getDescription();
					}
					if (!$isSkipLinkExtra)
					{
						if ($fieldValue instanceof Link)
						{
							$map["module"] = $fieldValue->getTargetData()->getModule();
							$map["entity"] = $fieldValue->getTargetData()->getEntityName();
						}
					}

					$content .= static::tagMap($tag, $map, $level);
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
		if ($xmlArray["data"]["@"]["deleted"])
		{
			$record->setDeleteMark(true);
		}
		else
		{
			if ($xmlArray["data"]["#"]["field"])
			{
				$fields = static::parseFields($xmlArray["data"]["#"]["field"]);
				$record->setFieldsValues($fields);
			}

			if ($xmlArray["data"]["#"]["dependency"])
			{
				$links = static::parseLinks($xmlArray["data"]["#"]["dependency"]);
				$record->setDependencies($links);
			}

			if ($xmlArray["data"]["#"]["reference"])
			{
				$links = static::parseLinks($xmlArray["data"]["#"]["reference"]);
				$record->setReferences($links);
			}

			if ($xmlArray["data"]["#"]["runtime"])
			{
				$runtimes = static::parseRuntimes($xmlArray["data"]["#"]["runtime"]);
				$record->setRuntimes($runtimes);
			}
		}

		return $record;
	}

	/**
	 * @param array $fieldsNodes
	 * @return \Intervolga\Migrato\Data\Value[]
	 */
	protected static function parseFields(array $fieldsNodes)
	{
		/**
		 * @var \Intervolga\Migrato\Data\Value[] $fields
		 */
		$fields = array();
		foreach ($fieldsNodes as $field)
		{
			$name = $field["#"]["name"][0]["#"];
			$isMultiple = false;
			if (substr_count($name, "[]") == 1)
			{
				$name = str_replace("[]", "", $name);
				$isMultiple = true;
			}
			if ($isMultiple)
			{
				if (!$fields[$name])
				{
					$fields[$name] = Value::createMultiple(array($field["#"]["value"][0]["#"]));
				}
				else
				{
					$fields[$name]->addValue($field["#"]["value"][0]["#"]);
				}
				$fields[$name]->addDescription($field["#"]["description"][0]["#"]);
			}
			else
			{
				$fields[$name] = new Value($field["#"]["value"][0]["#"]);
				$fields[$name]->setDescription($field["#"]["description"][0]["#"]);
			}
		}

		return $fields;
	}

	/**
	 * @param array $linksNodes
	 * @return Link[]
	 */
	protected static function parseLinks(array $linksNodes)
	{
		/**
		 * @var $links Link[]
		 */
		$links = array();
		foreach ($linksNodes as $linkNode)
		{
			$name = $linkNode["#"]["name"][0]["#"];
			$isMultiple = false;
			if (substr_count($name, "[]") == 1)
			{
				$name = str_replace("[]", "", $name);
				$isMultiple = true;
			}
			if ($isMultiple)
			{
				if (!$links[$name])
				{
					$links[$name] = Link::createMultiple(array($linkNode["#"]["value"][0]["#"]));
				}
				else
				{
					$links[$name]->addValue($linkNode["#"]["value"][0]["#"]);
				}
				$links[$name]->addDescription($linkNode["#"]["description"][0]["#"]);
			}
			else
			{
				$links[$name] = new Link(null, $linkNode["#"]["value"][0]["#"]);
				$links[$name]->setDescription($linkNode["#"]["description"][0]["#"]);
			}

			$data = static::getDataClass(
				$linkNode["#"]["module"][0]["#"],
				$linkNode["#"]["entity"][0]["#"]
			);
			if ($data)
			{
				$links[$name]->setTargetData($data);
			}
		}
		return $links;
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
			if ($runtimeNode["#"]["field"])
			{
				$fields = static::parseFields($runtimeNode["#"]["field"]);
				$runtime->setFields($fields);
			}
			if ($runtimeNode["#"]["reference"])
			{
				$links = static::parseLinks($runtimeNode["#"]["reference"]);
				$runtime->setReferences($links);
			}
			if ($runtimeNode["#"]["dependency"])
			{
				$links = static::parseLinks($runtimeNode["#"]["dependency"]);
				$runtime->setDependencies($links);
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
	 * @param array $map
	 * @param int $level
	 *
	 * @return string
	 */
	protected static function tagMap($tag, $map = array(), $level = 0)
	{
		if (!$map)
		{
			return static::tagValue($tag, "", $level);
		}
		else
		{
			$content = "";
			$content .= str_repeat("\t", $level) . "<$tag>\n";
			foreach ($map as $innerTag => $value)
			{
				$content .= static::tagValue($innerTag, $value, $level + 1);
			}
			$content .= str_repeat("\t", $level) . "</$tag>\n";
			return $content;
		}
	}

	/**
	 * @param string $tag
	 * @param string $value
	 * @param int $level
	 *
	 * @return string
	 */
	protected static function tagValue($tag, $value = "", $level = 0)
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