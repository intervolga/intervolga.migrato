<? namespace Intervolga\Migrato\Data;

use Intervolga\Migrato\Data\Module\Highloadblock\Field;
use Intervolga\Migrato\Data\Module\Highloadblock\HighloadBlock;
use Intervolga\Migrato\Data\Module\Iblock\Element;
use Intervolga\Migrato\Data\Module\Iblock\Iblock;
use Intervolga\Migrato\Data\Module\Iblock\Section;
use Intervolga\Migrato\Tool\XmlIdProvider\UfSelfXmlIdProvider;

abstract class BaseUserField extends BaseData
{
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

	public function __construct()
	{
		$this->xmlIdProvider = new UfSelfXmlIdProvider($this);
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
		$id = RecordId::createNumericId($userField["ID"]);
		$record->setId($id);
		$record->setXmlId($userField["XML_ID"]);
		$fields = array(
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
		$fields = array_merge($fields, $this->getSettingsFields($userField["SETTINGS"]));
		$fields = array_merge($fields, $this->getLangFields($userField));
		$record->setFields($fields);
		foreach ($this->getSettingsLinks($userField["SETTINGS"]) as $name => $link)
		{
			$record->addReference($name, $link);
		}

		return $record;
	}

	/**
	 * @param array $settings
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
				if(!is_array($setting))
				{
					$fields["SETTINGS." . $name] = $setting;
				}
				else
				{
					$fields = array_merge($fields, $this->getSettingsFields($setting, $name));
				}
			}
		}

		return $fields;
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
		foreach($fields as $key => $field)
		{
			if(strstr($key, $cutKey) !== false)
			{
				$workSetting = &$settings;
				$keys = explode(".", str_replace($cutKey . ".", "", $key));
				foreach($keys as $pathKey)
				{
					if(end($keys) == $pathKey)
					{
						$workSetting[$pathKey] = $field;
					}
					else
					{
						$workSetting[$pathKey] = $workSetting[$pathKey] ? $workSetting[$pathKey] : array();
						$workSetting = &$workSetting[$pathKey];
					}
				}

				if($isDelete)
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
				$xmlId = Iblock::getInstance()->getXmlIdProvider()->getXmlId($iblockIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
			if ($name == "HLBLOCK_ID")
			{
				$hlBlockIdObject = RecordId::createNumericId($setting);
				$xmlId = HighloadBlock::getInstance()->getXmlIdProvider()->getXmlId($hlBlockIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
			if ($name == "HLFIELD_ID")
			{
				$userFieldIdObject = RecordId::createNumericId($setting);
				$xmlId = Field::getInstance()->getXmlIdProvider()->getXmlId($userFieldIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
		}

		return $links;
	}

	public function getSettingsLinksFieldsIBLOCK(Link $iblock)
	{
		$settings = array();
		$iblockIdXml = $iblock->getValue();
		$iblockId = Iblock::getInstance()->findRecord($iblockIdXml)->getValue();

		$settings["IBLOCK_ID"] = $iblockId;
		$settings["IBLOCK_TYPE_ID"] = \CIBlock::GetArrayByID($iblockId, "IBLOCK_TYPE_ID");

		return $settings;
	}

	public function getSettingsLinksFieldsHLBLOCK(Link $iblock)
	{
		$iblock->getValue();
	}

	public function getSettingsLinksFieldsHLFIELD(Link $iblock)
	{
		$iblock->getValue();
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
	 * @param \Intervolga\Migrato\Data\Runtime $runtime
	 * @param \Intervolga\Migrato\Data\Record $field
	 * @param mixed $value
	 */
	public function fillRuntime(Runtime $runtime, Record $field, $value)
	{
		$runtimeValue = null;
		$runtimeLink = null;
		if ($field->getFieldValue("USER_TYPE_ID") == "iblock_element")
		{
			$runtimeLink = $this->getIblockElementLink($value);
		}
		elseif ($field->getFieldValue("USER_TYPE_ID") == "hlblock")
		{
			$runtimeLink = $this->getHlblockElementLink($field, $value);
		}
		elseif ($field->getFieldValue("USER_TYPE_ID") == "iblock_section")
		{
			$runtimeLink = $this->getIblockSectionLink($value);
		}
		elseif ($field->getFieldValue("USER_TYPE_ID") == "enumeration")
		{
			$runtimeLink = $this->getEnumerationLink($value);
		}
		elseif (in_array($field->getFieldValue("USER_TYPE_ID"), array("string", "double", "boolean", "integer", "datetime", "date", "string_formatted")))
		{
			$runtimeValue = new Value($value);
		}

		if ($runtimeValue)
		{
			$runtime->setField($field->getXmlId(), $runtimeValue);
		}
		if ($runtimeLink)
		{
			if ($field->getFieldValue("MANDATORY") == "Y")
			{
				$runtime->setDependency($field->getXmlId(), $runtimeLink);
			}
			else
			{
				$runtime->setReference($field->getXmlId(), $runtimeLink);
			}
		}
	}

	/**
	 * @param int $value
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 */
	protected function getIblockElementLink($value)
	{
		$inObject = RecordId::createNumericId($value);
		$elementXmlId = Element::getInstance()->getXmlIdProvider()->getXmlId($inObject);

		return new Link(Element::getInstance(), $elementXmlId);
	}

	/**
	 * @param int $value
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 */
	protected function getIblockSectionLink($value)
	{
		$inObject = RecordId::createNumericId($value);
		$sectionXmlId = Section::getInstance()->getXmlIdProvider()->getXmlId($inObject);

		return new Link(Section::getInstance(), $sectionXmlId);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $field
	 * @param int $value
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 * @throws \Exception
	 */
	protected function getHlblockElementLink(Record $field, $value)
	{
		$references = $field->getReferences();
		$hlbElementXmlId = "";
		if ($references["SETTINGS.HLBLOCK_ID"])
		{
			$hlblockXmlId = $references["SETTINGS.HLBLOCK_ID"]->getValue();
			$hlblockIdObject = HighloadBlock::getInstance()->findRecord($hlblockXmlId);
			if ($hlblockIdObject)
			{
				$hlblockId = $hlblockIdObject->getValue();
				$elementIdObject = RecordId::createComplexId(array(
					"ID" => intval($value),
					"HLBLOCK_ID" => intval($hlblockId),
				));
				$hlbElementXmlId = Module\Highloadblock\Element::getInstance()->getXmlIdProvider()->getXmlId($elementIdObject);
			}
		}

		return new Link(Module\Highloadblock\Element::getInstance(), $hlbElementXmlId);
	}

	/**
	 * @param int $value
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 */
	protected function getEnumerationLink($value)
	{
		// todo
	}
}