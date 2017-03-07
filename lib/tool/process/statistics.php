<?namespace Intervolga\Migrato\Tool\Process;

use Intervolga\Migrato\Data\BaseData;

class Statistics
{
	protected $array;

	public function reset()
	{
		$this->array = array();
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $data
	 * @param string $operation
	 * @param string $id
	 * @param boolean $status
	 */
	public function add(BaseData $data, $operation, $id, $status)
	{
		$this->array[$data->getModule()][$data->getEntityName()][$operation][$status][] = $id;
	}

	/**
	 * @return array
	 */
	public function get()
	{
		$result = array();
		foreach ($this->array as $module => $entities)
		{
			foreach ($entities as $entity => $operations)
			{
				foreach ($operations as $operation => $statuses)
				{
					foreach ($statuses as $status => $ids)
					{
						$result[] = array(
							"module" => $module,
							"entity" => $entity,
							"operation" => $operation,
							"status" => $status,
							"count" => count($ids),
						);
					}
				}
			}
		}
		return $result;
	}
}