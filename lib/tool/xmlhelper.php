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
		$search = '/<' . $tagName . '[^>]*/';
		preg_match($search, $xml, $matches);
		$tagLine = $matches[0];
		foreach ($attributes as $name => $value)
		{
			// Если аттрибута нет в теге
			if(preg_match('/\s' . $name . '\b/', $tagLine) === false)
			{
				$tagLine = str_replace(">", " $name=\"$value\">", $tagLine);
			}
			else
			{
				$tagLine = preg_replace("/$name=\".*?\"/", "$name=\"$value\"", $tagLine);
			}
		}

		$xml = preg_replace($search, $tagLine, $xml);
		return $xml;
	}

	/**
	 * @return string
	 */
	public static function xmlHeader()
	{
		if (defined('BX_UTF'))
		{
			$encoding = 'utf-8';
		}
		else
		{
			$encoding = 'windows-1251';
		}
		return "<?xml version=\"1.0\" encoding=\"$encoding\"?>\n";
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
				if (is_array($value))
				{
					$content .= static::tagMap($innerTag, $value, $level + 1);
				}
				else
				{
					$content .= static::tagValue($innerTag, $value, $level + 1);
				}
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
			return str_repeat("\t", $level) . "<$tag>" . htmlspecialcharsbx($value) . "</$tag>\n";
		}
	}
}