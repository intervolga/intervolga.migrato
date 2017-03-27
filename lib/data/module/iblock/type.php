<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Bitrix\Main\Localization\LanguageTable;
use Intervolga\Migrato\Data\Value;

class Type extends BaseData
{
	protected function __construct()
	{
		Loader::includeModule("iblock");
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CIBlockType::GetList();
		while ($type = $getList->fetch())
		{
			$record = new Record($this);
			$id = RecordId::createStringId($type["ID"]);
			$record->setXmlId($this->getXmlId($id));
			$record->setId($id);
			$record->addFieldsRaw(array(
				"ID" => $type["ID"],
				"SECTIONS" => $type["SECTIONS"],
				"EDIT_FILE_BEFORE" => $type["EDIT_FILE_BEFORE"],
				"EDIT_FILE_AFTER" => $type["EDIT_FILE_AFTER"],
				"IN_RSS" => $type["IN_RSS"],
				"SORT" => $type["SORT"],
			));
			$this->addLanguageStrings($record);
			$result[] = $record;
		}

		return $result;
	}

	/**
	 * @return array|string[]
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function getLanguages()
	{
		$result = array();
		$getList = LanguageTable::getList(array(
			"select" => array(
				"LID"
			)
		));
		while ($language = $getList->fetch())
		{
			$result[] = $language["LID"];
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected function addLanguageStrings(Record $record)
	{
		$strings = array();
		foreach ($this->getLanguages() as $language)
		{
			if ($typeLang = \CIBlockType::GetByIDLang($record->getId()->getValue(), $language))
			{
				foreach ($this->getLanguageFields() as $languageField)
				{
					$strings[$languageField][$language] = $typeLang[$languageField];
				}
			}
		}
		foreach ($strings as $field => $langFields)
		{
			$typeLangs = Value::treeToList($langFields, $field);
			$record->addFieldsRaw($typeLangs);
		}
	}

	/**
	 * @return string[]
	 */
	protected function getLanguageFields()
	{
		return array(
			"NAME",
			"SECTION_NAME",
			"ELEMENT_NAME"
		);
	}

	public function update(Record $record)
	{
		$typeObject = new \CIBlockType();
		$fields = $record->getFieldsRaw($this->getLanguageFields());
		$fields = $this->extractLanguageFields($fields);
		$isUpdated = $typeObject->update($record->getId()->getValue(), $fields);
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($typeObject->LAST_ERROR)));
		}
	}

	/**
	 * @param array $fields
	 *
	 * @return array
	 */
	protected function extractLanguageFields(array $fields)
	{
		$typeLang = array();
		foreach ($this->getLanguageFields() as $field)
		{
			foreach ($fields[$field] as $language => $langString)
			{
				$typeLang[$language][$field] = $langString;
			}
			unset($fields[$field]);
		}
		if ($typeLang)
		{
			$fields["LANG"] = $typeLang;
		}

		return $fields;
	}

	public function create(Record $record)
	{
		$fields = $record->getFieldsRaw($this->getLanguageFields());
		$fields = $this->extractLanguageFields($fields);
		$typeObject = new \CIBlockType();
		$typeId = $typeObject->add($fields);
		if ($typeId)
		{
			return $this->createId($typeId);
		}
		else
		{
			throw new \Exception(trim(strip_tags($typeObject->LAST_ERROR)));
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$typeObject = new \CIBlockType();
		if($id)
		{
			$this->deleteContentIBlockType($id->getValue());
			if (!$typeObject->delete($id->getValue()))
			{
				throw new \Exception("Unknown error");
			}
		}
		else
			throw new \Exception("Элемент с id " . $xmlId . " не существует");
	}

	private function deleteContentIBlockType($id)
	{
		$rsIblock = \CIBlock::GetList(array(), array("TYPE" => $id));
		while($arIblock = $rsIblock->Fetch())
		{
			if(\CModule::IncludeModule("catalog"))
			{
				\CCatalog::Delete($arIblock["ID"]);
			}

			$rsElement = \CIBlockElement::GetList(array(), array("IBLOCK_ID" => $arIblock["ID"]));
			while($arElement = $rsElement->Fetch())
			{
				\CIBlockElement::Delete($arElement["ID"]);
			}

			$rsSection = \CIBlockSection::GetList(array(), array("IBLOCK_ID" => $arIblock["ID"]));
			while($arSection = $rsSection->Fetch())
			{
				\CIBlockSection::Delete($arSection["ID"]);
			}
		}
	}

	public function setXmlId($id, $xmlId)
	{
		$rsType = \CIBlockType::GetByID($id->getValue());
		if($arType = $rsType->Fetch())
		{
			$arFields = array(
				"ID" => $xmlId,
				"SECTIONS" => $arType["SECTIONS"],
				"IN_RSS" => $arType["IN_RSS"]
			);
			$type = new \CIBlockType();
			$isUpdated = $type->Update($id, $arFields);
			if(!$isUpdated)
			{
				throw new \Exception("Ошибка обновления xmlId элемента iblocktype " . $id->getValue());
			}
		}
	}

	public function getXmlId($id)
	{
		return $id->getValue();
	}
}