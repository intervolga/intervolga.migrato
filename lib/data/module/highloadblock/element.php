<? namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Runtime;

class Element extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("highloadblock");
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
		$record->setDependency("HLBLOCK_ID", $link);

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
			$fieldName = $field->getFieldRaw("FIELD_NAME");
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
	 * @param \Intervolga\Migrato\Data\Value[] $fields
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

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $links
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getRuntimesLinks(array $links)
	{
		$result = array();
		foreach($links as $key => $value)
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

	/**
	 * @param int $hlblockId
	 *
	 * @return \Bitrix\Main\Entity\DataManager
	 * @throws \Bitrix\Main\SystemException
	 */
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

		$fields = array_merge($fields, $this->getRuntimesLinks($runtimes->getReferences()));

		$strEntityDataClass = $this->getDataClass($id["HLBLOCK_ID"]);

		$result = $strEntityDataClass::update($id["ID"], $fields);
		if(!$result->isSuccess())
		{
			throw new \Exception(trim(strip_tags($result->getErrorMessages())));
		}

	}

	public function create(Record $record)
	{
		if($hlIblockId = $record->getDependency("HLBLOCK_ID")->getId())
		{
			$runtimes = $record->getRuntime("FIELD");

			$fields = $this->getRuntimesLinks($runtimes->getDependencies());

			$strEntityDataClass = $this->getDataClass($hlIblockId->getValue());
			$result = $strEntityDataClass::add($fields);
			if ($result->isSuccess())
			{
				return $this->createId($result->getId());
			}
			else
			{
				throw new \Exception(trim(strip_tags($result->getErrorMessages())));
			}
		}
		else
			throw new \Exception("Creating highloadblock/element: record " . $record->getXmlId() .  "haven`t HLBLOCK_ID");
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

	public function isXmlIdFieldExists()
	{
		return count($this->getNoXmlIdHlblocks()) == 0;
	}

	/**
	 * @return int[]
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\LoaderException
	 */
	protected function getNoXmlIdHlblocks()
	{
		$result = array();
		if (Loader::includeModule("highloadblock"))
		{
			$getList = HighloadBlockTable::getList(array(
				"select" => array(
					"ID",
				),
			));
			while ($hlblock = $getList->fetch())
			{
				$filter = array(
					"ENTITY_ID" => "HLBLOCK_" . $hlblock["ID"],
					"FIELD_NAME" => "UF_XML_ID",
				);
				$fieldsGetList = \CUserTypeEntity::getList(array(), $filter);

				if (!$fieldsGetList->selectedRowsCount())
				{
					$result[] = $hlblock["ID"];
				}
			}
		}

		return $result;
	}

	public function createXmlIdField()
	{
		foreach ($this->getNoXmlIdHlblocks() as $hlblockId)
		{
			$field = array(
				"ENTITY_ID" => "HLBLOCK_" . $hlblockId,
				"FIELD_NAME" => "UF_XML_ID",
				"USER_TYPE_ID" => "string",
				"XML_ID" => "HLBLOCK_" . $hlblockId . ".UF_MIGRATO_XML_ID",
				"SORT" => "100",
				"IS_SEARCHABLE" => "N",
			);
			$userTypeEntity = new \CUserTypeEntity();
			if (!$userTypeEntity->add($field))
			{
				throw new \Exception("UF_XML_ID userfield for hlblock $hlblockId elements was not created");
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 * @param string $xmlId
	 */
	public function setXmlId($id, $xmlId)
	{
		Loader::includeModule("highloadblock");
		$idArray = $id->getValue();

		$hlbFields = HighloadBlockTable::getById($idArray["HLBLOCK_ID"])->fetch();
		$hlbEntity = HighloadBlockTable::compileEntity($hlbFields);
		$hlbClass = $hlbEntity->getDataClass();
		$hlbClass::update($idArray["ID"], array("UF_XML_ID" => $xmlId));
	}

	/**
	 * @param RecordId $id
	 *
	 * @return string
	 */
	public function getXmlId($id)
	{
		Loader::includeModule("highloadblock");
		$idArray = $id->getValue();

		$hlbFields = HighloadBlockTable::getById($idArray["HLBLOCK_ID"])->fetch();
		$hlbEntity = HighloadBlockTable::compileEntity($hlbFields);
		$hlbClass = $hlbEntity->getDataClass();
		$hlblockElement = $hlbClass::getList(array(
			"select" => array(
				"UF_XML_ID",
			),
			"filter" => array(
				"=ID" => $idArray["ID"],
			),
		))->fetch();

		return $hlblockElement["UF_XML_ID"];
	}
}