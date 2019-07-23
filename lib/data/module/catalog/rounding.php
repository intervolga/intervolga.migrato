<?php
namespace Intervolga\Migrato\Data\Module\Catalog;

use Bitrix\Catalog\RoundingTable;
use Bitrix\Catalog\GroupTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Rounding extends BaseData
{
	const PRICE_TYPE_DEPENDENCY_KEY = 'CATALOG_GROUP_ID';
	const XML_ID_SEPARATOR = '___';

	public static function getMinVersion()
	{
		return "16.5.4";
	}

	protected function configure()
	{
		Loader::includeModule('catalog');

		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.CATALOG_ROUNDING'));
		$this->setVirtualXmlId(true);
		$this->setDependencies(array(
			static::PRICE_TYPE_DEPENDENCY_KEY => new Link(PriceType::getInstance()),
		));
	}

	public function update(Record $record)
	{
		$recordId = $record->getId()->getValue();
		$recordAsArray = $this->recordToArray($record);

		$result = RoundingTable::update($recordId, $recordAsArray);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$roundingsDbResult = RoundingTable::getList();

		while ($rounding = $roundingsDbResult->fetch())
		{
			$result[] = $this->arrayToRecord($rounding);
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$roundingId = $id->getValue();
		$rounding = RoundingTable::getById($roundingId)->fetch();
		$catalogGroup = GroupTable::getById($rounding[static::PRICE_TYPE_DEPENDENCY_KEY])->fetch();

		$xmlId = $catalogGroup['XML_ID'] . static::XML_ID_SEPARATOR . str_replace('.', '_', $rounding['PRICE']);

		return $xmlId;
	}

	protected function createInner(Record $record)
	{
		$recordAsArray = $this->recordToArray($record);

		$result = RoundingTable::add($recordAsArray);
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
		$result = RoundingTable::delete($id->getValue());

		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}

	/**
	 * @param array $rounding
	 * @return Record
	 */
	protected function arrayToRecord($rounding)
	{
		$priceType = GroupTable::getById($rounding[static::PRICE_TYPE_DEPENDENCY_KEY])->fetch();
		$recordId = $this->createId($rounding['ID']);
		$xmlId = $this->getXmlId($recordId);

		$record = new Record($this);
		$record->setId($recordId);
		$record->setXmlId($xmlId);
		$record->addFieldsRaw(array(
			'PRICE' => $rounding['PRICE'],
			'ROUND_TYPE' => (int) $rounding['ROUND_TYPE'],
			'ROUND_PRECISION' => $rounding['ROUND_PRECISION'],
		));

		$dependency = clone $this->getDependency(static::PRICE_TYPE_DEPENDENCY_KEY);
		$dependency->setValue($priceType['XML_ID']);
		$record->setDependency(
			static::PRICE_TYPE_DEPENDENCY_KEY,
			$dependency
		);

		return $record;
	}

	/**
	 * @param Record $record
	 * @return array
	 */
	protected function recordToArray(Record $record)
	{
		$fields = $record->getFieldsRaw();

		$dependency = $record->getDependency(static::PRICE_TYPE_DEPENDENCY_KEY);
		$fields[static::PRICE_TYPE_DEPENDENCY_KEY] = $dependency->findId()->getValue();

		$this->castFields($fields);

		return $fields;
	}

	/**
	 * @param array $fields
	 */
	protected function castFields(array &$fields)
	{
		$fields['PRICE'] = floatval($fields['PRICE']);
		$fields['ROUND_TYPE'] = intval($fields['ROUND_TYPE']);
		$fields['ROUND_PRECISION'] = floatval($fields['ROUND_PRECISION']);
	}
}