<?namespace Intervolga\Migrato\Tool;

use Intervolga\Migrato\Base\Data;

class DataRecord
{
	protected $xmlId = "";
	protected $localDbId = "";
	protected $fields = array();
	protected $dependencies = array();
	protected $data = null;

	/**
	 * @param string $xmlId
	 */
	public function setXmlId($xmlId)
	{
		$this->xmlId = $xmlId;
	}

	/**
	 * @return string
	 */
	public function getXmlId()
	{
		return $this->xmlId;
	}

	/**
	 * @param array $fields
	 */
	public function setFields(array $fields)
	{
		$this->fields = $fields;
	}

	/**
	 * @return array
	 */
	public function getFields()
	{
		return $this->fields;
	}

	/**
	 * @param array $dependencies
	 */
	public function setDependencies(array $dependencies)
	{
		$this->dependencies = $dependencies;
	}

	/**
	 * @return array
	 */
	public function getDependencies()
	{
		return $this->dependencies;
	}

	/**
	 * @param int|string $id
	 */
	public function setLocalDbId($id)
	{
		$this->localDbId = $id;
	}

	/**
	 * @return int|string
	 */
	public function getLocalDbId()
	{
		return $this->localDbId;
	}

	/**
	 * @param Data $dataObject
	 */
	public function setData(Data $dataObject)
	{
		$this->data = $dataObject;
	}

	/**
	 * @return Data
	 */
	public function getData()
	{
		return $this->data;
	}
}