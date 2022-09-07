<? namespace Intervolga\Migrato\Tool\Console;

class DiffCounter
{
	private static $instance = false;
	public function __construct()
	{
		$this->clear();
	}
	public function __wakeup()
	{
	}
	public function __clone()
	{
	}
	public static function getInstance()
	{
		if (!self::$instance)
		{
			self::$instance = new self;
		}
		return self::$instance;
	}

	public $list;

	private function addToList(&$list, $add)
	{
		foreach ($add as $var => $value)
		{
			if (is_array($value))
			{
				$this->addToList($list[$var], $value);
			} else {
				if (is_bool($value))
				{
					$list[$var] = $value;
				} else {
					$list[$var] += $value;
				}
			}
		}
	}

	public function clear()
	{
		$this->list = [];
	}

	public function add($action, $id, $xmlId, $entityName, $module)
	{
		$add = [];
		$add['-'][$action] = 1;
		$add['v'][$action][$module][$entityName] = 1;
		$add['vv'][$action][$module][$entityName][$xmlId][$id?:''] = true;
		$this->addToList($this->list, $add);
	}

	public function addRecord($action, $record)
	{
		$this->add(
			$action,
			$record->getId(),
			$record->getXmlId(),
			$record->getData()->getEntityName(),
			$record->getData()->getModule()
		);
	}

	public function getResult()
	{
		return $this->list;
	}

	private function makeTableFromArray($top, $level, &$cols, $colValues, &$rows)
	{
		if (is_array($top))
		{
			foreach ($top as $colValue => $data)
			{
				if (count($cols) <= $level || $cols[$level] < mb_strlen($colValue))
				{
					$cols[$level] = mb_strlen($colValue);
				}
				$this->makeTableFromArray($data, $level+1, $cols, array_merge($colValues, [$colValue]), $rows);
			}
		} else {
			if (!is_bool($top)) {
				if (count($cols) <= $level || $cols[$level] < mb_strlen($top))
				{
					$cols[$level] = mb_strlen($top);
				}
				$rows[] = array_merge($colValues, [$top]);
			} else {
				$rows[] = $colValues;
			}
		}
	}

	public function makeTableAddLine(&$txt, $cols, $padding)
	{
		$txt .= '+';
		foreach ($cols as $colLength)
		{
			for ($i=-2*$padding; $i<$colLength; $i++)
			{
				$txt .= '-';
			}
			$txt .= '+';
		}
		$txt .= "\n";
	}

	public function makeTableAddRow(&$txt, $cols, $row, $padding)
	{
		$txt .= '|';
		foreach ($cols as $colId => $colLength)
		{
			for ($i=0; $i<$padding; $i++)
			{
				$txt .= ' ';
			}
			$txt .= $row[$colId];
			for ($i=0; $i<($colLength+$padding-mb_strlen($row[$colId])); $i++)
			{
				$txt .= ' ';
			}
			$txt .= '|';
		}
		$txt .= "\n";
	}

	public function makeTable($top, $header=false)
	{
		$cols = [];
		$rows = [];
		if ($header)
		{
			foreach ($header as $title)
			{
				$cols[] = mb_strlen($title);
			}
		}
		$this->makeTableFromArray($this->list[$top], 0, $cols, [], $rows);
		$padding = 2;
		$txt = "\n";
		if ($header)
		{
			$this->makeTableAddLine($txt, $cols, $padding);
			$this->makeTableAddRow($txt, $cols, $header, $padding);
		}
		$this->makeTableAddLine($txt, $cols, $padding);
		foreach ($rows as $row)
		{
			$this->makeTableAddRow($txt, $cols, $row, $padding);
		}
		$this->makeTableAddLine($txt, $cols, $padding);
		return $txt;
	}
}
