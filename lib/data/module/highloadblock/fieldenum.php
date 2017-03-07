<? namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Intervolga\Migrato\Data\BaseUserFieldEnum;
use Intervolga\Migrato\Data\Link;

class FieldEnum extends BaseUserFieldEnum
{

	public function getFilesSubdir()
	{
		return "/highloadblock/";
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$filter["USER_FIELD_ID"] = array();
		foreach(Field::getInstance()->getList() as $record)
		{
			$fields = $record->getFieldsStrings();
			if($fields["USER_TYPE_ID"] == "enumeration")
			{
				$filter["USER_FIELD_ID"][] = $record->getId()->getValue();
			}
		}
		return empty($filter["USER_FIELD_ID"]) ? array() : parent::getList($filter);
	}

	public function getDependencies()
	{
		return array(
			"USER_FIELD_ID" => new Link(Field::getInstance()),
		);
	}
}