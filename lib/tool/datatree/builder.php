<? namespace Intervolga\Migrato\Tool\DataTree;

use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\Config;

class Builder
{
	/**
	 * @return \Intervolga\Migrato\Tool\DataTree\Tree
	 */
	public static function build()
	{
		$tree = new Tree();
		$configDataClasses = Config::getInstance()->getDataClasses();
		foreach ($configDataClasses as $configDataClass)
		{
			$node = new Node($configDataClass);
			$node->newFrom(Node::FROM_CONFIG);
			$tree->addNode($node);
		}

		static::buildFor($configDataClasses, $tree);

		return $tree;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData[] $dataClasses
	 * @param \Intervolga\Migrato\Tool\DataTree\Tree $tree
	 */
	protected static function buildFor(array $dataClasses, Tree $tree)
	{
		$newClasses = array();
		foreach ($dataClasses as $dataClass)
		{
			$newClasses = array_merge($newClasses, static::buildDependencies($dataClass, $tree));
			$newClasses = array_merge($newClasses, static::buildReferences($dataClass, $tree));
		}
		if ($newClasses)
		{
			static::buildFor($newClasses, $tree);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param \Intervolga\Migrato\Tool\DataTree\Tree $tree
	 *
	 * @return \Intervolga\Migrato\Data\BaseData[]
	 */
	protected static function buildDependencies(BaseData $dataClass, Tree $tree)
	{
		$newClasses = array();
		$dependencies = $dataClass->getDependencies();
		if ($dependencies)
		{
			foreach ($dependencies as $dependency)
			{
				$dependentDataClass = $dependency->getTargetData();
				$newNode = $tree->findNode($dependentDataClass);
				if (!$newNode)
				{
					$newNode = new Node($dependentDataClass);
					$tree->addNode($newNode);
					$newClasses[] = $dependentDataClass;
				}
				$parentNode = $tree->findNode($dataClass);
				$newNode->addParent($parentNode, Node::FROM_DEPENDENCY);
			}
		}

		return $newClasses;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @param \Intervolga\Migrato\Tool\DataTree\Tree $tree
	 *
	 * @return \Intervolga\Migrato\Data\BaseData[]
	 */
	private static function buildReferences(BaseData $dataClass, Tree $tree)
	{
		$newClasses = array();
		$references = $dataClass->getReferences();
		if ($references)
		{
			foreach ($references as $reference)
			{
				$dependentDataClass = $reference->getTargetData();
				$newNode = $tree->findNode($dependentDataClass);
				if (!$newNode)
				{
					$newNode = new Node($dependentDataClass);
					$tree->addNode($newNode);
					$newClasses[] = $dependentDataClass;
				}
				$parentNode = $tree->findNode($dataClass);
				$newNode->addParent($parentNode, Node::FROM_REFERENCE);
			}
		}

		return $newClasses;
	}
}