<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\LanguageTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\XmlIdProvider\TableXmlIdProvider;

class TypeLang extends BaseData
{
	protected function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new TableXmlIdProvider($this);
	}

	public function getFilesSubdir()
	{
		return "/type/";
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CIBlockType::GetList();
		while ($type = $getList->fetch())
		{
			foreach ($this->getLanguages() as $lang)
			{
				if ($typeLang = \CIBlockType::GetByIDLang($type["ID"], $lang))
				{
					$id = RecordId::createComplexId(
						array(
							"ID" => strval($typeLang["ID"]),
							"LANG" => strval($lang)
						)
					);
					$record = new Record($this);
					$record->setXmlId($this->getXmlIdProvider()->getXmlId($id));
					$record->setId($id);
					$record->setFields(array(
						"LID" => $typeLang["LID"],
						"NAME" => $typeLang["NAME"],
						"SECTION_NAME" => $typeLang["SECTION_NAME"],
						"ELEMENT_NAME" => $typeLang["ELEMENT_NAME"],
					));
					$record->addDependency(
						"IBLOCK_TYPE_ID",
						new Link(
							Type::getInstance(),
							Type::getInstance()->getXmlIdProvider()->getXmlId(RecordId::createStringId($typeLang["IBLOCK_TYPE_ID"]))
						)
					);
					$result[] = $record;
				}
			}
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

	public function getDependencies()
	{
		return array(
			"IBLOCK_TYPE_ID" => new Link(Type::getInstance()),
		);
	}
}