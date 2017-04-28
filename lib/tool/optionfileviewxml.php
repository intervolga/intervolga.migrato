<?namespace Intervolga\Migrato\Tool;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;

class OptionFileViewXml
{
	const FILE_NAME = "option.xml";

	/**
	 * @param array $options
	 * @param string $directory
	 * @param string $module
	 */
	public static function write(array $options, $directory, $module)
	{
		$content = "";
		$content .= XmlHelper::xmlHeader();
		$content .= "<options>\n";
		foreach ($options as $option)
		{
			$map = array(
				'name' => $option['NAME'],
				'value' => $option['VALUE'],
				'site' => $option['SITE_ID'],
			);
			$content .= XmlHelper::tagMap('option', $map, 1);
		}
		$content .= "</options>";

		File::putFileContents($directory . $module . ".xml", $content);
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