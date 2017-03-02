<? namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
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
}