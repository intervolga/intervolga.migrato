<?namespace Intervolga\Migrato\Schema;

class Field
{
	protected $xmlId = "";
	protected $fields = array();
	protected $settings = array();
	protected $id;
	protected $schema;

	/**
	 * @param \Intervolga\Migrato\Schema\BaseSchema $schema
	 */
	protected function __construct(BaseSchema $schema)
	{
		$this->schema = $schema;
	}

	/**
	 * @param \Intervolga\Migrato\Schema\BaseSchema $schema
	 * @param array $userField
	 *
	 * @return static
	 */
	public static function makeForUserField(BaseSchema $schema, array $userField)
	{
		$field = new static($schema);
		$field->xmlId = $userField["XML_ID"];
		$field->id = $userField["ID"];
		$field->fields = array(
			"FIELD_NAME" => $userField["FIELD_NAME"],
			"USER_TYPE_ID" => $userField["USER_TYPE_ID"],
			"SORT" => $userField["SORT"],
			"MULTIPLE" => $userField["MULTIPLE"],
			"MANDATORY" => $userField["MANDATORY"],
			"SHOW_FILTER" => $userField["SHOW_FILTER"],
			"SHOW_IN_LIST" => $userField["SHOW_IN_LIST"],
			"EDIT_IN_LIST" => $userField["EDIT_IN_LIST"],
			"IS_SEARCHABLE" => $userField["IS_SEARCHABLE"],
		);
		$field->settings = $userField["SETTINGS"];
		// EDIT_FORM_LABEL
		// LIST_COLUMN_LABEL
		// LIST_FILTER_LABEL
		return $field;
	}
}