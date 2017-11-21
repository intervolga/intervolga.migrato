<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

class GroupRights extends BaseData
{
	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_GROUP_RIGHT'));
		$this->setFilesSubdir('/');
		$this->setDependencies(array(
			'GROUP' => new Link(Group::getInstance()),
			'TASK' => new Link(Task::getInstance()),
		));
	}


	public function getList(array $filter = array())
	{
		//Get all users groups
		$rsGroups = \CGroup::GetList($by = "ID", $order = "asc", array("ADMIN" => 'N'));
		$groups = array();
		while ($g = $rsGroups->Fetch())
		{
			if ($g['ID'] != Group::GROUP_ALL_USERS)
			{
				$groups[] = $g;
			}
		}
		//Get all modules
		$modulesId = array();
		$rsInstalledModules = \CModule::GetList();
		while ($m = $rsInstalledModules->Fetch())
		{
			$modulesId[] = $m['ID'];
		}
		$result = array();
		foreach ($groups as $group)
		{
			$record = new Record($this);
			$record->setId($this->createId($group["ID"]));
			$groupClass = Group::getInstance();
			$record->setXmlId($groupClass->getXmlId($groupClass->createId($group["ID"])));
			$fields = array();
			$dependencyList = array();
			foreach ($modulesId as $moduleId)
			{
				$roles = \CMain::GetUserRoles($moduleId, array($group["ID"]), 'N');
				if ($roles)
				{
					$tasksId = array();
					$dbRes = \CTask::GetList(array(), array(
						'MODULE_ID' => $moduleId,
						'BINDING' => 'module',
					));
					while ($task = $dbRes->fetch())
					{
						if (in_array($task['LETTER'], $roles))
						{
							$tasksId[] = $task['ID'];
						}
					}
					if ($tasksId)
					{
						$dependencyList[$moduleId] = $tasksId;
					}
					else
					{
						$fields[$moduleId] = Task::createXmlId(array(
							'MODULE_ID' => $moduleId,
							'LETTER' => $roles[0],
						));
					}

				}
			}
			if ($fields)
			{
				$record->addFieldsRaw(array(
					'CODE_RIGHT' => $fields,
				));
			}
			$this->addGroupDependency($record, $group['ID']);
			if ($dependencyList)
			{
				$this->addTaskDependency($record, $dependencyList);
			}
			$result[] = $record;
		}
		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param $groupId
	 */
	private function addGroupDependency($record, $groupId)
	{
		$groupLink = clone($this->getDependency('GROUP'));
		$groupXmlId = Group::getInstance()->getXmlId(Group::getInstance()->createId($groupId));
		$groupLink->setValue($groupXmlId);
		$record->addDependencies(array('GROUP' => $groupLink));
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param $taskId
	 */
	private function addTaskDependency($record, array $tasksId)
	{
		$tasks = array();
		foreach ($tasksId as $id => $taskId)
		{
			$tasks[] = Task::getInstance()->getXmlId($taskId[0]);
		}
		$taskLink = clone($this->getDependency('TASK'));
		$taskLink->setValues($tasks);
		$record->setDependency('TASK', $taskLink);
	}

	public function update(Record $record)
	{
		$links = $record->getDependencies();
		if ($links)
		{
			if ($links['GROUP'])
			{
				$groupId = $links['GROUP']->getId()->getValue();
				$tasks = $this->getRightsFromRecord($record);
				$rsInstalledModules = \CModule::GetList();
				while ($m = $rsInstalledModules->Fetch())
				{
					$moduleId = $m['ID'];
					$roles = \CMain::GetGroupRight($moduleId, array($groupId), 'N');
					if ($tasks[$moduleId] && $tasks[$moduleId]['LETTER'] != $roles)
					{
						if ($tasks[$moduleId]['ID'])
						{
							\CAllGroup::SetModulePermission($groupId, $moduleId, $tasks[$moduleId]['ID']);
						}
						else
						{
							\CAllGroup::SetModulePermission($groupId, $moduleId, $tasks[$moduleId]['LETTER']);
						}
					}
					elseif (!($tasks[$moduleId]) && $roles != null)
					{
						\CAllGroup::SetModulePermission($groupId, $moduleId, false);
					}
				}
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @return array
	 * @throws \Exception
	 */
	private function getRightsFromRecord(Record $record)
	{
		$taskLinks = $record->getDependency('TASK');
		$tasks = array();
		if ($taskLinks)
		{
			/**
			 * @var RecordId $taskRecId
			 */
			foreach ($taskLinks->getId() as $taskRecId)
			{
				$taskId = $taskRecId->getValue();
				$dbRes = \CTask::GetList(array(), array("ID" => $taskId));
				if ($t = $dbRes->fetch())
				{
					$tasks[$t['MODULE_ID']] = array(
						'LETTER' => $t['LETTER'],
						'ID' => $t['ID'],
					);
				}
			}
		}
		$codeRights = $record->getFieldRaws('CODE_RIGHT');
		foreach ($codeRights as $codeRight)
		{
			$arCodeRight = explode(Task::XML_ID_SEPARATOR, $codeRight);
			if (count($arCodeRight) == 2)
			{
				$tasks[$arCodeRight[0]]['LETTER'] = $arCodeRight[1];
			}
		}
		return $tasks;
	}

	public function findRecord($xmlId)
	{
		$rsGroups = \CGroup::GetList($by = "ID", $order = "asc", array(
				"ADMIN" => 'N',
				"STRING_ID" => $xmlId)
		);
		if ($g = $rsGroups->fetch())
		{
			return $this->createId($g['ID']);
		}
		return null;
	}

	public function setXmlId($id, $xmlId)
	{
		//Is implemented in Group class.
	}

	public function getXmlId($id)
	{
		$groupClass = Group::getInstance();
		return $groupClass->getXmlId($id);
	}

	protected function deleteInner(RecordId $id)
	{
		$groupId = $id->getValue();
		$rsInstalledModules = \CModule::GetList();
		while ($m = $rsInstalledModules->Fetch())
		{
			\CAllGroup::SetModulePermission($groupId, $m['ID'], false);
		}
	}
}