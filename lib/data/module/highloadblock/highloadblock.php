<?php
namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class HighloadBlock extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.HIGHLOADBLOCK_HIGHLOADBLOCK'));
	}

	public function getList(array $filter = array())
	{
		$hlBlocks = HighloadBlockTable::getList();
		$result = array();
		while ($hlBlock = $hlBlocks->fetch())
		{
			$record = new Record($this);
			$id = RecordId::createNumericId($hlBlock["ID"]);
			$xmlId = $this->getXmlId($id);
			$record->setXmlId($xmlId);
			$record->setId($id);
			$record->addFieldsRaw(array(
				"NAME" => $hlBlock["NAME"],
				"TABLE_NAME" => $hlBlock["TABLE_NAME"],
			));

			$result[] = $record;
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$record = HighloadBlockTable::getById($id->getValue())->fetch();

		return strtolower($record['TABLE_NAME']);
	}

	public function update(Record $record)
	{
		$result = HighloadBlockTable::update($record->getId()->getValue(), $record->getFieldsRaw());
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}

	protected function createInner(Record $record)
	{
		$result = HighloadBlockTable::add($record->getFieldsRaw());
		if ($result->isSuccess())
		{
			$id = RecordId::createNumericId($result->getId());

			return $id;
		}
		else
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$result = HighloadBlockTable::delete($id->getValue());
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}
}