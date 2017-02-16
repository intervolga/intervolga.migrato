<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Module\Main\Group;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataRecordId;
use Intervolga\Migrato\Tool\DataLink;
use Intervolga\Migrato\Tool\XmlIdProviders\TableXmlIdProvider;

class Permission extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new TableXmlIdProvider($this);
	}

	/**
	 * @return array|DataRecord[]
	 */
	public function getFromDatabase()
	{
		$result = array();
		foreach ($this->getIblocks() as $iblockId)
		{
			$permissions = \CIBlock::GetGroupPermissions($iblockId);
			foreach ($permissions as $groupId => $permission)
			{
				$id = DataRecordId::createComplexId(array(
					"IBLOCK_ID" => intval($iblockId),
					"GROUP_ID" => intval($groupId),
				));
				$record = new DataRecord();
				$record->setXmlId($this->getXmlIdProvider()->getXmlId($id));
				$record->setId($id);

				$record->setFields(array(
					"PERMISSION" => $permission,
				));
				$record->addDependency("GROUP_ID",
					new DataLink(
						Group::getInstance(),
						Group::getInstance()->getXmlIdProvider()->getXmlId(DataRecordId::createNumericId($groupId))
					)
				);
				$record->addDependency("IBLOCK_ID",
					new DataLink(
						Iblock::getInstance(),
						Iblock::getInstance()->getXmlIdProvider()->getXmlId(DataRecordId::createNumericId($iblockId))
					)
				);
				$result[] = $record;
			}
		}

		return $result;
	}

	/**
	 * @return array|int[]
	 */
	protected function getIblocks()
	{
		$result = array();
		$getList = \CIBlock::GetList();
		while ($iblock = $getList->fetch())
		{
			$result[] = $iblock["ID"];
		}

		return $result;
	}

	/**
	 * @param DataRecord $record
	 */
	public function update(DataRecord $record)
	{
		// TODO: Implement update() method.
	}

	/**
	 * @param DataRecord $record
	 */
	public function create(DataRecord $record)
	{
		// TODO: Implement create() method.
	}

	/**
	 * @param $xmlId
	 */
	public function delete($xmlId)
	{
		// TODO: Implement delete() method.
	}
}