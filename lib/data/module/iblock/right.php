<?php namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Main\Group;
use Intervolga\Migrato\Data\Module\Main\Task;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class Right extends BaseData
{
	const PREFIX_GROUP_CODE = 'G';

	protected static array $iblockRights = [];

	protected function configure()
	{
		Loader::includeModule('iblock');
		$this->setVirtualXmlId(true); // Есть XML_ID, но при создании вручную поле NULL
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_RIGHTS'));
		$this->setFilesSubdir('/type/iblock/');
		$this->setDependencies([
			'IBLOCK' => new Link(IblocK::getInstance()),
			'TASK' => new Link(Task::getInstance()),
			'GROUP' => new Link(Group::getInstance()),
		]);
	}

	public function createId($id)
	{
		return RecordId::createComplexId([
			'IBLOCK_ID' => intval($id['IBLOCK_ID']),
			'GROUP_ID' => intval(trim($id['GROUP_CODE'], static::PREFIX_GROUP_CODE)),
			'ENTITY_ID' => intval($id['ENTITY_ID']),
		]);
	}

	public function getXmlId($id)
	{
		$array = $id->getValue();
		$iblockData = Iblock::getInstance();
		$groupData = Group::getInstance();

		$iblockXmlId = $iblockData->getXmlId($iblockData->createId($array['IBLOCK_ID']));
		$groupId = $groupData->getXmlId($groupData->createId($array['GROUP_ID']));

		$md5 = md5(serialize([
			$iblockXmlId,
			$groupId,
		]));

		return BaseXmlIdProvider::formatXmlId($md5);
	}

	public function getList(array $filter = [])
	{
		$result = [];
		Loader::includeModule( 'iblock' );

		$iblockIterator = IblockTable::getList([
			'select' => ['ID']
		]);

		while ($iblock = $iblockIterator->fetch()){
			$this->initIblockRights($iblock['ID']);

			foreach (static::$iblockRights[$iblock['ID']] as $right){
				$record = new Record($this);
				$id = $this->createId($right);

				$record->setId($id);
				$record->setXmlId($this->getXmlId($id));

				foreach ([
					'IBLOCK' => [
						'CLASS' => IblocK::class,
						'ID' => RecordId::createNumericId($iblock['ID'])
					],
					'TASK' => [
						'CLASS' => Task::class,
						'ID' => $right['TASK_ID']
					],
					'GROUP' => [
						'CLASS' => Group::class,
						'ID' => RecordId::createNumericId(trim($right['GROUP_CODE'], static::PREFIX_GROUP_CODE))
					]
				] as $field => $desc)
				{
					$dependency = clone $this->getDependency($field);
					$dependency->setValue(
						$desc['CLASS']::getInstance()->getXmlId($desc['ID'])
					);
					$record->setDependency($field, $dependency);
				}

				$result[] = $record;
			}
		}

		return $result;
	}

	protected function createInner(Record $record)
	{
		return $this->setRights($record);
	}

	public function update(Record $record)
	{
		$this->setRights($record);
	}

	protected function deleteInner(RecordId $id)
	{
		$array = $id->getValue();
		$iblockId = $array['IBLOCK_ID'];

		if (!$iblockId){
			return;
		}

		$this->initIblockRights($iblockId);

		$obIBlockRights = new \CIBlockRights($iblockId);
		$obIBlockRights->setRights(static::$iblockRights[$iblockId]);
	}

	protected function setRights(Record $record)
	{
		$iblockLinkId = Iblock::getInstance()->findRecord($record->getDependency('IBLOCK')->getValue());
		$groupLinkId = Group::getInstance()->findRecord($record->getDependency('GROUP')->getValue());
		$taskLinkId = Task::getInstance()->findRecord($record->getDependency('TASK')->getValue());

		if (!$taskLinkId){
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.TASK_NOT_FOUND'));
		}

		if (!$iblockLinkId) {
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_NOT_FOUND'));
		}

		if (!$groupLinkId) {
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.GROUP_NOT_FOUND'));
		}

		$groupId = $groupLinkId->getValue();
		$taskId = $taskLinkId->getValue();
		$iblockId = $iblockLinkId->getValue();

		$this->initIblockRights($iblockId);

		$issetRight = false;

		foreach (static::$iblockRights[$iblockId] as $i => $right){
			if (
				$right['ENTITY_TYPE'] == 'iblock'
				&& $right['GROUP_CODE'] == static::PREFIX_GROUP_CODE.$groupId
				&& $right['ENTITY_ID'] == $iblockId
			){
				static::$iblockRights[$iblockId][$i] = array_merge(
					$right,
					[
						'TASK_ID' => $taskId,
					]
				);

				$issetRight = true;
				break;
			}
		}

		if (!$issetRight){
			static::$iblockRights[$iblockId]['n'.count(static::$iblockRights[$iblockId])] = [
				'TASK_ID' => $taskId,
				'GROUP_CODE' => static::PREFIX_GROUP_CODE.$groupId,
			];
		}

		$obIBlockRights = new \CIBlockRights($iblockId);
		$obIBlockRights->setRights(static::$iblockRights[$iblockId]);

		return $this->createId([
			'IBLOCK_ID' => $iblockId,
			'TASK_ID' => $taskId,
			'GROUP_CODE' => static::PREFIX_GROUP_CODE.$groupId,
			'ENTITY_ID' => $iblockId,
		]);
	}

	protected function initIblockRights($iblockId): void
	{
		if (!is_numeric($iblockId) || $iblockId <= 0){
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.INVALID_IBLOCK_ID'));
		}

		if (!static::$iblockRights[$iblockId]){
			static::$iblockRights[$iblockId] = (new \CIBlockRights($iblockId))->getRights();

			if (static::$iblockRights[$iblockId]){
				foreach (static::$iblockRights[$iblockId] as $i => $right){
					static::$iblockRights[$iblockId][$i]["IBLOCK_ID"] = $iblockId;
				}
			}
		}
	}
}