<?
namespace Intervolga\Migrato\Data\Module\Main;


use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;

class GroupRights extends BaseData
{
	const ALL_USERS_GROUP_ID = 2;
	const CODE_RIGHT_DELIMITER = '___';

	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_GROUP_RIGHT'));
		$this->setFilesSubdir('/');
		$this->setDependencies(array(
			'GROUP' => new Link(Group::getInstance()),
			'TASK' => new Link(Task::getInstance()),
		));
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		//Get all users groups
		$rsGroups = \CGroup::GetList($by="ID", $order="asc", array("ADMIN"=>'N'));
		$groups = array();
		while($g = $rsGroups->Fetch())
		{
			if($g['ID'] != static::ALL_USERS_GROUP_ID)
				$groups[] = $g;
		}
		//Get all modules
		$modulesId = array();
		$rsInstalledModules = \CModule::GetList();
		while ($m = $rsInstalledModules->Fetch())
			$modulesId[] = $m['ID'];
		$result = array();
		foreach ($groups as $group) {
			$record = new Record($this);
			$record->setId($this->createId($group["ID"]));
			$record->setXmlId($group["STRING_ID"]);
			$fields = array();
			$dependencylist = array();
			foreach ($modulesId as $moduleId)
			{
				$roles = \CMain::GetUserRoles($moduleId, array($group["ID"]),'N');
				if($roles) {
					$tasksId = array();
					$dbRes = \CTask::GetList(array(), array( // TODO BINDING
						'MODULE_ID' => $moduleId
					));
					while ($task = $dbRes->fetch()) {
						if (in_array($task['LETTER'], $roles)) {
							$tasksId[] = $task['ID'];
						}
					}
					if ($tasksId) {
						$dependencylist[$moduleId] = $tasksId;
					}
					else{
						$fields[$moduleId] = $moduleId.static::CODE_RIGHT_DELIMITER.$roles[0];
					}

				}
			}
			if($fields)
				$record->addFieldsRaw(array(
					'CODE_RIGHT' => $fields,
				));
			$this->addGroupDependency($record, $group['ID']);
			if($dependencylist)
				$this->addTaskDependency($record, $dependencylist);
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
		$record->addDependencies(array('GROUP'=>$groupLink));
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param $taskId
	 */
	private function addTaskDependency($record,array $tasksId)
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

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	public function update(Record $record)
	{
		$links = $record->getDependencies();
		if($links)
		{
			if($links['GROUP'])
			{
				$groupId = $links['GROUP']->getId()->getValue();
				$tasks = $this->getRightsFromRecord($record);
				//Get all modules
				$modulesId = array();
				$rsInstalledModules = \CModule::GetList();
				while ($m = $rsInstalledModules->Fetch())
					$modulesId[] = $m['ID'];
				foreach ($modulesId as $moduleId)
				{
					$roles = \CMain::GetGroupRight($moduleId, array($groupId),'N'); //\CAllGroup::GetModulePermission
					if($tasks[$moduleId] && $tasks[$moduleId]['LETTER'] != $roles)
					{
						if($tasks[$moduleId]['ID'])
							\CAllGroup::SetModulePermission($groupId, $moduleId, $tasks[$moduleId]['ID']);
						else
							\CAllGroup::SetModulePermission($groupId, $moduleId, $tasks[$moduleId]['LETTER']);
					}
					elseif(!($tasks[$moduleId]) && $roles != NULL)
					{
						\CAllGroup::SetModulePermission($groupId, $moduleId, false);
					}
				}
			}
		}
	}

	private function getRightsFromRecord(Record $record)
	{
		$taskLinks = $record->getDependency('TASK');
		$tasks = array();
		if($taskLinks)
		{
			foreach ($taskLinks->getId() as $taskRecId)
			{
				$taskId = $taskRecId->getValue();
				$dbRes = \CTask::GetList(array(), array("ID" => $taskId));
				if($t = $dbRes->fetch())
					$tasks[$t['MODULE_ID']] = array(
						'LETTER' => $t['LETTER'],
						'ID'=>$t['ID']
					);
			}
		}
		$codeRights = $record->getFieldRaws('CODE_RIGHT');
		foreach ($codeRights as $codeRight)
		{
			$arCodeRight = explode(static::CODE_RIGHT_DELIMITER,$codeRight);
			if(count($arCodeRight) == 2)
			{
				$tasks[$arCodeRight[0]]['LETTER'] = $arCodeRight[1];
			}
		}
		return $tasks;
	}

	/**
	 * @param string $xmlId
	 *
	 * @return \Intervolga\Migrato\Data\RecordId|null
	 */
	public function findRecord($xmlId)
	{
		$rsGroups = \CGroup::GetList($by="ID", $order="asc", array(
			"ADMIN"=>'N',
			"STRING_ID" => $xmlId)
		);
		if($g = $rsGroups->fetch())
			return $this->createId($g['ID']);
		return null;
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 * @param string $xmlId
	 *
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	public function setXmlId($id, $xmlId)
	{
		//Is implemented in Group class.
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 *
	 * @return string
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	public function getXmlId($id)
	{
		$element = \CGroup::getByID($id->getValue());
		if ($element = $element->fetch())
		{
			return $element["STRING_ID"];
		}
		else
		{
			return "";
		}
	}
}