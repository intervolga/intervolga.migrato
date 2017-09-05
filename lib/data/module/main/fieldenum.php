<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseUserFieldEnum;
use Intervolga\Migrato\Data\Link;

class FieldEnum extends BaseUserFieldEnum
{
	public function getFilesSubdir()
	{
		return '/field/';
	}

	public function getList(array $filter = array())
	{
		$filter["USER_FIELD_ID"] = array();

		/** @var \Intervolga\Migrato\Data\Record $record */
		foreach (Field::getInstance()->getList() as $record)
		{
			$fields = $record->getFieldsRaw();
			if ($fields["USER_TYPE_ID"] == "enumeration")
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