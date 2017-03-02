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

			$result[] = $record;
		}

		return $result;
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

	public function getIBlock(Record $record)
	{
		$iblockId = null;
		if($iblockIdXml = $record->getDependency("IBLOCK_ID"))
		{
			$iblockId = Iblock::getInstance()->findRecord($iblockIdXml->getValue())->getValue();
		}
		else
		{
			$rsSection = \CIBlockSection::GetByID($record->getId()->getValue());
			if($arSection = $rsSection->Fetch())
				$iblockId = intval($arSection["IBLOCK_ID"]);
		}
		if(!$iblockId)
		{
			throw new \Exception("Not found IBlock for the section " . $record->getId()->getValue());
		}
		return $iblockId;
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsStrings();
		$fields["IBLOCK_ID"] = $this->getIBlock($record);

		$reference = $record->getReference("IBLOCK_SECTION_ID");
		$reference = $reference->getValue() ? self::findRecord($reference->getValue())->getValue() : null;
		$fields["IBLOCK_SECTION_ID"] = $reference;

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

		$sectionObject = new \CIBlockSection();
		$sectionId = $sectionObject->add($fields);
		if ($sectionId)
		{
			$id = RecordId::createNumericId($sectionId);
			$this->getXmlIdProvider()->setXmlId($id, $record->getXmlId());

			return $id;
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