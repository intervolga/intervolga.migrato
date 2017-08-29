<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use \Intervolga\Migrato\Data\BaseData,
	\Intervolga\Migrato\Data\Record,
    \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

class Filter extends BaseData
{
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
			$record->setId(\Intervolga\Migrato\Data\RecordId::createNumericId($arFilter['ID']));
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
			return $id;
		return new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_PROPERTY_FILTER_ADD_ERROR'));
	}

	protected function deleteInner($xmlId)
	{
		$id = $this->findRecord($xmlId);
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
			return md5( $filter["USER_ID"] . $filter["NAME"] . $filter["COMMON"]. $filter['fields'] );
		}
		return "";
	}

	protected function getXmlIdByObject(array $fields)
	{
		$res = md5($fields["USER_ID"] . $fields["NAME"] . $fields["COMMON"] . $fields['fields']);
		return $res;
	}
}