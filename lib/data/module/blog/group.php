<?php
namespace Intervolga\Migrato\Data\Module\Blog;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Module\Main\Site;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Group extends BaseData
{
	protected function configure()
	{
		Loader::includeModule("blog");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.BLOG_GROUP'));
		$this->setDependencies(array(
			'SITE' => new Link(Site::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CBlogGroup::GetList();

		while ($group = $getList->Fetch())
		{
			$record = new Record($this);
			$id = $this->createId($group['ID']);
			$record->setId($id);
			$record->setXmlId($group['NAME']);
			$record->addFieldsRaw(array());
			$link = clone $this->getDependency('SITE');
			$link->setValue(
				Site::getInstance()->getXmlId(
					Site::getInstance()->createId($group['SITE_ID'])
				)
			);
			$record->setDependency('SITE', $link);
			$result[] = $record;
		}
		return $result;
	}


	public function getXmlId($id)
	{
		$group = \CBlogGroup::GetByID($id->getValue());
		return $group['NAME'];
	}

	public function setXmlId($id, $xmlId)
	{
		\CBlogGroup::Update($id->getValue(), array('NAME' => $xmlId));
	}

	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		global $strError;
		$strError = '';
		$result = \CBlogGroup::Update($id, $data);
		if (!$result)
		{
			throw new \Exception(ExceptionText::getFromString($strError));
		}
	}

	/**
	 * @param Record $record
	 * @return array
	 */
	protected function recordToArray(Record $record)
	{
		$array = array(
			'NAME' => $record->getXmlId(),
		);
		$link = $record->getDependency('SITE');
		if ($link && $link->getValue())
		{
			$array['SITE_ID'] = $link->getValue();
		}

		return $array;
	}

	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		global $strError;
		$strError = '';
		$result = \CBlogGroup::Add($data);
		if ($result)
		{
			return $this->createId($result);
		}
		else
		{
			throw new \Exception(ExceptionText::getFromString($strError));
		}
	}

	protected function deleteInner(RecordId $id)
	{
		\CBlogGroup::Delete($id->getValue());
	}
}