<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\CultureTable;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Culture extends BaseData
{
	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_CULTURE'));
		$this->setFilesSubdir('/loc/');
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = CultureTable::getList();
		while ($culture = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($culture['ID']);
			$record->setId($id);
			$record->setXmlId($culture['NAME']);
			$record->addFieldsRaw(array(
				'CODE' => $culture['CODE'],
				'FORMAT_DATE' => $culture['FORMAT_DATE'],
				'FORMAT_DATETIME' => $culture['FORMAT_DATETIME'],
				'FORMAT_NAME' => $culture['FORMAT_NAME'],
				'WEEK_START' => $culture['WEEK_START'],
				'CHARSET' => $culture['CHARSET'],
				'DIRECTION' => $culture['DIRECTION'],
			));
			$result[] = $record;
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$culture = CultureTable::getById($id->getValue())->fetch();
		return $culture['NAME'];
	}

	public function setXmlId($id, $xmlId)
	{
		$result = CultureTable::update($id->getValue(), array('NAME' => $xmlId));
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}

	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		$result = CultureTable::update($id, $data);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 */
	protected function recordToArray(Record $record)
	{
		$array = array(
			'NAME' => $record->getXmlId(),
			'CODE' => $record->getFieldRaw('CODE'),
			'FORMAT_DATE' => $record->getFieldRaw('FORMAT_DATE'),
			'FORMAT_DATETIME' => $record->getFieldRaw('FORMAT_DATETIME'),
			'FORMAT_NAME' => $record->getFieldRaw('FORMAT_NAME'),
			'WEEK_START' => $record->getFieldRaw('WEEK_START'),
			'CHARSET' => $record->getFieldRaw('CHARSET'),
			'DIRECTION' => $record->getFieldRaw('DIRECTION'),
		);

		return $array;
	}

	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		$result = CultureTable::add($data);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
		else
		{
			return $this->createId($result->getId());
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$result = CultureTable::delete($id->getValue());
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}
}
