<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Intervolga\Migrato\Data\BaseUserField;
use \Intervolga\Migrato\Data\Module\Highloadblock;
use \Intervolga\Migrato\Data\Module\Iblock;

class Field extends BaseUserField
{
	/**
	 * @param string $userFieldEntityId
	 *
	 * @return int
	 */
	public function isCurrentUserField($userFieldEntityId)
	{
		$isHl = Highloadblock\Field::getInstance()->isCurrentUserField($userFieldEntityId);
		$isIbl = Iblock\Field::getInstance()->isCurrentUserField($userFieldEntityId);
		return !$isIbl && !$isHl;
	}

	protected function userFieldToRecord(array $userField)
	{
		$record = parent::userFieldToRecord($userField);
		$record->addFieldsRaw(array("ENTITY_ID" => $userField["ENTITY_ID"]));
		return $record;
	}

	/**
	 * @return string
	 */
	public function getDependencyString()
	{
		return "";
	}

	/**
	 * @param $id
	 *
	 * @return string
	 */
	public function getDependencyNameKey($id)
	{
		return "";
	}
}