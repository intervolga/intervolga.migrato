<?php
namespace Intervolga\Migrato\Data\Module\perfmon;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;

Loc::loadMessages(__FILE__);

class Index extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		Loader::includeModule("perfmon");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.PERFMON_INDEX'));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CPerfomanceIndexComplete::getList($filter);
		while ($index = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($index['ID']);
			$record->setId($id);
			$record->setXmlId($index['INDEX_NAME']);
			$record->addFieldsRaw(array(
				"INDEX_NAME" => $index["INDEX_NAME"],
				"TABLE_NAME" => $index["TABLE_NAME"],
				"COLUMN_NAMES" => $index["COLUMN_NAMES"],
				"BANNED" => $index["BANNED"],
			));
			$result[] = $record;
		}
		return $result;
	}

	public function getXmlId($id)
	{
		$arFilter = array("ID" => $id);
		$getList = \CPerfomanceIndexComplete::getList($arFilter);
		if ($index = $getList->fetch())
		{
			return $index['INDEX_NAME'];
		}
		return 'Error';
	}


	public function update(Record $record)
	{
		$this->deleteInner($record);
		$this->createInner($record);
	}

	/**
	 * @param Record $record
	 * @return array
	 */
	protected function recordToArray(Record $record)
	{
		$array = array(
			'INDEX_NAME' => $record->getXmlId(),
			'TABLE_NAME' => $record->getFieldRaw('TABLE_NAME'),
			'COLUMN_NAMES' => $record->getFieldRaw('COLUMN_NAMES'),
			'BANNED' => $record->getFieldRaw('BANNED'),
		);
		return $array;
	}

	protected function createInner(Record $record)
	{
		global $DB;
		$data = $this->recordToArray($record);
		global $strError;
		$strError = '';
		$dbfields = $DB->query('CREATE INDEX ' . $data["INDEX_NAME"] . ' ON ' . $data["TABLE_NAME"] . ' (' . $data["COLUMN_NAMES"] . ') ');
		$result = \CPerfomanceIndexComplete::add($data);
		if (($dbfields) && ($result))
		{
			return $this->createId($result);
		}
		else
		{
			if ($strError)
			{
				throw new \Exception($strError);
			}
			else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.PERFMON_INDEX_UNKNOWN_ERROR'));
			}
		}
	}

	protected function deleteInner(Record $record)
	{
		global $DB;
		$data = $this->recordToArray($record);
		$DB->query('ALTER TABLE ' . $data['TABLE_NAME'] . ' DROP INDEX ' . $data["INDEX_NAME"]);
		$arFilter = array("INDEX_NAME" => $data["INDEX_NAME"]);
		$getList = \CPerfomanceIndexComplete::getList($arFilter);
		if ($index = $getList->fetch())
		{
			\CPerfomanceIndexComplete::delete($index['ID']);
		}
	}
}