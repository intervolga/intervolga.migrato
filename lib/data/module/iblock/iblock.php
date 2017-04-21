<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\InheritedProperty\IblockTemplates;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

class Iblock extends BaseData
{
	protected function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Iblock\\IblockTable");
	}

	public function getFilesSubdir()
	{
		return "/type/";
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$order = array("ID" => "ASC");
		$iblockFilter = array();
		if ($filter)
		{
			$iblockFilter["XML_ID"] = $filter;
		}
		$getList = \CIBlock::GetList($order, $iblockFilter);
		while ($iblock = $getList->fetch())
		{
			$record = new Record($this);
			$record->setXmlId($iblock["XML_ID"]);
			$record->setId(RecordId::createNumericId($iblock["ID"]));
			$record->addFieldsRaw(array(
				"SITE_ID" => $iblock["LID"],
				"CODE" => $iblock["CODE"],
				"NAME" => $iblock["NAME"],
				"ACTIVE" => $iblock["ACTIVE"],
				"SORT" => $iblock["SORT"],
				"LIST_PAGE_URL" => $iblock["LIST_PAGE_URL"],
				"DETAIL_PAGE_URL" => $iblock["DETAIL_PAGE_URL"],
				"SECTION_PAGE_URL" => $iblock["SECTION_PAGE_URL"],
				"CANONICAL_PAGE_URL" => $iblock["CANONICAL_PAGE_URL"],
				"DESCRIPTION" => $iblock["DESCRIPTION"],
				"DESCRIPTION_TYPE" => $iblock["DESCRIPTION_TYPE"],
				"RSS_TTL" => $iblock["RSS_TTL"],
				"RSS_ACTIVE" => $iblock["RSS_ACTIVE"],
				"RSS_FILE_ACTIVE" => $iblock["RSS_FILE_ACTIVE"],
				"RSS_FILE_LIMIT" => $iblock["RSS_FILE_LIMIT"],
				"RSS_FILE_DAYS" => $iblock["RSS_FILE_DAYS"],
				"RSS_YANDEX_ACTIVE" => $iblock["RSS_YANDEX_ACTIVE"],
				"INDEX_ELEMENT" => $iblock["INDEX_ELEMENT"],
				"INDEX_SECTION" => $iblock["INDEX_SECTION"],
				"SECTION_CHOOSER" => $iblock["SECTION_CHOOSER"],
				"LIST_MODE" => $iblock["LIST_MODE"],
				"EDIT_FILE_BEFORE" => $iblock["EDIT_FILE_BEFORE"],
				"EDIT_FILE_AFTER" => $iblock["EDIT_FILE_AFTER"],
			));
			$this->addLanguageStrings($record);
			$this->addFieldsSettings($record);
			$this->addSeoSettings($record);

			$dependency = clone $this->getDependency("IBLOCK_TYPE_ID");
			$dependency->setValue(
				Type::getInstance()->getXmlId(RecordId::createStringId($iblock["IBLOCK_TYPE_ID"]))
			);
			$record->setDependency("IBLOCK_TYPE_ID", $dependency);

			$result[] = $record;
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected function addLanguageStrings(Record $record)
	{
		$messages = \CIBlock::getMessages($record->getId());
		if ($messages)
		{
			$messagesValues = Value::treeToList($messages, "MESSAGES");
			$record->addFieldsRaw($messagesValues);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected function addFieldsSettings(Record $record)
	{
		$fields = \CIBlock::getFields($record->getId()->getValue());
		if ($fields)
		{
			foreach ($fields as $k => $field)
			{
				unset($fields[$k]["NAME"]);
			}
			$fieldsValues = Value::treeToList($fields, "FIELDS");
			$record->addFieldsRaw($fieldsValues);
		}
	}

	protected function addSeoSettings(Record $record)
	{
		$seoProps = new IblockTemplates($record->getId()->getValue());
		if ($templates = $seoProps->findTemplates())
		{
			foreach ($templates as $k => $template)
			{
				$templates[$k] = $template["TEMPLATE"];
			}
			$fieldsValues = Value::treeToList($templates, "SEO");
			$record->addFieldsRaw($fieldsValues);
		}
	}
	public function getDependencies()
	{
		return array(
			"IBLOCK_TYPE_ID" => new Link(Type::getInstance()),
		);
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsRaw();

		if($typeId = $this->getDependency("IBLOCK_TYPE_ID")->getId())
		{
			$fields["IBLOCK_TYPE_ID"] = $typeId->getValue();
		}
		$iblockObject = new \CIBlock();
		$isUpdated = $iblockObject->update($record->getId()->getValue(), $fields);
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($iblockObject->LAST_ERROR)));
		}
	}

	public function create(Record $record)
	{
		$fields = $record->getFieldsRaw();
		if($iblockTypeId = $record->getDependency("IBLOCK_TYPE_ID")->getId())
		{
			$fields["IBLOCK_TYPE_ID"] = $iblockTypeId->getValue();

			$iblockObject = new \CIBlock();
			$iblockId = $iblockObject->add($fields);
			if ($iblockId)
			{
				return $this->createId($iblockId);
			}
			else
			{
				throw new \Exception(trim(strip_tags($iblockObject->LAST_ERROR)));
			}
		}
		else
		{
			throw new \Exception("IBlock " . $record->getXmlId() . " haven`t dependency");
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if ($id)
		{
			$iblockObject = new \CIBlock();
			if (!$iblockObject->delete($id->getValue()))
			{
				throw new \Exception("Unknown error");
			}
		}
	}
}