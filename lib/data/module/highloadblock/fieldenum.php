<? namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Intervolga\Migrato\Data\BaseUserFieldEnum;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;

class FieldEnum extends BaseUserFieldEnum
{
	public function getFilesSubdir()
	{
		return "/highloadblock/field/";
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$filter["USER_FIELD_ID"] = array();

		/** @var Record $record */
		foreach(Field::getInstance()->getList() as $record)
		{
			$fields = $record->getFieldsRaw();
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