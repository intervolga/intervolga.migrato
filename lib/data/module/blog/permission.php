<?php
namespace Intervolga\Migrato\Data\Module\Blog;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class Permission extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		Loader::includeModule("blog");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.BLOG_PERMISSION'));
		$this->setDependencies(array(
			'GROUP_ID' => new Link(Group::getInstance()),
			'BLOG_ID' => new Link(Blog::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$permissions = \CBlogUserGroupPerms::getList();

		while ($permission = $permissions->fetch())
		{
			$id = $this->createId(array(
				"BLOG_ID" => $permission['BLOG_ID'],
				"GROUP_ID" => $permission['USER_GROUP_ID'],
			));
			$record = new Record($this);
			$record->setXmlId($this->getXmlId($id));
			$record->setId($id);

			$record->addFieldsRaw(array(
				"PERMISSION" => $permission['PERMS'],
			));

			$dependency = $this->getDependency("GROUP_ID");
			$dependency->setValue(Group::getInstance()->getXmlId(RecordId::createNumericId($permission['USER_GROUP_ID'])));
			$record->setDependency("GROUP_ID", $dependency);

			$dependency = clone $this->getDependency("BLOG_ID");
			$dependency->setValue(Blog::getInstance()->getXmlId(RecordId::createNumericId($permission['BLOG_ID'])));
			$record->setDependency("BLOG_ID", $dependency);

			$result[] = $record;
		}
	}


	/**
	 * @return array|int[]
	 */
	protected function getBlogs()
	{
		$result = array();
		$getList = \CBlog::GetList();
		while ($blog = $getList->fetch())
		{
			$result[] = $blog["ID"];
		}

		return $result;
	}


	public function getXmlId($id)
	{
		$array = $id->getValue();
		$blogData = Blog::getInstance();
		$groupData = Group::getInstance();
		$blogXmlId = $blogData->getXmlId($blogData->createId($array['IBLOCK_ID']));
		$groupXmlId = $groupData->getXmlId($groupData->createId($array['GROUP_ID']));
		$md5 = md5(serialize(array(
			$blogXmlId,
			$groupXmlId,
		)));
		return BaseXmlIdProvider::formatXmlId($md5);
	}

	public function createId($id)
	{
		return RecordId::createComplexId(array(
				"BLOG_ID" => intval($id['BLOG_ID']),
				"GROUP_ID" => intval($id['GROUP_ID']),
			)
		);
	}



}