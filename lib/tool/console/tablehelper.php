<?namespace Intervolga\Migrato\Tool\Console;

class TableHelper
{
	protected $table = array();
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
		static $widths = array();
		if (!$widths)
		{
			$widths = static::calculateColumnWidths();
		}

		return $widths;
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
				$widths[$column] = max(strlen($content), $widths[$column]);
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
		$result = static::VERTICAL_LINE;

		foreach ($this->getColumnWidths() as $column => $width)
		{
			$content = $row[$column];
			$pad = str_repeat(' ', $width - strlen($content));
			$result .= $prefix . static::PAD_BEFORE . $content . $pad . static::PAD_AFTER . $postfix;
			$result .= static::VERTICAL_LINE;
		}
		$result .= "\n";

		return $result;
	}

	/**
	 * @param string[] $row
	 *
	 * @return string
	 */
	protected function getHeaderOutput(array $row)
	{
		return static::getRowOutput($row, '<info>', '</info>');
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
		return $output;
	}
}