<?php
namespace Intervolga\Migrato\Data;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\Module\Highloadblock\Field;
use Intervolga\Migrato\Data\Module\Highloadblock\HighloadBlock;
use Intervolga\Migrato\Data\Module\Iblock\Iblock;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

abstract class BaseUserField extends BaseData
{
	public function getEntityNameLoc()
	{
		return Loc::getMessage('INTERVOLGA_MIGRATO.USER_FIELD');
	}

	/**
	 * @return string[]
	 */
	public static function getLangFieldsNames()
	{
		return array(
			"EDIT_FORM_LABEL",
			"LIST_COLUMN_LABEL",
			"LIST_FILTER_LABEL",
			"ERROR_MESSAGE",
			"HELP_MESSAGE",
		);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CUserTypeEntity::getList(array(), $filter);
		while ($userField = $getList->fetch())
		{
			$userField = \CUserTypeEntity::getByID($userField["ID"]);
			if ($this->isCurrentUserField($userField["ENTITY_ID"]))
			{
				if ($record = $this->userFieldToRecord($userField))
				{
					$result[] = $record;
				}
			}
		}

		return $result;
	}

	/**
	 * @param string $userFieldEntityId
	 *
	 * @return bool
	 */
	abstract public function isCurrentUserField($userFieldEntityId);

	/**
	 * @param array $userField
	 *
	 * @return Record
	 */
	protected function userFieldToRecord(array $userField)
	{
		$record = new Record($this);
		$id = $this->createId($userField["ID"]);
		$record->setId($id);
		$record->setXmlId($this->getXmlId($id));
		$fields = array(
			"FIELD_NAME" => $userField["FIELD_NAME"],
			"XML_ID" => $userField["XML_ID"],
			"USER_TYPE_ID" => $userField["USER_TYPE_ID"],
			"SORT" => $userField["SORT"],
			"MULTIPLE" => $userField["MULTIPLE"],
			"MANDATORY" => $userField["MANDATORY"],
			"SHOW_FILTER" => $userField["SHOW_FILTER"],
			"SHOW_IN_LIST" => $userField["SHOW_IN_LIST"],
			"EDIT_IN_LIST" => $userField["EDIT_IN_LIST"],
			"IS_SEARCHABLE" => $userField["IS_SEARCHABLE"],
		);
		if ($userField["SETTINGS"])
		{
			$fields = array_merge($fields, $this->getSettingsFields($userField["SETTINGS"]));
		}
		$fields = array_merge($fields, $this->getLangFields($userField));
		$record->addFieldsRaw($fields);
		if ($userField["SETTINGS"])
		{
			foreach ($this->getSettingsLinks($userField["SETTINGS"]) as $name => $link)
			{
				$record->setReference($name, $link);
			}
		}

		return $record;
	}

	/**
	 * @param array $settings
	 * @param string $fullname
	 *
	 * @return array
	 */
	protected function getSettingsFields(array $settings, $fullname = "")
	{
		$fields = array();
		foreach ($settings as $name => $setting)
		{
			$name = $fullname ? $fullname . "." . $name : $name;
			if (!in_array($name, array_keys($this->getSettingsReferences())))
			{
				if (!is_array($setting))
				{
					$fields["SETTINGS." . $name] = $setting;
				}
				else
				{
					$fields = array_merge($fields, $this->addSerializedField("SETTINGS." . $name, $setting));
				}
			}
		}

		return $fields;
	}

