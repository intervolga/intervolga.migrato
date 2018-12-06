<?php
namespace Intervolga\Migrato\Data\Module\Blog;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Permission extends BaseData
{
	protected function configure()
	{
		Loader::includeModule("blog");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.BLOG_PERMISSION'));
//		$this->setDependencies(array(
//			'GROUP_ID' => new Link(Group::getInstance()),
//		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		foreach ($this->getGroups() as $groupId)
		{
			//$permissions = \CBlogUserGroup::GetGroupPerms($groupId);
		}
	}

	public function getXmlId($id)
	{
//		$blog = \CBlog::GetByID($id->getValue());
//		return $blog['URL'];
	}

	public function setXmlId($id, $xmlId)
	{

	}

	public function update(Record $record)
	{

	}

	/**
	 * @param Record $record
	 * @return array
	 */
	protected function recordToArray(Record $record)
	{

	}
	protected function createInner(Record $record)
	{

	}
	protected function deleteInner(RecordId $id)
	{

	}

	/**
	 * @return array|int[]
	 */
	protected function getGroups()
	{
		$result = array();
		$getList = \CBlogGroup::GetList();
		while ($group = $getList->fetch())
		{
			$result[] = $group["ID"];
		}

		return $result;
	}

}