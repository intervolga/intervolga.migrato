<?namespace Intervolga\Migrato\Tool\Console;

class TableHelper
{
	protected $table = array();
	protected $widths = array();
	const PAD_BEFORE = ' ';
	const PAD_AFTER = ' ';
	const CROSSING = '+';
	const HORIZONTAL_LINE = '-';
	const VERTICAL_LINE = '|';
	const MIN_LINES_SHOW_HEADER = 10;

	/**
	 * @return string
	 */
	protected function getSeparatorOutput()
	{
		$result = static::CROSSING;
		foreach ($this->getColumnWidths() as $width)
		{
			$result .= str_repeat(static::HORIZONTAL_LINE, strlen(static::PAD_BEFORE) + $width + strlen(static::PAD_AFTER));
			$result .= static::CROSSING;
		}
		$result .= "\n";

		return $result;
	}

	/**
	 * @return int[]
	 */
	protected function getColumnWidths()
	{
		if (!$this->widths)
		{
			$this->widths = $this->calculateColumnWidths();
		}

		return $this->widths;
	}

	/**
	 * @return int[]
	 */
	protected function calculateColumnWidths()
	{
		$widths = array();
		foreach ($this->table as $row)
		{
			foreach ($row as $column => $content)
			{
				$contentLines = explode(PHP_EOL, $content);
				foreach ($contentLines as $contentLine)
				{
					$widths[$column] = max(strlen($contentLine), $widths[$column]);
				}
			}
		}

		return $widths;
	}

	/**
	 * @return string
	 */
	protected function getRowsOutput()
	{
		$output = '';
		foreach ($this->table as $index => $row)
		{
			if ($index == 0)
			{
				continue;
			}
			$output .= $this->getRowOutput($row);
		}

		return $output;
	}

	/**
	 * @param string[] $row
	 * @param string $prefix
	 * @param string $postfix
	 *
	 * @return string
	 */
	protected function getRowOutput(array $row, $prefix = '', $postfix = '')
	{
		$result = '';
		$maxContentLines = $this->getMaxContentLines($row);

		for ($contentLine = 0; $contentLine < $maxContentLines; $contentLine++)
		{
			$result .= static::VERTICAL_LINE;
			foreach ($this->getColumnWidths() as $column => $width)
			{
				$content = $row[$column];
				$contentLines = explode(PHP_EOL, $content);
				$pad = str_repeat(' ', $width - strlen($contentLines[$contentLine]));
				$result .= $prefix . static::PAD_BEFORE . $contentLines[$contentLine] . $pad . static::PAD_AFTER . $postfix;
				$result .= static::VERTICAL_LINE;
			}
			$result .= PHP_EOL;
		}

		return $result;
	}

	/**
	 * @param array $row
	 *
	 * @return int
	 */
	protected function getMaxContentLines(array $row)
	{
		$result = 0;
		foreach ($this->getColumnWidths() as $column => $width)
		{
			$content = $row[$column];
			$result = max($result, count(explode(PHP_EOL, $content)));
		}

		return $result;
	}

	/**
	 * @param string[] $row
	 *
	 * @return string
	 */
	protected function getHeaderOutput(array $row)
	{
		return $this->getRowOutput($row, '<info>', '</info>');
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
		if (count($this->table) > 1)
		{
			$output .= $this->getSeparatorOutput();
			$output .= $this->getHeaderOutput($this->table[0]);
			$output .= $this->getSeparatorOutput();
			$output .= $this->getRowsOutput();
			if (count($this->table) > static::MIN_LINES_SHOW_HEADER)
			{
				$output .= $this->getSeparatorOutput();
				$output .= $this->getHeaderOutput($this->table[0]);
			}
			$output .= $this->getSeparatorOutput();
		}
		return $output;
	}
}