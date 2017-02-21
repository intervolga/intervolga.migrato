<?namespace Intervolga\Migrato\Tool\XmlIdProvider;

class UfXmlIdProvider extends BaseXmlIdProvider
{
	/**
	 * @return array
	 */
	protected function makeField()
	{
		$module = strtoupper($this->dataClass->getModule());
		$entityName = strtoupper($this->dataClass->getEntityName());

		return $fields = array(
			"ENTITY_ID" => "MGR_{$module}_{$entityName}",
			"FIELD_NAME" => "UF_MIGRATO_XML_ID",
			"USER_TYPE_ID" => "string",
			"XML_ID" => "MIGRATO_{$module}_{$entityName}.UF_MIGRATO_XML_ID",
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
		$userTypeEntity->add($fields);

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