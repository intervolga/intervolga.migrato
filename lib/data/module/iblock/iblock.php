<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
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
			$record->setFields(array(
				"SITE_ID" => $iblock["LID"],
				"CODE" => $iblock["CODE"],
				"NAME" => $iblock["NAME"],
				"ACTIVE" => $iblock["ACTIVE"],
			));

			$dependency = clone $this->getDependency("IBLOCK_TYPE_ID");
			$dependency->setValue(
				Type::getInstance()->getXmlIdProvider()->getXmlId(RecordId::createStringId($iblock["IBLOCK_TYPE_ID"]))
			);
			$record->addDependency("IBLOCK_TYPE_ID", $dependency);
			$result[] = $record;
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			"IBLOCK_TYPE_ID" => new Link(Type::getInstance()),
		);
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsStrings();

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
		$fields = $record->getFieldsStrings();
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