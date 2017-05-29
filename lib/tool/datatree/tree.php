<? namespace Intervolga\Migrato\Tool\DataTree;


use Intervolga\Migrato\Data\BaseData;

class Tree
{
	/**
	 * @var \Intervolga\Migrato\Tool\DataTree\Node[]
	 */
	protected $nodes = array();

	/**
	 * @param \Intervolga\Migrato\Tool\DataTree\Node $node
	 */
	public function addNode(Node $node)
	{
		$this->nodes[get_class($node->getDataClass())] = $node;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 *
	 * @return \Intervolga\Migrato\Tool\DataTree\Node|null
	 */
	public function findNode(BaseData $dataClass)
	{
		return $this->nodes[get_class($dataClass)];
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData[]
	 */
	public function getStrongNeed()
	{
		$result = array();
		foreach ($this->nodes as $node)
		{
			if ($node->isStrongNeed())
			{
				$result[] = $node->getDataClass();
			}
		}

		return $result;
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData[]
	 */
	public function getDataClasses()
	{
		$result = array();
		foreach ($this->nodes as $node)
		{
			$result[] = $node->getDataClass();
		}

		return $result;
	}
}