	/**
	 * @param string $key
	 * @param array $value
	 *
	 * @return array
	 */
	protected function addSerializedField($key, $value)
	{
		return array($key . ":serialized" => serialize($value));
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	protected function isSerializedField($key)
	{
		$trimName = $this->getSerializeFieldName($key);
		return ($trimName != $key);
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	protected function getSerializeFieldName($key)
	{
		$re = '/:serialized$/';
		return preg_replace($re, "", $key);
	}

	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return array
	 */
	protected function unserializeField($key, $value)
	{
		$unserialize = array($this->getSerializeFieldName($key) => unserialize($value));
		return $unserialize;
	}

	/**
	 * @param array $fields список полей
	 * @param array $isDelete удалять ли составные настройки
	 *
	 * @return array список настроек
	 */
	protected function fieldsToArray(&$fields, $cutKey, $isDelete = false)
	{
		$settings = array();
		foreach ($fields as $key => $field)
		{
			if (strstr($key, $cutKey) !== false)
			{
				$workSetting = &$settings;
				$keys = explode(".", str_replace($cutKey . ".", "", $key));
				foreach ($keys as $pathKey)
				{
					if (end($keys) == $pathKey)
					{
						if ($this->isSerializedField($pathKey))
						{
							$workSetting = array_merge($workSetting, $this->unserializeField($pathKey, $field));
						}
						else
						{
							$workSetting[$pathKey] = $field;
						}
					}
					else
					{
						$workSetting[$pathKey] = $workSetting[$pathKey] ? $workSetting[$pathKey] : array();
						$workSetting = &$workSetting[$pathKey];
					}
				}

				if ($isDelete)
				{
					unset($fields[$key]);
				}
			}
		}
		return $settings;
	}

	/**
	 * @param array $userField
	 *
	 * @return array
	 */
	protected function getLangFields(array $userField)
	{
		$fields = array();
		foreach (static::getLangFieldsNames() as $langField)
		{
			foreach ($userField[$langField] as $lang => $message)
			{
				$fields[$langField . "." . $lang] = $message;
			}
		}

		return $fields;
	}

	/**
	 * @param array $settings
	 *
	 * @return Link[]
	 */
	protected function getSettingsLinks(array $settings)
	{
		$links = array();
		foreach ($settings as $name => $setting)
		{
			if ($name == "IBLOCK_ID")
			{
				$iblockIdObject = RecordId::createNumericId($setting);
				$xmlId = Iblock::getInstance()->getXmlId($iblockIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
			if ($name == "HLBLOCK_ID")
			{
				$hlBlockIdObject = RecordId::createNumericId($setting);
				$xmlId = HighloadBlock::getInstance()->getXmlId($hlBlockIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
			if ($name == "HLFIELD_ID")
			{
				$userFieldIdObject = RecordId::createNumericId($setting);
				$xmlId = Field::getInstance()->getXmlId($userFieldIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
		}

		return $links;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $links
	 *
	 * @return array настройки для привязки к сущности
	 */
	public function getSettingsLinksFields(array $links)
	{
		$settings = array();
		foreach ($links as $entity => $link)
		{
			$entity = str_replace("SETTINGS.", "", $entity);
			$xmlId = $link->getValue();
			if ($entityId = $this->getSettingsReference($entity)->getTargetData()->findRecord($xmlId))
			{
				$settings[$entity] = $entityId->getValue();
			}

			if ($entity == "IBLOCK_ID")
			{
				$settings["IBLOCK_TYPE_ID"] = \CIBlock::GetArrayByID($settings[$entity], "IBLOCK_TYPE_ID");
			}
		}
		return $settings;
	}

	public function getReferences()
	{
		$references = array();
		foreach ($this->getSettingsReferences() as $name => $link)
		{
			$references["SETTINGS." . $name] = $link;
		}

		return $references;
	}

	/**
	 * @return Link[]
	 */
	public function getSettingsReferences()
	{
		return array(
			"IBLOCK_ID" => new Link(Iblock::getInstance()),
			"HLBLOCK_ID" => new Link(HighloadBlock::getInstance()),
			"HLFIELD_ID" => new Link(Field::getInstance()),
		);
	}

	/**
	 * @param $key
	 *
	 * @return Link
	 */
	public function getSettingsReference($key)
	{
		$references = $this->getSettingsReferences();
		return $references[$key];
	}

	/**
	 * @return string
	 */
	abstract public function getDependencyString();

	/**
	 * @param $id
	 * @return string
	 */
	abstract public function getDependencyNameKey($id);

	public function update(Record $record)
	{
		if ($existId = $record->getId()->getValue())
		{
			$fieldObject = new \CUserTypeEntity();
			$existUserField = $fieldObject->getList(array(), array("ID" => $existId))->fetch();
			$xmlFields = $record->getFieldsRaw();

			$xmlFields["SETTINGS"] = $this->fieldsToArray($xmlFields, "SETTINGS", true);
			foreach ($this->getLangFieldsNames() as $lang)
			{
				$xmlFields[$lang] = $this->fieldsToArray($xmlFields, $lang, true);
			}

			$blockIdXml = $record->getDependency($this->getDependencyString());
			if (!$blockIdXml)
			{
				$xmlFields["SETTINGS"] = array_merge($xmlFields["SETTINGS"], $this->getSettingsLinksFields($record->getReferences()));
			}

			if ($xmlFields["SETTINGS"])
			{
				if ($existUserField["SETTINGS"])
				{
					$xmlFields["SETTINGS"] = array_merge($existUserField["SETTINGS"], $xmlFields["SETTINGS"]);
				}
			}
			$isReCreate = false;
			if ($xmlFields["MULTIPLE"] && ($xmlFields["MULTIPLE"] != $existUserField["MULTIPLE"]))
			{
				$isReCreate = true;
			}
			if ($xmlFields["USER_TYPE_ID"] && ($xmlFields["USER_TYPE_ID"] != $existUserField["USER_TYPE_ID"]))
			{
				$isReCreate = true;
			}

			if ($isReCreate)
			{
				$this->delete($record->getXmlId());
				$this->create($record);
			}
			else
			{
				$isUpdated = $fieldObject->Update($existId, $xmlFields);
				if (!$isUpdated)
				{
					throw new \Exception(ExceptionText::getFromApplication());
				}
			}
		}
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$fields["SETTINGS"] = $this->fieldsToArray($fields, "SETTINGS", true);
		foreach ($this->getLangFieldsNames() as $lang)
		{
			$fields[$lang] = $this->fieldsToArray($fields, $lang, true);
		}

		$fieldObject = new \CUserTypeEntity();
		$fieldId = $fieldObject->add($fields);
		if ($fieldId)
		{
			return $this->createId($fieldId);
		}
		else
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	protected function deleteInner($xmlId)
	{
		if ($id = $this->findRecord($xmlId))
		{
			$fieldObject = new \CUserTypeEntity();
			if (!$fieldObject->delete($id->getValue()))
			{
				throw new \Exception(ExceptionText::getFromApplication());
			}
		}
	}

	public function generateXmlId()
	{
		return '';
	}

	public function setXmlId($id, $xmlId)
	{
		// XML ID is autogenerated, cannot be modified
	}

	public function getXmlId($id)
	{
		$userField = \CUserTypeEntity::getById($id->getValue());
		$md5 = md5(serialize(array(
			$userField['ENTITY_ID'],
			$userField['FIELD_NAME'],
		)));

		return BaseXmlIdProvider::formatXmlId($md5);
	}
}