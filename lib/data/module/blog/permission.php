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

		return $result;
	}

//	public function update(Record $record)
//	{
//		$curValue = $record->getId()->getValue();
//		$arGroups = \CBlogUserGroupPerms::GetGroupPermissions($curValue["BLOG_ID"]);
//
//		$curFields = $record->getFieldsRaw();
//		$arGroups[$curValue["GROUP_ID"]] = $curFields["PERMISSION"];
//		$blog = new \CBlog();
//		$blog->SetPermission($curValue["BLOG_ID"], $arGroups);
//	}

//	protected function createInner(Record $record)
//	{
//		$blogLinkId = Blog::getInstance()->findRecord($record->getDependency("BLOG_ID")->getValue());
//		$groupLinkId = Group::getInstance()->findRecord($record->getDependency("GROUP_ID")->getValue());
//		if ($blogLinkId)
//		{
//			$blogId = $blogLinkId->getValue();
//			$arGroups = \CBlogUserGroupPerms::getList($blogId);
//			if ($groupLinkId)
//			{
//				$groupId = $groupLinkId->getValue();
//				$arGroups[$groupId] = $record->getField("PERMISSION")->getValue();
//				$iblock = new \CBlog();
//				$iblock->setPermission($blogId, $arGroups);
//
//				return $this->createId(array(
//					"BLOG_ID" => $blogId,
//					"GROUP_ID" => $groupId,
//				));
//			}
//			else
//			{
//				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.GROUP_NOT_FOUND'));
//			}
//		}
//		else
//		{
//			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_NOT_FOUND'));
//		}
//	}

//	protected function deleteInner(RecordId $id)
//	{
//		$complexId = $id->getValue();
//		$arGroups = \CBlogUserGroupPerms::getList($complexId["BLOG_ID"]);
//		if (in_array($complexId['GROUP_ID'], array_keys($arGroups)))
//		{
//			unset($arGroups[$complexId['GROUP_ID']]);
//			$iblock = new \CIBlock();
//			$iblock->setPermission($complexId["BLOG_ID"], $arGroups);
//		}
//	}

	public function getXmlId($id)
	{
		$array = $id->getValue();
		$blogData = Blog::getInstance();
		$groupData = Group::getInstance();




		$blogXmlId = $blogData->getXmlId($blogData->createId($array['BLOG_ID']));
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