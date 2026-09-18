<?php namespace Intervolga\Migrato\Tool\Web;

/**
 * Преобразует вывод symfony/console (ANSI-последовательности) в HTML
 */
class HtmlConverter
{
	/**
	 * Цвета консоли для HTML-представления
	 */
	const FOREGROUND_COLORS = array(
		30 => '#2c3e50',
		31 => '#c0392b',
		32 => '#27ae60',
		33 => '#b58900',
		34 => '#2980b9',
		35 => '#8e44ad',
		36 => '#16a085',
		37 => '#ecf0f1',
		90 => '#7f8c8d',
		91 => '#e74c3c',
		92 => '#2ecc71',
		93 => '#f1c40f',
		94 => '#3498db',
		95 => '#9b59b6',
		96 => '#1abc9c',
		97 => '#ffffff',
	);

	const BACKGROUND_COLORS = array(
		40 => '#2c3e50',
		41 => '#c0392b',
		42 => '#27ae60',
		43 => '#b58900',
		44 => '#2980b9',
		45 => '#8e44ad',
		46 => '#16a085',
		47 => '#ecf0f1',
		100 => '#7f8c8d',
		101 => '#e74c3c',
		102 => '#2ecc71',
		103 => '#f1c40f',
		104 => '#3498db',
		105 => '#9b59b6',
		106 => '#1abc9c',
		107 => '#ffffff',
	);

	protected $foreground = '';
	protected $background = '';
	protected $bold = false;
	protected $underline = false;

	/**
	 * Преобразует очередную порцию вывода в HTML.
	 * Состояние стилей сохраняется между вызовами, поэтому один и тот же
	 * объект нужно использовать на всём протяжении вывода команды.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function convert($text)
	{
		$result = '';
		$parts = preg_split(
			'/\033\[([0-9;]*)m/',
			$text,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		foreach ($parts as $index => $part)
		{
			if ($index % 2)
			{
				$this->applyCodes($part);
			}
			elseif ($part !== '')
			{
				$result .= $this->wrap($part);
			}
		}

		return $result;
	}

	/**
	 * @param string $codes
	 */
	protected function applyCodes($codes)
	{
		if ($codes === '')
		{
			$codes = '0';
		}
		foreach (explode(';', $codes) as $code)
		{
			$code = (int)$code;
			if ($code === 0)
			{
				$this->reset();
			}
			elseif ($code === 1)
			{
				$this->bold = true;
			}
			elseif ($code === 4)
			{
				$this->underline = true;
			}
			elseif ($code === 22)
			{
				$this->bold = false;
			}
			elseif ($code === 24)
			{
				$this->underline = false;
			}
			elseif ($code === 39)
			{
				$this->foreground = '';
			}
			elseif ($code === 49)
			{
				$this->background = '';
			}
			elseif (array_key_exists($code, static::FOREGROUND_COLORS))
			{
				$this->foreground = static::FOREGROUND_COLORS[$code];
			}
			elseif (array_key_exists($code, static::BACKGROUND_COLORS))
			{
				$this->background = static::BACKGROUND_COLORS[$code];
			}
		}
	}

	protected function reset()
	{
		$this->foreground = '';
		$this->background = '';
		$this->bold = false;
		$this->underline = false;
	}

	/**
	 * @param string $text
	 *
	 * @return string
	 */
	protected function wrap($text)
	{
		$text = htmlspecialcharsbx($text);
		$styles = array();
		if ($this->foreground)
		{
			$styles[] = 'color:' . $this->foreground;
		}
		if ($this->background)
		{
			$styles[] = 'background-color:' . $this->background;
		}
		if ($this->bold)
		{
			$styles[] = 'font-weight:bold';
		}
		if ($this->underline)
		{
			$styles[] = 'text-decoration:underline';
		}
		if (!$styles)
		{
			return $text;
		}

		return '<span style="' . implode(';', $styles) . '">' . $text . '</span>';
	}
}
