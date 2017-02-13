<? namespace Intervolga\Migrato\Tool;

class DataRecordsList
{
	protected $list = array();

	/**
	 * @param array|DataRecord[] $data
	 */
	public function addItems(array $data)
	{
		$this->list = array_merge($this->list, $data);
	}
}