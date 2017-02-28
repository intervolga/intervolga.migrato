<?namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Intervolga\Migrato\Data\BaseData;

class UfXmlIdProvider extends BaseXmlIdProvider
{
	protected $dataName = "";

	/**
	 * @param BaseData $dataClass
	 * @param string $dataName
	 */
	public function __construct(BaseData $dataClass, $dataName = "")
	{
		parent::__construct($dataClass);
		if (!$dataName)
		{
			$dataName = strtoupper($dataClass->getModule() . "_" . $dataClass->getEntityName());
		}
		$this->dataName = $dataName;
	}

	/**
	 * @return array
	 */
	protected function makeField()
	{
		return array(
			"ENTITY_ID" => "MGR_" . $this->dataName,
			"FIELD_NAME" => "UF_MIGRATO_XML_ID",
			"USER_TYPE_ID" => "string",
			"XML_ID" => "MIGRATO_" . $this->dataName . ".UF_MIGRATO_XML_ID",
			"SORT" => "100",
			"IS_SEARCHABLE" => "N",
		);
	}
	
	public function isXmlIdFieldExists()
	{
		$fields = $this->makeField();
		$filter = array(
			"ENTITY_ID" => $fields["ENTITY_ID"],
			"XML_ID" => $fields["XML_ID"],
		);
		$getList = \CUserTypeEntity::getList(array(), $filter);

		return $getList->selectedRowsCount() > 0;
	}

	public function createXmlIdField()
	{
		$fields = $this->makeField();
		$userTypeEntity = new \CUserTypeEntity();
		if (!$userTypeEntity->add($fields))
		{
			throw new \Exception($fields["ENTITY_ID"] . " was not created");
		}

		return true;
	}

	public function setXmlId($id, $xmlId)
	{
		global $USER_FIELD_MANAGER;
		$fields = $this->makeField();

		return $USER_FIELD_MANAGER->update($fields["ENTITY_ID"], $id->getValue(), array($fields["FIELD_NAME"] => $xmlId));
	}

	public function getXmlId($id)
	{
		$xmlId = "";
		global $USER_FIELD_MANAGER;
		$fields = $this->makeField();
		$result = $USER_FIELD_MANAGER->getUserFields($fields["ENTITY_ID"], $id->getValue());
		if (array_key_exists($fields["FIELD_NAME"], $result))
		{
			$xmlId = $result[$fields["FIELD_NAME"]]["VALUE"];
		}

		return $xmlId;
	}
}