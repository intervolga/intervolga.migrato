<? namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Runtime;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

class Section extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Iblock\\SectionTable");
	}

	public function getFilesSubdir()
	{
		return "/type/iblock/";
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = SectionTable::getList();
		while($section = $getList->fetch())
		{
			$record = new Record($this);
			$record->setXmlId($section["XML_ID"]);
			$record->setId(RecordId::createNumericId($section["ID"]));
			$record->setFields(array(
				"NAME"              => $section["NAME"],
				"CODE"              => $section["CODE"],
				"ACTIVE"            => $section["ACTIVE"],
				"SORT"              => $section["SORT"],
				"DESCRIPTION"       => $section["DESCRIPTION"],
				"DESCRIPTION_TYPE"  => $section["DESCRIPTION_TYPE"],
				"SECTION_PROPERTY"  => $section["SECTION_PROPERTY"],
			));

			$dependency = clone $this->getDependency("IBLOCK_ID");
			$dependency->setValue(Iblock::getInstance()->getXmlIdProvider()->getXmlId(RecordId::createNumericId($section["IBLOCK_ID"])));
			$record->addDependency("IBLOCK_ID", $dependency);

			$link = new Link(self::getInstance(), $this->getXmlIdProvider()->getXmlId(RecordId::createNumericId($section["IBLOCK_SECTION_ID"])));
			$record->setReferences(array("IBLOCK_SECTION_ID" => $link));

			$rsSection = \CIBlockSection::GetList(array(), array("IBLOCK_ID" => $section["IBLOCK_ID"], "ID" => $section["ID"]), false, array("UF_*"));
			if($arSection = $rsSection->Fetch())
			{
				$this->addRuntime($record, $arSection, $section["IBLOCK_ID"]);
			}


			$result[] = $record;
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param array $section
	 * @param int $iblockId
	 */
	protected function addRuntime(Record $record, array $section, $iblockId)
	{
		$runtime = clone $this->getRuntime("FIELD");

		$fields = Field::getInstance()->getList(array("IBLOCK_ID" => $iblockId));
		foreach ($fields as $field)
		{
			/**
			 * @var Record $field
			 */
			$fieldName = $field->getFieldValue("FIELD_NAME");
			Field::getInstance()->fillRuntime($runtime, $field, $section[$fieldName]);
		}

		if ($runtime->getFields() || $runtime->getDependencies() || $runtime->getReferences())
		{
			$record->setRuntime("FIELD", $runtime);
		}
	}

	public function getDependencies()
	{
		return array(
			"IBLOCK_ID" => new Link(Iblock::getInstance()),
		);
	}

	public function getReferences()
	{
		return array(
			"IBLOCK_SECTION_ID" => new Link(self::getInstance()),
		);
	}

	public function getRuntimes()
	{
		return array(
			"FIELD" => new Runtime(Field::getInstance()),
		);
	}

	public function getIBlock(Record $record)
	{
		$iblockId = null;
		if($iblockId = $record->getDependency("IBLOCK_ID")->getId())
		{
			$iblockId = $iblockId->getValue();
		}
		elseif($id = $record->getId())
		{
			$rsSection = \CIBlockSection::GetByID($id->getValue());
			if($arSection = $rsSection->Fetch())
				$iblockId = intval($arSection["IBLOCK_ID"]);
		}
		if(!$iblockId)
		{
			throw new \Exception("Not found IBlock for the section " . $record->getXmlId());
		}
		return $iblockId;
	}

	public function getRuntimesFields($fields)
	{
		$result = array();

		/**
		 * @var \Intervolga\Migrato\Data\Link  $value
		 */
		foreach($fields as $key => $value)
		{
			$fieldId = Field::getInstance()->findRecord($key)->getValue();
			$field = \CUserTypeEntity::GetByID($fieldId);

			$result[$field["FIELD_NAME"]] = $value->getValue();
		}
		return $result;
	}

	public function getRuntimesLinks($links)
	{
		$result = array();

		/**
		 * @var \Intervolga\Migrato\Data\Link  $link
		 */
		foreach($links as $key => $link)
		{
			$fieldId = Field::getInstance()->findRecord($key)->getValue();
			$field = \CUserTypeEntity::GetByID($fieldId);
			if(!$link->isMultiple())
			{
                $id = $link->getId() ? $link->getId()->getValue() : null;
			    if(is_array($id))
                {
                    $id = $id["ID"];
                }
                $result[$field["FIELD_NAME"]] = $id;
			}
			else
			{
				$result[$field["FIELD_NAME"]] = $link->getIds();
			}
		}
		return $result;
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsStrings();
		$fields["IBLOCK_ID"] = $this->getIBlock($record);

		$reference = $record->getReference("IBLOCK_SECTION_ID")->getId();
		$fields["IBLOCK_SECTION_ID"] = $reference ? $reference->getValue() : null;

		$runtimes = $record->getRuntime("FIELD");

		$fields = array_merge($fields, $this->getRuntimesFields($runtimes->getFields()));
		$fields = array_merge($fields, $this->getRuntimesLinks($runtimes->getDependencies()));
		$fields = array_merge($fields, $this->getRuntimesLinks($runtimes->getReferences()));

		$sectionObject = new \CIBlockSection();
		$isUpdated = $sectionObject->update($record->getId()->getValue(), $fields);
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($sectionObject->LAST_ERROR)));
		}
	}

	public function create(Record $record)
	{
		$fields = $record->getFieldsStrings();
		$fields["IBLOCK_ID"] = $this->getIBlock($record);

		$fields = array_merge($fields, $this->getRuntimesFields($record->getRuntime("FIELD")->getFields()));
		$fields = array_merge($fields, $this->getRuntimesLinks($record->getRuntime("FIELD")->getDependencies()));

		$sectionObject = new \CIBlockSection();
		$sectionId = $sectionObject->add($fields);
		if ($sectionId)
		{
			return $this->createId($sectionId);
		}
		else
		{
			throw new \Exception(trim(strip_tags($sectionObject->LAST_ERROR)));
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if(!\CIBlockSection::Delete($id))
		{
			throw new \Exception("Unknown error");
		}
	}
}