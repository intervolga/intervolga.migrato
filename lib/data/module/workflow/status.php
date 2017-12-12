<?php
namespace Intervolga\Migrato\Data\Module\WorkFlow;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Main\Group;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\Orm\WorkFlow\StatusGroupTable;
use Intervolga\Migrato\Tool\Orm\WorkFlow\StatusTable;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class Status extends BaseData
{
	const FINAL_XML_ID = 'FINAL';

	protected function configure()
	{
		Loader::includeModule('workflow');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.WORKFLOW_STATUS'));
		$this->setVirtualXmlId(true);
		$this->setDependencies(array(
			'USER_GROUP_MOVE' => new Link(Group::getInstance()),
			'USER_GROUP_EDIT' => new Link(Group::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();

		if ($arStatuses = $this->getWorkFlowStatus())
		{
			foreach ($arStatuses as $idStatus => $status)
			{
				$result[] = $this->makeRecord($status);
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	protected function getWorkFlowStatus()
	{
		$arStatus = array();

		$rsStatus = StatusTable::getList();

		while ($arResStatus = $rsStatus->fetch())
		{
			$arStatus[] = $arResStatus;
		}

		return $arStatus;
	}

	/**
	 * @param array $status
	 *
	 * @return \Intervolga\Migrato\Data\Record
	 */
	protected function makeRecord($status)
	{
		$record = array();

		if ($status)
		{
			$record = new Record($this);
			$id = $this->createId($status["ID"]);
			$record->setId($id);

			if ($status['IS_FINAL'] == 'Y')
			{
				$record->setXmlId(static::FINAL_XML_ID);
			}
			else
			{
				$record->setXmlId($this->getXmlId($status["TITLE"]));
			}

			$record->addFieldsRaw(array(
				'C_SORT' => $status['C_SORT'],
				'ACTIVE' => $status['ACTIVE'],
				'TITLE' => $status['TITLE'],
				'DESCRIPTION' => $status['DESCRIPTION'] ?: '',
				'IS_FINAL' => $status['IS_FINAL'],
				'NOTIFY' => $status['NOTIFY'],
			));

			$this->addGroupDependency($status['ID'], $record, "USER_GROUP_MOVE");
			$this->addGroupDependency($status['ID'], $record, "USER_GROUP_EDIT");
		}

		return $record;
	}

	/**
	 * @param $id
	 * @param \Intervolga\Migrato\Data\Record
	 * @param string $type
	 */
	protected function addGroupDependency($id, Record $record, $type)
	{
		if ($arAccess = $this->getAccessesList($id, $type))
		{
			$link = clone $this->getDependency($type);

			$viewGroupsXmlIds = array();
			foreach ($arAccess as $access)
			{
				$groupIdObject = Group::getInstance()->createId($access);
				$groupXmlId = Group::getInstance()->getXmlId($groupIdObject);
				if ($groupXmlId)
				{
					$viewGroupsXmlIds[] = $groupXmlId;
				}
			}
			sort($viewGroupsXmlIds);
			$link->setValues($viewGroupsXmlIds);
			$record->setDependency($type, $link);
		}
	}

	/**
	 * @param string $type
	 * @return int
	 */
	protected function getPermissionType($type)
	{
		return ($type == 'USER_GROUP_MOVE') ? '1' : '2';
	}

	/**
	 * @param $id
	 * @param $type
	 * @return array
	 */
	protected function getAccessesList($id, $type)
	{
		$arAccess = array();
		$permissionType = $this->getPermissionType($type);

		$rsAccess = StatusGroupTable::getList(
			array(
				'filter' => array(
					'STATUS_ID' => $id,
					'PERMISSION_TYPE' => $permissionType,
				),
				'select' => array(
					'GROUP_ID',
				),
			)
		);

		while ($access = $rsAccess->fetch())
		{
			$arAccess[] = $access['GROUP_ID'];
		}

		return $arAccess;
	}

	public function createId($id)
	{
		return RecordId::createNumericId(intval($id));
	}

	public function getXmlId($id)
	{
		return BaseXmlIdProvider::formatXmlId(md5($id));
	}

	protected function deleteInner(RecordId $record)
	{
		$rsStatus = StatusTable::getList(
			array(
				'filter' => array(
					'TITLE' => $record->getValue(),
				),
			)
		);

		if ($status = $rsStatus->fetch())
		{
			if ($isDelete = StatusTable::delete($status['ID']))
			{
				$rsStatusGroup = StatusGroupTable::getList(
					array(
						'filter' => array(
							'STATUS_ID' => $status['ID'],
						),
						'select' => array(
							'ID',
						),
					)
				);

				while ($statusGroup = $rsStatusGroup->fetch())
				{
					StatusGroupTable::delete($statusGroup['ID']);
				}
			}
		}
	}

	public function createInner(Record $record)
	{
		$result = $record->getFieldsRaw();

		$obWorkflowStatus = new \CWorkflowStatus;

		$resultId = $obWorkflowStatus->Add(
			array(
				'C_SORT' => $result['C_SORT'],
				'ACTIVE' => $result['ACTIVE'],
				'TITLE' => $result['TITLE'],
				'DESCRIPTION' => $result['DESCRIPTION'] ?: '',
				'IS_FINAL' => $result['IS_FINAL'],
				'NOTIFY' => $result['NOTIFY'],
			)
		);

		if ($resultId)
		{
			$this->setPermissions($resultId, $record);
		}
		else
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}

		return $this->createId($result['TITLE']);
	}

	/**
	 * @param int $id
	 * @param \Intervolga\Migrato\Data\Record
	 */
	protected function setPermissions($id, Record $record)
	{
		$obWorkflowStatus = new \CWorkflowStatus;

		if ($id && $record)
		{
			if ($groupMove = $this->extractGroups($record, 'USER_GROUP_MOVE'))
			{
				$obWorkflowStatus->SetPermissions($id, $groupMove, $this->getPermissionType('USER_GROUP_MOVE'));
			}
			if ($groupEdit = $this->extractGroups($record, 'USER_GROUP_EDIT'))
			{
				$obWorkflowStatus->SetPermissions($id, $groupEdit, $this->getPermissionType('USER_GROUP_EDIT'));
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $type
	 * @return array
	 */
	protected function extractGroups(Record $record, $type)
	{
		$arGroup = array();

		if ($arGroupXml = $record->getDependency($type))
		{
			foreach ($arGroupXml->findIds() as $groupXml)
			{
				$arGroup[] = $groupXml->getValue();
			}
		}

		return $arGroup;
	}

	public function update(Record $record)
	{
		$obWorkflowStatus = new \CWorkflowStatus;

		$fields = $record->getFieldsRaw();

		$rsStatus = StatusTable::getList(
			array(
				'filter' => array(
					'TITLE' => $fields['TITLE'],
				),
			)
		);

		if ($status = $rsStatus->fetch())
		{
			if ($isUpdate = $obWorkflowStatus->Update($status['ID'], $fields))
			{
				$this->setPermissions($status['ID'], $record);
			}
			else
			{
				throw new \Exception(ExceptionText::getFromApplication());
			}
		}
	}
}