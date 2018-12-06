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
class Blog extends BaseData
{
	protected function configure()
	{
		Loader::includeModule("blog");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.BLOG_BLOG'));
		$this->setDependencies(array(
			'GROUP' => new Link(Group::getInstance()),
		));
	}
	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CBlog::GetList(
			array(), Array(
			"OWNER_ID" => "1",
		),
			false,
			false,
			array(
				"NAME",
				"DESCRIPTION",
				"ACTIVE",
				"URL",
				"REAL_URL",
				"OWNER_ID",
				"GROUP_ID",
				"ENABLE_COMMENTS",
				"ENABLE_IMG_VERIF",
				"EMAIL_NOTIFY",
				"ENABLE_RSS",
				"AUTO_GROUPS",
				"ALLOW_HTML",
				"USE_SOCNET",
				"EDITOR_USE_FONT",
				"EDITOR_USE_LINK",
				"EDITOR_USE_IMAGE",
				"EDITOR_USE_FORMAT",
				"EDITOR_USE_VIDEO",
			)
		);
		while ($blog = $getList->Fetch())
		{
			$record = new Record($this);
			$id = $this->createId($blog['ID']);
			$record->setId($id);
			$record->setXmlId($blog['URL']);
			$record->addFieldsRaw(array(
				"NAME" => $blog["NAME"],
				"DESCRIPTION" => $blog["DESCRIPTION"],
				"ACTIVE" => $blog["ACTIVE"],
				"REAL_URL" => $blog["REAL_URL"],
				"OWNER_ID" => $blog["OWNER_ID"],
				"ENABLE_COMMENTS" => $blog["ENABLE_COMMENTS"],
				"ENABLE_IMG_VERIF" => $blog["ENABLE_IMG_VERIF"],
				"EMAIL_NOTIFY" => $blog["EMAIL_NOTIFY"],
				"ENABLE_RSS" => $blog["ENABLE_RSS"],
				"AUTO_GROUPS" => $blog["AUTO_GROUPS"],
				"ALLOW_HTML" => $blog["ALLOW_HTML"],
				"USE_SOCNET" => $blog["USE_SOCNET"],
				"EDITOR_USE_FONT" => $blog["EDITOR_USE_FONT"],
				"EDITOR_USE_LINK" => $blog["EDITOR_USE_LINK"],
				"EDITOR_USE_IMAGE" => $blog["EDITOR_USE_IMAGE"],
				"EDITOR_USE_FORMAT" => $blog["EDITOR_USE_FORMAT"],
				"EDITOR_USE_VIDEO" => $blog["EDITOR_USE_VIDEO"],
			));
			$link = clone $this->getDependency('GROUP');
			$link->setValue(
				Group::getInstance()->getXmlId(
					Group::getInstance()->createId($blog['GROUP_ID'])
				));
			$record->setDependency('GROUP', $link);
			$result[] = $record;
		}
		return $result;
	}
	public function getXmlId($id)
	{
		$blog = \CBlog::GetByID($id->getValue());
		return $blog['URL'];
	}
	public function setXmlId($id, $xmlId)
	{
		\CBlog::Update($id->getValue(), array('URL' => $xmlId));
	}
	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		global $strError;
		$strError = '';
		$result = \CBlog::Update($id, $data);
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
			'URL' => $record->getXmlId(),
			'NAME' => $record->getFieldRaw('NAME'),
			'DESCRIPTION' => $record->getFieldRaw('DESCRIPTION'),
			'ACTIVE' => $record->getFieldRaw('ACTIVE'),
			'OWNER_ID' => $record->getFieldRaw('OWNER_ID'),
			'ENABLE_COMMENTS' => $record->getFieldRaw('ENABLE_COMMENTS'),
			'ENABLE_IMG_VERIF' => $record->getFieldRaw('ENABLE_IMG_VERIF'),
			'EMAIL_NOTIFY' => $record->getFieldRaw('EMAIL_NOTIFY'),
			'ENABLE_RSS' => $record->getFieldRaw('ENABLE_RSS'),
			'AUTO_GROUPS' => $record->getFieldRaw('AUTO_GROUPS'),
			'ALLOW_HTML' => $record->getFieldRaw('ALLOW_HTML'),
			'USE_SOCNET' => $record->getFieldRaw('USE_SOCNET'),
			'EDITOR_USE_FONT' => $record->getFieldRaw('EDITOR_USE_FONT'),
			'EDITOR_USE_LINK' => $record->getFieldRaw('EDITOR_USE_LINK'),
			'EDITOR_USE_IMAGE' => $record->getFieldRaw('EDITOR_USE_IMAGE'),
			'EDITOR_USE_FORMAT' => $record->getFieldRaw('EDITOR_USE_FORMAT'),
			'EDITOR_USE_VIDEO' => $record->getFieldRaw('EDITOR_USE_VIDEO'),
		);
		$link = $record->getDependency('GROUP');
		if ($link && $link->getValue())
		{
			$array['GROUP_ID'] = $link->findId()->getValue();
		}
		return $array;
	}
	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		global $strError;
		$strError = '';
		$result = \CBlog::Add($data);
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
		\CBlog::Delete($id->getValue());
	}
}