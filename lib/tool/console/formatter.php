<? namespace Intervolga\Migrato\Tool\Console;

use Bitrix\Main\Localization\Loc;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

Loc::loadMessages(__FILE__);

class Formatter extends OutputFormatter
{
	protected $isWindowsCharset = false;
	protected $isUnicodeCharset = false;
	protected $replaces = array();

	public function __construct($decorated = false, array $styles = array())
	{
		parent::__construct($decorated, $styles);
		$this->initStyles();
	}

	protected function initStyles()
	{
		$style = new OutputFormatterStyle('blue', 'white');
		$this->setStyle('info', $style);

		$style = new OutputFormatterStyle('green');
		$this->setStyle('ok', $style);

		$style = new OutputFormatterStyle('red');
		$this->setStyle('fail', $style);
	}

	public function setWindowsCharset($isWindowsCharset = true)
	{
		$this->isWindowsCharset = $isWindowsCharset;
	}

	public function setUnicodeCharset($isUnicodeCharset = true)
	{
		$this->isUnicodeCharset = $isUnicodeCharset;
	}

	public function format($message): ?string
	{
		$result = $this->replace($message);
		$result = parent::format($result);
		$result = $this->replaceBack($result);
		$result = $this->convert($result);

		return $result;
	}

	/**
	 * @param string $message
	 *
	 * @return string
	 */
	protected function replace($message)
	{
		$this->replaces = $this->getRussianReplaces($message);
		foreach ($this->replaces as $code => $word)
		{
			$message = str_replace($word, $code, $message);
		}

		return $message;
	}

	/**
	 * @param string $message
	 *
	 * @return string[]
	 */
	protected function getRussianReplaces($message)
	{
		$result = array();
		$matches = array();
		$regex = Loc::getMessage('INTERVOLGA_MIGRATO.RUSSIAN_REGEX');
		if (preg_match_all('/[' . $regex . ']+/' . BX_UTF_PCRE_MODIFIER, $message, $matches))
		{
			foreach ($matches[0] as $i => $string)
			{
				$result['#RUSSIAN_' . $i . '#'] = $string;
			}
		}

		return $this->sortByLength($result);
	}

	/**
	 * @param array $array
	 *
	 * @return array
	 */
	protected function sortByLength(array $array)
	{
		uasort(
			$array,
			function($a, $b)
			{
				return strlen($b) - strlen($a);
			}
		);
		return $array;
	}

	/**
	 * @param string $message
	 *
	 * @return string
	 */
	protected function replaceBack($message)
	{
		foreach ($this->replaces as $code => $word)
		{
			$message = str_replace($code, $word, $message);
		}

		return $message;
	}

	/**
	 * @param string $message
	 *
	 * @return string
	 */
	protected function convert($message)
	{
		if ($this->isWindowsCharset)
		{
			$message = iconv('utf-8', 'cp1251', $message);
		}
		if ($this->isUnicodeCharset)
		{
			$message = iconv('cp1251', 'utf-8', $message);
		}

		return $message;
	}
}