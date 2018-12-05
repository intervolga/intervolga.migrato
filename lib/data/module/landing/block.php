<?php
namespace Intervolga\Migrato\Data\Module\landing;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Seo\Engine\Bitrix;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use \Bitrix\Landing\Internals\BlockTable as CLandingBlock;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Data\Link;

Loc::loadMessages(__FILE__);

class Block extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		Loader::includeModule("landing");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.LANDING_BLOCK_TYPE'));
//		$this->setReferences(array(
//			'LID' => new Link(Landing::getInstance()),
//		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = CLandingBlock::getList();
		while ($block = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($block['ID']);
			$record->setId($id);
			$record->setXmlId($block['CODE'] . $block['ID']);
			$record->addFieldsRaw(array(
				"CODE" => $block["CODE"],
				"SORT" => $block["SORT"],
				"ACTIVE" => $block["ACTIVE"],
				"PUBLIC" => $block["PUBLIC"],
				"DELETED" => $block["DELETED"],
				"ACCESS" => $block["ACCESS"],
				"CONTENT" => $block["CONTENT"],
				"LID" => $block["LID"],
			));

//			$reference = clone $this->getReference("LID");
//			$reference->setValue(
//				Landing::getInstance()->getXmlId(RecordId::createNumericId($block["LID"]))
//			);
//			$record->setReference("LID", $reference);

			$result[] = $record;
		}
		return $result;
	}

	public function getXmlId($id)
	{
		$block = CLandingBlock::getById($id->getValue())->fetch();
		return $block;
	}


	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		global $strError;
		$strError = '';
		$result = CLandingBlock::update($id, $data);
		if (!$result)
		{
			if ($strError)
			{
				throw new \Exception($strError);
			} else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.LANDING_BLOCK_UNKNOWN_ERROR'));
			}
		}
	}

	/**
	 * @param Record $record
	 * @return array
	 */
	protected function recordToArray(Record $record)
	{
		$array = array(
			'CODE' => $record->getFieldRaw('CODE'),
			'LID' => $record->getFieldRaw('LID'),
			'SORT' => $record->getFieldRaw('SORT'),
			'ACTIVE' => $record->getFieldRaw('ACTIVE'),
			'PUBLIC' => $record->getFieldRaw('PUBLIC'),
			'DELETED' => $record->getFieldRaw('DELETED'),
			'ACCESS' => $record->getFieldRaw('ACCESS'),
			'CONTENT' => $record->getFieldRaw('CONTENT'),
		);

//		if ($reference = $record->getReference("LID"))
//		{
//			if ($reference->getId())
//			{
//				$array["LID"] = $reference->getId()->getValue();
//			}
//		}

		return $array;
	}

	/**
	 * @param Record $record
	 * @return RecordId
	 * @throws \Exception
	 */
	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		$data = $this->addRequiredFields($data);
		global $strError;
		$strError = '';
		$result = CLandingBlock::add($data);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		} else
		{
			return $this->createId($result->getId());
		}
	}

	protected function deleteInner(RecordId $id)
	{
		CLandingBlock::delete($id->getValue());
	}

	protected function addRequiredFields(array $data)
	{
		$data['CREATED_BY_ID'] = \Intervolga\Migrato\Data\Module\Main\Agent::ADMIN_USER_ID;
		$data['MODIFIED_BY_ID'] = \Intervolga\Migrato\Data\Module\Main\Agent::ADMIN_USER_ID;
		$data['DATE_CREATE'] = new \Bitrix\Main\Type\DateTime();
		$data['DATE_MODIFY'] = new \Bitrix\Main\Type\DateTime();;
		return $data;
	}
}