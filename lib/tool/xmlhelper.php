<?namespace Intervolga\Migrato\Tool;

class XmlHelper
{
	/**
	 * @param string $tagName
	 * @param string[] $attributes
	 * @param string $xml
	 *
	 * @return mixed
	 */
	public static function addAttrToTags($tagName, $attributes, $xml)
	{
		$search = '<' . $tagName . '>';
		$replace = '<' . $tagName;
		foreach ($attributes as $name => $value)
		{
			$replace .= " $name=\"$value\"";
		}
		$replace .= '>';

		$xml = str_replace($search, $replace, $xml);
		return $xml;
	}

	/**
	 * @return string
	 */
	public static function xmlHeader()
	{
		return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
	}
	/**
	 * @param string $tag
	 * @param array $map
	 * @param int $level
	 *
	 * @return string
	 */
	public static function tagMap($tag, $map = array(), $level = 0)
	{
		if (!$map)
		{
			return static::tagValue($tag, '', $level);
		}
		else
		{
			$content = '';
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
	public static function tagValue($tag, $value = '', $level = 0)
	{
		if (!strlen($value))
		{
			return str_repeat("\t", $level) . "<$tag/>\n";
		}
		else
		{
			return str_repeat("\t", $level) . "<$tag>" . htmlspecialchars($value) . "</$tag>\n";
		}
	}
}