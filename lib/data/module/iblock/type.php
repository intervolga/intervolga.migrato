<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\TypeTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Main\Language;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Bitrix\Main\Localization\LanguageTable;
use Intervolga\Migrato\Data\Value;
use \Bitrix\Iblock\TypeLanguageTable;

class Type extends BaseData
{
	protected function __construct()
	{
		Loader::includeModule("iblock");
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = TypeTable::getList();
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

	public function getDependencies()
	{
		return array(
			'LANGUAGE' => new Link(Language::getInstance()),
		);
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
		$langXmlIds = array();
		$strings = array();
		foreach ($this->getLanguages() as $language)
		{
			if ($typeLang = \CIBlockType::GetByIDLang($record->getId()->getValue(), $language))
			{
				foreach ($this->getLanguageFields() as $languageField)
				{
					if ($languageField)
					{
						$langXmlIds[$language] = Language::getInstance()->getXmlId(Language::getInstance()->createId($language));
					}
					$strings[$languageField][$language] = $typeLang[$languageField];
				}
			}
		}
		foreach ($strings as $field => $langFields)
		{
			$typeLangs = Value::treeToList($langFields, $field);
			$record->addFieldsRaw($typeLangs);
		}
		if ($langXmlIds)
		{
			$dependency = clone $this->getDependency('LANGUAGE');
			$dependency->setValues($langXmlIds);
			$record->setDependency('LANGUAGE', $dependency);
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
		$fields = $record->getFieldsRaw($this->getLanguageFields());
		$fields = $this->extractLanguageFields($fields);
		$result = TypeTable::update($record->getId()->getValue(), $fields);
		if (!$result->isSuccess())
		{
			throw new \Exception(trim(strip_tags(implode(', ', $result->getErrorMessages()))));
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

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw($this->getLanguageFields());
		$fields = $this->extractLanguageFields($fields);
		$result = TypeTable::add($fields);
		if ($result->isSuccess())
		{
			return $this->createId($result->getId());
		}
		else
		{
			throw new \Exception(trim(strip_tags(implode(', ', $result->getErrorMessages()))));
		}
	}

	protected function deleteInner($xmlId)
	{
		if($id = $this->findRecord($xmlId))
		{
			$this->deleteContentIBlockType($id->getValue());
			$result = TypeTable::delete($id->getValue());
			if (!$result->isSuccess())
			{
				throw new \Exception(implode(', ', $result->getErrorMessages()));
			}
		}
	}

	private function deleteContentIBlockType($id)
	{
		$rsIblock = IblockTable::getList(array(
			'filter' => array("TYPE" => $id)
		));
		while($arIblock = $rsIblock->fetch())
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
		$rsType = TypeTable::getById($id->getValue());
		if($arType = $rsType->fetch())
		{
			$arFields = array("ID" => $xmlId);
			$result = TypeTable::update($id->getValue(), $arFields);
			if($result->isSuccess())
			{
				foreach($this->getLanguages() as $language)
				{
					$langId = array('IBLOCK_TYPE_ID' => $id->getValue(), 'LANGUAGE_ID' => $language);
					TypeLanguageTable::update($langId, array('IBLOCK_TYPE_ID' => $xmlId));
				}
			}
			else
			{
				throw new \Exception(implode(', ', $result->getErrorMessages()));
			}
		}
	}

	public function getXmlId($id)
	{
		return $id->getValue();
	}

	public function findRecord($xmlId)
	{
		$parameters = array(
			'filter' => array(
				'=ID' => $xmlId,
			),
			'limit' => 1,
		);
		if (TypeTable::getList($parameters)->fetch())
		{
			return $this->createId($xmlId);
		}
		else
		{
			return null;
		}
	}

	public function createId($id)
	{
		return RecordId::createStringId($id);
	}
}