<?namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Intervolga\Migrato\Data\BaseUserField;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

class Field extends BaseUserField
{
	public function getFilesSubdir()
	{
		return "/highloadblock/";
	}

	/**
	 * @param string $userFieldEntityId
	 * @return int
	 */
	protected function isCurrentUserField($userFieldEntityId)
	{
		return preg_match("/^HLBLOCK_[0-9]+$/", $userFieldEntityId);
	}

	public function getDependencies()
	{
		return array(
			"HLBLOCK_ID" => new Link(HighloadBlock::getInstance()),
		);
	}

	/**
	 * @param array $userField
	 * @return Record
	 */
	protected function userFieldToRecord(array $userField)
	{
		$hlBlockId = str_replace("HLBLOCK_", "", $userField["ENTITY_ID"]);
		$hlBlockRecordId = RecordId::createNumericId($hlBlockId);
		$hlBlockXmlId = HighloadBlock::getInstance()->getXmlIdProvider()->getXmlId($hlBlockRecordId);

		$record = new Record($this);
		$id = RecordId::createNumericId($userField["ID"]);
		$record->setId($id);
		$record->setXmlId($userField["XML_ID"]);
		$record->setFields(array(
			"FIELD_NAME" => $userField["FIELD_NAME"],
			"USER_TYPE_ID" => $userField["USER_TYPE_ID"],
			"SORT" => $userField["SORT"],
			"MULTIPLE" => $userField["MULTIPLE"],
			"MANDATORY" => $userField["MANDATORY"],
			"SHOW_FILTER" => $userField["SHOW_FILTER"],
			"SHOW_IN_LIST" => $userField["SHOW_IN_LIST"],
			"EDIT_IN_LIST" => $userField["EDIT_IN_LIST"],
			"IS_SEARCHABLE" => $userField["IS_SEARCHABLE"],
		));

		$dependency = clone $this->getDependency("HLBLOCK_ID");
		$dependency->setValue($hlBlockXmlId);
		$record->addDependency("HLBLOCK_ID", $dependency);

		return $record;
	}
}