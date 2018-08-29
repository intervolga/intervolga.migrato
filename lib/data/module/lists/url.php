<?php
namespace Intervolga\Migrato\Data\Module\Lists;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Iblock\Iblock;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Orm\Lists\UrlTable;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Url extends BaseData
{
	protected function configure()
	{
		Loader::includeModule('lists');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.LISTS_URL'));
		$this->setDependencies(array(
			'IBLOCK_ID' => new Link(Iblock::getInstance()),
		));
		$this->setVirtualXmlId(true);
	}

	public function getList(array  $filter = array())
	{
		$result = array();
		$getList = UrlTable::getList(array('filter' => $filter));
		while ($process = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($process['IBLOCK_ID']);
			$record->setId($id);
			$record->setXmlId($this->getXmlId($id));
			$dependency = clone $this->getDependency('IBLOCK_ID');
			$dependency->setValue(
				Iblock::getInstance()->getXmlId(RecordId::createStringId($process['IBLOCK_ID']))
			);
			$record->setDependency('IBLOCK_ID', $dependency);
			$record->addFieldsRaw(array(
				'URL' => str_replace($process['IBLOCK_ID'], '#IBLOCK_ID#' ,$process['URL']),
				'LIVE_FEED' => $process['LIVE_FEED'],
			));
			$result[] = $record;
		}
		
		return $result;
	}

	/**
	 * @param RecordId $id
	 * @return string
	 */
	public function getXmlId($id)
	{
		return Iblock::getInstance()->getXmlId($id);
	}

	/**
	 * @param Record $record
	 * @return RecordId
	 * @throws \Exception
	 */
	protected function createInner(Record $record)
	{
		$fields = $this->recordToArray($record);
		$result = UrlTable::add($fields);
		if ($result->isSuccess())
		{
			$id = $this->createId($result->getData()['IBLOCK_ID']);
			return $id;
		}
		else
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	/**
	 * @param RecordId $id
	 * @throws \Exception
	 */
	protected function deleteInner(RecordId $id)
	{
		$result = UrlTable::delete($id->getValue());
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	/**
	 * @param Record $record
	 * @throws \Exception
	 */
	public function update(Record $record)
	{
		$fields = $this->recordToArray($record);
		$result = UrlTable::update($record->getId()->getValue(), $fields);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	/**
	 * @param Record $record
	 * @return \string[]
	 */
	protected function recordToArray(Record $record)
	{
		$array = $record->getFieldsRaw(array('URL', 'LIVE_FEED'));

		if ($record->getDependency('IBLOCK_ID')->getValue()
			&& $iblockId = $record->getDependency('IBLOCK_ID')->findId()
		)
		{
			$array['URL'] = str_replace('#IBLOCK_ID#', $iblockId->getValue() ,$array['URL']);
			$array['IBLOCK_ID'] = $iblockId->getValue();
		}

		return $array;
	}
}