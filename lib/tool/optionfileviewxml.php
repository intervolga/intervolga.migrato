<?namespace Intervolga\Migrato\Tool;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;

class OptionFileViewXml
{
	const FILE_NAME = "option.xml";

	/**
	 * @param array $options
	 * @param string $path
	 */
	public static function writeToFileSystem(array $options, $path)
	{
		$content = "";
		$content .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		$content .= "<options>\n";
		foreach ($options as $name => $value)
		{
			$content .= "\t<field>\n";
			$content .= "\t\t<name>" . htmlspecialchars($name) . "</name>\n";
			if (strlen($value))
			{
				$content .= "\t\t<value>" . htmlspecialchars($value) . "</value>\n";
			}
			else
			{
				$content .= "\t\t<value/>\n";
			}

			$content .= "\t</field>\n";
		}
		$content .= "</options>";

		checkDirPath($path);
		File::putFileContents($path . static::FILE_NAME, $content);
	}

	/**
	 * @param string $path
	 * @return array
	 */
	public static function readFromFileSystem($path)
	{
		$options = array();
		foreach (static::getFiles($path) as $file)
		{
			$xmlParser = new \CDataXML();
			$xmlParser->Load($file->getPath());
			$xmlArray = $xmlParser->getArray();
			foreach ($xmlArray["options"]["#"]["field"] as $optionArray)
			{
				$options[$optionArray["#"]["name"][0]["#"]] = $optionArray["#"]["value"][0]["#"];
			}
		}

		return $options;
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
					if ($name == static::FILE_NAME)
					{
						$result[] = $fileSystemEntry;
					}
				}
			}
		}

		return $result;
	}
}