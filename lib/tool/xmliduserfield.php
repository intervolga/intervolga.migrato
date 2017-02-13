<? namespace Intervolga\Migrato\Tool;

class XmlIdUserField
{
	/**
	 * @param string $module
	 * @param string $entityName
	 *
	 * @return array
	 */
	protected static function makeField($module, $entityName)
	{
		$module = strtoupper($module);
		$entityName = strtoupper($entityName);

		return $fields = array(
			"ENTITY_ID" => "MGR_{$module}_{$entityName}",
			"FIELD_NAME" => "UF_MIGRATO_XML_ID",
			"USER_TYPE_ID" => "string",
			"XML_ID" => "MIGRATO_{$module}_{$entityName}.UF_MIGRATO_XML_ID",
			"SORT" => "100",
			"IS_SEARCHABLE" => "N",
		);
	}

	/**
	 * @param string $module
	 * @param string $entityName
	 *
	 * @return bool
	 */
	public static function createField($module, $entityName)
	{
		$fields = static::makeField($module, $entityName);
		$userTypeEntity = new \CUserTypeEntity();

		return !!$userTypeEntity->add($fields);
	}

	/**
	 * @param string $module
	 * @param string $entityName
	 *
	 * @return bool
	 */
	public static function isFieldExists($module, $entityName)
	{
		$fields = static::makeField($module, $entityName);
		$filter = array(
			"ENTITY_ID" => $fields["ENTITY_ID"],
			"XML_ID" => $fields["XML_ID"],
		);
		$getList = \CUserTypeEntity::getList(array(), $filter);

		return $getList->selectedRowsCount() > 0;
	}

	/**
	 * @param string $module
	 * @param string $entityName
	 * @param string $id
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	public static function setXmlId($module, $entityName, $id, $xmlId)
	{
		global $USER_FIELD_MANAGER;
		$fields = static::makeField($module, $entityName);

		return $USER_FIELD_MANAGER->update($fields["ENTITY_ID"], $id, array($fields["FIELD_NAME"] => $xmlId));
	}

	/**
	 * @param string $module
	 * @param string $entityName
	 * @param int|string $id
	 *
	 * @return string
	 */
	public static function getXmlId($module, $entityName, $id)
	{
		$xmlId = "";
		global $USER_FIELD_MANAGER;
		$fields = static::makeField($module, $entityName);
		$result = $USER_FIELD_MANAGER->getUserFields($fields["ENTITY_ID"], $id);
		if (array_key_exists($fields["FIELD_NAME"], $result))
		{
			$xmlId = $result[$fields["FIELD_NAME"]]["VALUE"];
		}

		return $xmlId;
	}
}