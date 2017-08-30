<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use \Intervolga\Migrato\Data\BaseData,
	\Intervolga\Migrato\Data\Record,
    \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

class Filter extends BaseData
{
	const XML_ID_SEPARATOR = '.';

	public function __construct()
	{
		Loader::includeModule("iblock");
	}

	public function getFilesSubdir()
	{
		return "/type/iblock/";
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$result = array();
		$dbRes = \CAdminFilter::GetList(
			array(),
			array(
				'USER_ID' => '1',
				'COMMON' => 'Y'
			)
		);
		while($arFilter = $dbRes->Fetch())
		{
			$record = new Record($this);
			$record->setId($this->createId( $arFilter['ID'] ));
			$record->setXmlId($this->getXmlIdByObject($arFilter));
			$record->setFieldRaw('NAME', $arFilter['NAME']);
			$record->setFieldRaw('FILTER_ID', $arFilter['FILTER_ID']);
			$record->setFieldRaw('COMMON', $arFilter['COMMON']);
			$record->setFieldRaw('PRESET', $arFilter['PRESET']);
			$record->setFieldRaw('LANGUAGE_ID', $arFilter['LANGUAGE_ID']);
			$record->setFieldRaw('PRESET_ID', $arFilter['PRESET_ID']);
			$record->setFieldRaw('FIELDS', $arFilter['FIELDS']);

			$result[] = $record;
		}
		return $result;
	}

	public function getDependencies()
	{
		return array();
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$fields['FIELDS'] = unserialize($fields['FIELDS']);
		$id = \CAdminFilter::Add($fields);
		if($id)
			return $this->createId($id);
		return new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_PROPERTY_FILTER_ADD_ERROR'));
	}

	protected function deleteInner($xmlId)
	{
		$RecordId = $this->findRecord($xmlId);
		$id = $RecordId->getValue();
		$res = \CAdminFilter::Delete($id);
		if(!$res)
			return new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_PROPERTY_FILTER_DELETE_ERROR'));
	}

	public function getXmlId($id)
	{
		$dbRes = \CAdminFilter::GetList(
			array(),
			array(
				'ID' => $id
			)
		);
		if ($filter = $dbRes->Fetch())
		{
			$this->getXmlIdByObject($filter);
		}
		return "";
	}

	protected function getXmlIdByObject(array $filter)
	{
		if($filter['FIELDS'])
		{
			$fields = md5($filter['FIELDS']);
			return ($filter["USER_ID"] . static::XML_ID_SEPARATOR .
					$filter["FILTER_ID"] . static::XML_ID_SEPARATOR .
					$filter["COMMON"] . static::XML_ID_SEPARATOR.
					$fields);
		}
		return '';
	}

	public function findRecord($xmlId)
	{
		$id = null;
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$filter = [
			'USER_ID' => $fields[0],
			'FILTER_ID' => $fields[1],
			'COMMON' => $fields[2]
		];
		$dbres = \CAdminFilter::getList([],$filter);
		while ($filterRes = $dbres->Fetch())
		{
			if(md5($filterRes['FIELDS']) == $fields[3])
				return $this->createId($filterRes['ID']);
		}
		return null;
	}

	public function createId($id)
	{
		return \Intervolga\Migrato\Data\RecordId::createNumericId($id);
	}
}