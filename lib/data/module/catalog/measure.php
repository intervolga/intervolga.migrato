<?php
namespace Intervolga\Migrato\Data\Module\Catalog;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

Loc::loadMessages(__FILE__);

class Measure extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		Loader::includeModule("catalog");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.CATALOG_MEASURE'));
	}

	public function getList(array $filter = array())
	{
		$getList = \CCatalogMeasure::getList();
		$result = Array();
		while ($measure = $getList->Fetch())
		{
			$record = new Record($this);
			$id = $this->createId($measure['ID']);
			$record->setId($id);
			$record->setXmlId('measure_' . $measure["CODE"]);
			$record->addFieldsRaw(array(
				"CODE" => $measure["CODE"],
				"MEASURE_TITLE" => $measure["MEASURE_TITLE"],
				"SYMBOL_RUS" => $measure["SYMBOL_RUS"],
				"SYMBOL_INTL" => $measure["SYMBOL_INTL"],
				"SYMBOL_LETTER_INTL" => $measure["SYMBOL_LETTER_INTL"],
				"IS_DEFAULT" => $measure["IS_DEFAULT"],
				"SYMBOL" => $measure["SYMBOL"],
			));

			$result[] = $record;
		}
		return $result;
	}

	public function getXmlId($id)
	{
		$getList = \CCatalogMeasure::getList(array(), array("ID" => $id));
		if ($measure = $getList->Fetch())
		{
			return $measure["SID"];
		}

	}

	public function setXmlId($id, $xmlId)
	{
		\CCatalogMeasure::update($id->getValue(), array('SID' => $xmlId));

	}

	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		global $strError;
		$strError = '';
		$result = \CCatalogMeasure::update($id, $data);
		if (!$result)
		{
			if ($strError)
			{
				throw new \Exception($strError);
			} else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.MEASURE_UNKNOWN_ERROR'));
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
			'SID' => $record->getXmlId(),
			'CODE' => $record->getFieldRaw('CODE'),
			'MEASURE_TITLE' => $record->getFieldRaw('MEASURE_TITLE'),
			'SYMBOL_RUS' => $record->getFieldRaw('SYMBOL_RUS'),
			'SYMBOL_INTL' => $record->getFieldRaw('SYMBOL_INTL'),
			'SYMBOL_LETTER_INTL' => $record->getFieldRaw('SYMBOL_LETTER_INTL'),
			'IS_DEFAULT' => $record->getFieldRaw('IS_DEFAULT'),
			'SYMBOL' => $record->getFieldRaw('SYMBOL'),
		);
		return $array;
	}

	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		global $strError;
		$strError = '';
		$result = \CCatalogMeasure::add($data);
		if ($result)
		{
			return $this->createId($result);
		} else
		{
			if ($strError)
			{
				throw new \Exception($strError);
			} else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.MEASURE_UNKNOWN_ERROR'));
			}
		}
	}

	protected function deleteInner(RecordId $id)
	{
		\CCatalogMeasure::delete($id->getValue());
	}
}