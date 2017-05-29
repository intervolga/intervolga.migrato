<? namespace Intervolga\Migrato\Tool\DataTree;

use Intervolga\Migrato\Data\BaseData;

class Node
{
	const FROM_CONFIG = 'C';
	const FROM_DEPENDENCY = 'D';
	const FROM_REFERENCE = 'R';

	protected $paths = array();
	protected $dataClass;

	public function __construct(BaseData $dataClass)
	{
		$this->dataClass = $dataClass;
	}

	public function addPath(array $path)
	{
		$this->paths[] = $path;
	}

	/**
	 * @param string $from
	 */
	public function addFrom($from)
	{
		foreach ($this->paths as $i => $path)
		{
			$this->paths[$i][] = $from;
		}
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData
	 */
	public function getDataClass()
	{
		return $this->dataClass;
	}

	/**
	 * @param \Intervolga\Migrato\Tool\DataTree\Node $parent
	 * @param string $from
	 */
	public function addParent(Node $parent, $from)
	{
		$paths = $parent->getPaths();
		foreach ($paths as $path)
		{
			$path[] = $from;
			$this->paths[] = $path;
		}
	}

	/**
	 * @return array
	 */
	public function getPaths()
	{
		return $this->paths;
	}

	/**
	 * @param string $from
	 */
	public function newFrom($from)
	{
		$this->paths[] = array($from);
	}

	public function isStrongNeed()
	{
		foreach ($this->paths as $path)
		{
			$count = array_count_values($path);
			$hasConfigs = !!$count[static::FROM_CONFIG];
			$hasDependencies = !!$count[static::FROM_DEPENDENCY];
			$hasReferences = !!$count[static::FROM_REFERENCE];
			if (($hasConfigs || $hasDependencies) && !$hasReferences)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function isRoot()
	{
		foreach ($this->paths as $path)
		{
			$isSingleFrom = count($path) == 1;
			$hasConfigFrom = in_array(static::FROM_CONFIG, $path);
			if ($isSingleFrom && $hasConfigFrom)
			{
				return true;
			}
		}

		return false;
	}
}