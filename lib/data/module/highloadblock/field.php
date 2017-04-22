<? namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Intervolga\Migrato\Data\BaseUserField;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

class Field extends BaseUserField
{
	public function getFilesSubdir()
	{
		return "/highloadblock/";
	}

	/**
	 * @param string $userFieldEntityId
	 *
	 * @return int
	 */
	public function isCurrentUserField($userFieldEntityId)
	{
		return preg_match("/^HLBLOCK_[0-9]+$/", $userFieldEntityId);
	}

	public function getDependencies()
	{
		return array(
			"HLBLOCK_ID" => new Link(HighloadBlock::getInstance()),
		);
	}

	/**
	 * @param array $userField
	 *
	 * @return Record
	 */
	protected function userFieldToRecord(array $userField)
	{
		$record = parent::userFieldToRecord($userField);
		$hlBlockId = str_replace("HLBLOCK_", "", $userField["ENTITY_ID"]);
		$hlBlockRecordId = RecordId::createNumericId($hlBlockId);
		$hlBlockXmlId = HighloadBlock::getInstance()->getXmlId($hlBlockRecordId);

		$dependency = clone $this->getDependency("HLBLOCK_ID");
		$dependency->setValue($hlBlockXmlId);
		$record->setDependency("HLBLOCK_ID", $dependency);

		return $record;
	}

	public function getList(array $filter = array())
	{
		if ($filter["HLBLOCK_ID"])
		{
			$filter["ENTITY_ID"] = "HLBLOCK_" . $filter["HLBLOCK_ID"];
			unset($filter["HLBLOCK_ID"]);
		}

		return parent::getList($filter);
	}

	public function getDependencyString()
	{
		return "HLBLOCK_ID";
	}

	public function getDependencyNameKey($id)
	{
		return "HLBLOCK_" . $id;
	}

	public function create(Record $record)
	{
		$hlblockLink = $record->getDependency($this->getDependencyString());
		if ($hlblockLink && ($hlblockId = $hlblockLink->getId()))
		{
			$record->setFieldRaw("ENTITY_ID", $this->getDependencyNameKey($hlblockId->getValue()));

			return parent::create($record);
		}
		else
		{
			$module = static::getModule();
			$entity = static::getEntityName();
			throw new \Exception("Create $module/$entity: record haven`t the dependence for element " . $record->getXmlId());
		}
	}
}