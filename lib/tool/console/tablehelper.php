<?namespace Intervolga\Migrato\Tool\Console;

class TableHelper
{
	protected $table = array();
	const PAD_BEFORE = ' ';
	const PAD_AFTER = ' ';
	const MIN_LINES_SHOW_HEADER = 10;

	/**
	 * @return string
	 */
	protected function getSeparator()
	{
		$result = '+';
		foreach ($this->getTableColumnWidths() as $width)
		{
			$result .= str_repeat('-', strlen(static::PAD_BEFORE) + $width + strlen(static::PAD_AFTER));
			$result .= '+';
		}
		$result .= "\n";

		return $result;
	}

	/**
	 * @return int[]
	 */
	protected function getTableColumnWidths()
	{
		static $widths = array();
		if (!$widths)
		{
			foreach ($this->table as $row)
			{
				foreach ($row as $column => $content)
				{
					if (!array_key_exists($column, $widths))
					{
						$widths[$column] = strlen($content);
					}
					else
					{
						$widths[$column] = max(strlen($content), $widths[$column]);
					}
				}
			}
		}

		return $widths;
	}

	/**
	 * @param string[] $row
	 *
	 * @return string
	 */
	protected function getRow(array $row)
	{
		$result = '|';

		foreach ($this->getTableColumnWidths() as $column => $width)
		{
			$content = $row[$column];
			$pad = str_repeat(' ', $width - strlen($content));
			$result .= static::PAD_BEFORE . $content . $pad . static::PAD_AFTER;
			$result .= '|';
		}
		$result .= "\n";

		return $result;
	}

	/**
	 * @param string[] $header
	 *
	 * @return string
	 */
	protected function getHeader(array $header)
	{
		$result = '|';

		foreach ($this->getTableColumnWidths() as $column => $width)
		{
			$content = $header[$column];
			$pad = str_repeat(' ', $width - strlen($content));
			$result .= '<info>' . static::PAD_BEFORE . $content . $pad . static::PAD_AFTER . '</info>';
			$result .= '|';
		}
		$result .= "\n";

		return $result;
	}

	/**
	 * @param string[] $row
	 */
	public function addRow(array $row)
	{
		$this->table[] = array_merge(array('INDEX' => count($this->table)), $row);
	}

	/**
	 * @param string[] $header
	 */
	public function addHeader(array $header)
	{
		$this->table[] = array_merge(array('INDEX' => ''), $header);
	}

	/**
	 * @return string
	 */
	public function getOutput()
	{
		$output = '';
		$output .= $this->getSeparator();
		foreach ($this->table as $index => $row)
		{
			if ($index == 0)
			{
				$output .= $this->getHeader($row);
				$output .= $this->getSeparator();
			}
			else
			{
				$output .= $this->getRow($row);
			}
		}
		if (count($this->table) > static::MIN_LINES_SHOW_HEADER)
		{
			$output .= $this->getSeparator();
			$output .= $this->getHeader($this->table[0]);
		}
		$output .= $this->getSeparator();
		return $output;
	}
}