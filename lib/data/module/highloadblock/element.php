<? namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Iblock\Iblock;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Runtime;
use Intervolga\Migrato\Tool\XmlIdProvider\HlbElementXmlIdProvider;

class Element extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("highloadblock");
		$this->xmlIdProvider = new HlbElementXmlIdProvider($this);
	}

	public function getFilesSubdir()
	{
		return "/highloadblock/";
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$hlblocks = HighloadBlock::getInstance()->getList();
		/**
		 * @var \Intervolga\Migrato\Data\Record $hlblock
		 */
		foreach ($hlblocks as $hlblock)
		{
			$hlbId = $hlblock->getId()->getValue();
			$hlbFields = HighloadBlockTable::getById($hlbId)->fetch();
			$hlbEntity = HighloadBlockTable::compileEntity($hlbFields);
			$hlbClass = $hlbEntity->getDataClass();
			$getList = $hlbClass::getList();
			while ($element = $getList->fetch())
			{
				$result[] = $this->getRecord($element, $hlblock);
			}
		}

		return $result;
	}

	/**
	 * @param array $element
	 * @param \Intervolga\Migrato\Data\Record $hlblock
	 *
	 * @return \Intervolga\Migrato\Data\Record
	 */
	protected function getRecord(array $element, Record $hlblock)
	{
		$record = new Record($this);
		$idObject = RecordId::createComplexId(array(
			"ID" => intval($element["ID"]),
			"HLBLOCK_ID" => intval($hlblock->getId()->getValue()),
		));
		$record->setId($idObject);
		$record->setXmlId($element["UF_XML_ID"]);

		$link = clone $this->getDependency("HLBLOCK_ID");
		$link->setValue($hlblock->getXmlId());
		$record->addDependency("HLBLOCK_ID", $link);

		$this->addRuntime($record, $element, $hlblock->getId()->getValue());

		return $record;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param array $element
	 * @param int $hlblockId
	 */
	protected function addRuntime(Record $record, array $element, $hlblockId)
	{
		$runtime = clone $this->getRuntime("FIELD");

		$fields = Field::getInstance()->getList(array("HLBLOCK_ID" => $hlblockId));
		foreach ($fields as $field)
		{
			/**
			 * @var Record $field
			 */
			$fieldName = $field->getFieldValue("FIELD_NAME");
			Field::getInstance()->fillRuntime($runtime, $field, $element[$fieldName]);
		}

		if ($runtime->getFields() || $runtime->getDependencies() || $runtime->getReferences())
		{
			$record->setRuntime("FIELD", $runtime);
		}
	}

	public function getDependencies()
	{
		return array(
			"HLBLOCK_ID" => new Link(HighloadBlock::getInstance()),
		);
	}

	public function getRuntimes()
	{
		return array(
			"FIELD" => new Runtime(Field::getInstance()),
		);
	}

	/**
	 * @param array $fields
	 * @return array
	 */
	public function getRuntimesFields(array $fields)
	{
		$result = array();
		foreach($fields as $key => $value)
		{
			$fieldId = Field::getInstance()->findRecord($key)->getValue();
			$field = \CUserTypeEntity::GetByID($fieldId);
			$result[$field["FIELD_NAME"]] = $value->getValue();
		}
		return $result;
	}

	public function getRuntimesReferences(array $references)
	{
		$result = array();
		foreach($references as $key => $value)
		{
			if($value->getValue())
			{
				$fieldId = Field::getInstance()->findRecord($key)->getValue();
				$field = \CUserTypeEntity::GetByID($fieldId);
				$result[$field["FIELD_NAME"]] = $value->getTargetData()->findRecord($value->getValue())->getValue();
			}
		}
		return $result;
	}

	public function getDataClass($hlblockId)
	{
		$arHLBlock = HighloadBlockTable::getById($hlblockId)->fetch();
		$obEntity = HighloadBlockTable::compileEntity($arHLBlock);

		return $obEntity->getDataClass();
	}

	public function update(Record $record)
	{
		$id = $record->getId()->getValue();

		$runtimes = $record->getRuntime("FIELD");

		$fields = $this->getRuntimesFields($runtimes->getFields());

		$fields = array_merge($fields, $this->getRuntimesReferences($runtimes->getReferences()));

		$strEntityDataClass = $this->getDataClass($id["HLBLOCK_ID"]);

		$result = $strEntityDataClass::update($id["ID"], $fields);
		if(!$result->isSuccess())
		{
			throw new \Exception(trim(strip_tags($result->getErrorMessages())));
		}

	}

	public function create(Record $record)
	{
		$id = $record->getId()->getValue();

		$runtimes = $record->getRuntime("FIELD");

		$fields = $this->getRuntimesFields($runtimes->getFields());
		$fields = array_merge($fields, $this->getRuntimesReferences($runtimes->getReferences()));

		$strEntityDataClass = $this->getDataClass($id["HLBLOCK_ID"]);
		$result = $strEntityDataClass::add($fields);
		if ($result->isSuccess())
		{
			$id = RecordId::createNumericId($result->getId());
			$this->getXmlIdProvider()->setXmlId($id, $record->getXmlId());

			return $id;
		}
		else
		{
			throw new \Exception(trim(strip_tags($result->getErrorMessages())));
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId)->getValue();
		$strEntityDataClass = $this->getDataClass($id["HLBLOCK_ID"]);

		$result = $strEntityDataClass::delete($id["ID"]);
		if(!$result->isSuccess())
		{
			throw new \Exception($result->getErrorMessages());
		}
	}
}