<?php
namespace Intervolga\Migrato\Tool;

use Bitrix\Main\Entity\Result;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ExceptionText
{
	/**
	 * @param $string
	 * @return string
	 */
	public static function getFromString($string)
	{
		$text = static::prepareText($string);
		if (!strlen($text))
		{
			$text = static::getUnknown();
		}

		return $text;
	}

	/**
	 * @return string
	 */
	public static function getFromApplication()
	{
		$text = '';
		global $APPLICATION;
		$exception = $APPLICATION->getException();
		if ($exception)
		{
			$text = $exception->getString();
			$text = static::prepareText($text);
		}
		if (!strlen($text))
		{
			$text = static::getUnknown();
		}

		return $text;
	}

	/**
	 * @return string
	 */
	public static function getUnknown()
	{
		return Loc::getMessage('INTERVOLGA_MIGRATO.UNKNOWN_EXCEPTION');
	}

	/**
	 * @param object $object
	 * @return string
	 */
	public static function getLastError($object)
	{
		$text = '';
		if ($object && is_object($object))
		{
			if ($object->LAST_ERROR)
			{
				$text = $object->LAST_ERROR;
				$text = static::prepareText($text);
			}
		}
		if (!strlen($text))
		{
			$text = static::getUnknown();
		}

		return $text;
	}

	/**
	 * @param \Bitrix\Main\Entity\Result $result
	 */
	public static function getFromResult(Result $result)
	{
		$lines = array();
		foreach ($result->getErrorMessages() as $error)
		{
			$lines[] = static::prepareText($error);
		}
		$text = implode('. ', $lines);
		if (!strlen($text))
		{
			$text = static::getUnknown();
		}

		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	protected static function prepareText($text)
	{
		$text = strip_tags($text);
		$text = trim($text);
		return $text;
	}
}