<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use \Intervolga\Migrato\Data\BaseData,
	\Intervolga\Migrato\Data\Record,
	\Intervolga\Migrato\Data\Link,
	\Intervolga\Migrato\Data\Module\Main\Language,
	Intervolga\Migrato\Data\Module\Iblock\Iblock as MigratoIblock,
    \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

class Filter extends BaseData
{
	const XML_ID_SEPARATOR = '.';
	const FILTER_IBLOCK_TABLE_NAME = 'tbl_iblock_element_';
	const PROPERTY_FIELD_PREFIX = 'find_el_property_';

	public function __construct()
	{
		Loader::includeModule("iblock");
	}

	public function getFilesSubdir()
	{
		return "/";
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$result = array();
		$filterParams = [
			0 => ['USER_ID' => '1'],
			1 => ['COMMON' => 'Y']
		];
		$filtersId = array();
		foreach ($filterParams as $filterParam)
		{
			$newFilter = array_merge($filter, $filterParam);
			$dbRes = \CAdminFilter::GetList(array(), $newFilter);
			while ($arFilter = $dbRes->Fetch())
			{
				if (strpos($arFilter['FILTER_ID'], static::FILTER_IBLOCK_TABLE_NAME) === 0 && !in_array($arFilter['ID'], $filtersId))
				{
					$filtersId[] = $arFilter['ID'];
					$record = new Record($this);
					$record->setId($this->createId($arFilter['ID']));
					$record->setXmlId($this->getXmlIdByObject($arFilter));
					$record->setFieldRaw('NAME', $arFilter['NAME']);
					$record->setFieldRaw('COMMON', $arFilter['COMMON']);
					$record->setFieldRaw('PRESET', $arFilter['PRESET']);
					$record->setFieldRaw('PRESET_ID', $arFilter['PRESET_ID']);
					$record->setFieldRaw('SORT', $arFilter['SORT']);
					$record->setFieldRaw('SORT_FIELD', $arFilter['SORT_FIELD']);
					$this->addPropsDependencies($record, $arFilter['FIELDS']);
					$this->setDependencies($record, $arFilter);
					$result[] = $record;
				}
			}
		}
		return $result;
	}

	/**
	 * @param $filter_id - b_filter table field
	 * @return string
	 */
	private function getIblockXmlIdByFilterId($filter_id)
	{
		if(Loader::includeModule('iblock'))
		{
			$hash = substr($filter_id, strlen(static::FILTER_IBLOCK_TABLE_NAME));
			$hash = substr($hash, 0, strlen($hash) - 7); // strlen('_filter') == 7
			$res = \CIBlock::GetList();
			while ($iblock = $res->Fetch())
			{
				if (md5($iblock['IBLOCK_TYPE_ID'] . '.' . $iblock['ID']) == $hash)
					return $iblock['ID'];
			}
		}
		return '';
	}

	public function getDependencies()
	{
		return array(
			"LANGUAGE_ID" => new Link(Language::getInstance()),
			"IBLOCK_ID" => new Link(MigratoIblock::getInstance()),
			"PROPERTY_ID" => new Link(Property::getInstance()),
			"PROPERTY_ENUM_ID" => new Link(Enum::getInstance()),
		);
	}

	public function setDependencies(Record $record, array $arFilter)
	{
		//LANGUAGE_ID
		$languageId = $record->getFieldRaw('LANGUAGE_ID');
		if($languageId)
		{
			$dependency = clone $this->getDependency('LANGUAGE_ID');
			$dependency->setValue( Language::getInstance()->getXmlId( Language::getInstance()->createId($languageId) ));
			$record->setDependency('LANGUAGE_ID', $dependency );
		}
		//IBLOCK_ID
		if($arFilter['FILTER_ID'])
		{
			$iblockId = $this->getIblockXmlIdByFilterId($arFilter['FILTER_ID']);
			if ($iblockId)
			{
				$dependency = clone $this->getDependency('IBLOCK_ID');
				$dependency->setValue(MigratoIblock::getInstance()->getXmlId(MigratoIblock::getInstance()->createId($iblockId)));
				$record->setDependency('IBLOCK_ID', $dependency);
			}
		}
	}

	/**
	 * @param Record $record
	 * @param $fields - FIELDS field
	 */
	protected function addPropsDependencies(Record $record, $fields)
	{
		$newArrFields = $arrFields = unserialize($fields);
		$propsId = array();
		$propertyXmlIds = array();
		foreach ($arrFields as $fieldName => $arrField)
		{
			if(strpos($fieldName, static::PROPERTY_FIELD_PREFIX) === 0)
			{
				$propId = substr($fieldName, strlen(static::PROPERTY_FIELD_PREFIX));
				if($propId)
				{
					$propsId[] = $propId;
					$idObject = Property::getInstance()->createId($propId);
					$propertyXmlId = Property::getInstance()->getXmlId($idObject);
					$propertyXmlIds[] = $propertyXmlId;
					//convert field name using propery xmlId
					unset($newArrFields[$fieldName]);
					$newArrFields[static::PROPERTY_FIELD_PREFIX.$propertyXmlId] = $arrField;
				}
			}
		}
		//add property enum dependency
		$newArrFields = $this->addPropsEnumDependencies($record,$newArrFields,$propsId);
		//add field
		$record->setFieldRaw('FIELDS', serialize($newArrFields));
		//add property dependency
		if($propertyXmlIds)
		{
			$dependency = clone $this->getDependency('PROPERTY_ID');
			$dependency->setValues($propertyXmlIds);
			$record->setDependency('PROPERTY_ID', $dependency);
		}
	}

	/**
	 * @param Record $record
	 * @param $fields - field FIELDS with properties xmlId
	 * @param array $propertyIds
	 * @return mixed - converted FIELDS array with properties enum xmlId
	 */
	private function addPropsEnumDependencies(Record $record, $fields, array $propertyIds)
	{
		if(Loader::includeModule('iblock'))
		{
			$dbRes = \CIBlockProperty::GetList(
				array(),
				array(
					'PROPERTY_TYPE' => 'L'
				)
			);
			$enumXmlIds = array();
			while ($el = $dbRes->Fetch())
			{
				if(in_array($el['ID'],$propertyIds))
				{
					$propertyId = Property::getInstance()->createId($el['ID']);
					$propertyXmlId = Property::getInstance()->getXmlId($propertyId);
					if ($fields[static::PROPERTY_FIELD_PREFIX . $propertyXmlId])
					{
						$enumId = Enum::getInstance()->createId($fields[static::PROPERTY_FIELD_PREFIX . $propertyXmlId]['value']);
						$enumXmlId = Enum::getInstance()->getXmlId($enumId);
						if ($enumXmlId)
						{
							$enumXmlIds[] = $enumXmlId;
						}
						$fields[static::PROPERTY_FIELD_PREFIX . $propertyXmlId]['value'] = $enumXmlId;
					}
				}
			}
			if($enumXmlIds)
			{
				$dependency = clone $this->getDependency('PROPERTY_ENUM_ID');
				$dependency->setValues($enumXmlIds);
				$record->setDependency('PROPERTY_ENUM_ID', $dependency);
			}
		}
		return $fields;
	}

	/**
	 * Replace properties xmlId to id
	 * @param $fields
	 * @return string
	 */
	private function convertFieldsFromXml(array $arrFields)
	{
		$newArrFields = $arrFields;
		foreach ($arrFields as $key => $arrField)
		{
			if(strpos($key, static::PROPERTY_FIELD_PREFIX) === 0)
			{
				$xmlId = substr($key,strlen(static::PROPERTY_FIELD_PREFIX));
				$id = Property::getInstance()->findRecord($xmlId);
				if($id)
				{
					if(!is_array($arrField['value']))
					{
						$enumId = Enum::getInstance()->findRecord($arrField['value']);
						if($enumId)
							$arrField['value'] = $enumId->getValue();
					}
					unset($newArrFields[$key]);
					$newArrFields[static::PROPERTY_FIELD_PREFIX.$id->getValue()] = $arrField;
				}
			}
		}
		return $newArrFields;
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$xmlId = $record->getXmlId();
		$xmlFields = explode(static::XML_ID_SEPARATOR, $xmlId);
		if($xmlFields[0] == 'Y')
			$fields['USER_ID'] = 1;

		//создаем FILTER_ID записи
		$iblockXmlId = $xmlFields[3];
		$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
		if(Loader::includeModule('iblock'))
		{
			$dbres = \CIBlock::GetById($iblockId);
			if($iblockInfo = $dbres->GetNext())
			{
				$fields['FILTER_ID'] = static::FILTER_IBLOCK_TABLE_NAME . md5( $iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId ) . '_filter';
				$arFields = unserialize($fields['FIELDS']);
				if($arFields)
				{
					$fields['FIELDS'] = $this->convertFieldsFromXml($arFields);
					$fields['SORT_FIELD'] = unserialize($fields['SORT_FIELD']);
					$id = \CAdminFilter::Add($fields);
					if ($id)
						return $this->createId($id);
				}
			}
		}
		throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_FILTER_ADD_ERROR'));
	}

	public function update(Record $record)
	{
		$xmlId = $record->getXmlId();
		$filterId = $this->findRecord($xmlId);
		if($filterId)
		{
			$fields = $record->getFieldsRaw();
			$xmlId = $record->getXmlId();
			$xmlFields = explode(static::XML_ID_SEPARATOR, $xmlId);
			if($xmlFields[0] == 'Y')
				$fields['USER_ID'] = 1;

			//создаем FILTER_ID записи
			$iblockXmlId = $xmlFields[3];
			$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
			if(Loader::includeModule('iblock'))
			{
				$dbres = \CIBlock::GetById($iblockId);
				if($iblockInfo = $dbres->GetNext())
				{
					$fields['FILTER_ID'] = static::FILTER_IBLOCK_TABLE_NAME . md5( $iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId ) . '_filter';
					$arFields = unserialize($fields['FIELDS']);
					if($arFields)
					{
						$fields['FIELDS'] = $this->convertFieldsFromXml($arFields);
						$fields['SORT_FIELD'] = unserialize($fields['SORT_FIELD']);
						if (\CAdminFilter::Update($filterId->getValue(), $fields))
							return;
					}
				}
			}
		}
		throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_FILTER_UPDATE_ERROR'));
	}

	protected function deleteInner($xmlId)
	{
		$RecordId = $this->findRecord($xmlId);
		if($RecordId)
		{
			$id = $RecordId->getValue();
			$res = \CAdminFilter::Delete($id);
			if (!$res)
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_FILTER_DELETE_ERROR'));
			}
		}
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
			return $this->getXmlIdByObject($filter);
		}
		return "";
	}

	/**
	 * @param array $filter - filter fields
	 * @return string
	 */
	protected function getXmlIdByObject(array $filter)
	{
		$iblockId = $this->getIblockXmlIdByFilterId($filter['FILTER_ID']);
		if($iblockId)
		{
			$iblockXmlId = MigratoIblock::getInstance()->getXmlId(MigratoIblock::getInstance()->createId($iblockId));
			if ($iblockXmlId)
			{
				return (
					($filter["USER_ID"] == 1 ? 'Y' : 'N') . static::XML_ID_SEPARATOR .
					$filter["COMMON"] . static::XML_ID_SEPARATOR .
					md5($filter["NAME"]) . static::XML_ID_SEPARATOR . $iblockXmlId);
			}
		}
		return '';
	}

	public function findRecord($xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);

		$arFilter = array('COMMON' => $fields[1]);
		if($fields[0] === 'Y')
			$filter['USER_ID'] = 1;
		$name = $fields[2];
		$iblockXmlId = $fields[3];
		if($iblockRecord =  MigratoIblock::getInstance()->findRecord($iblockXmlId))
		{
			$iblockId = $iblockRecord->getValue();
			if (Loader::includeModule('iblock') && $iblockId)
			{
				$dbres = \CIBlock::GetById($iblockId);
				if ($iblockInfo = $dbres->GetNext())
				{
					$dbres = \CAdminFilter::getList([], $arFilter);
					while ($filter = $dbres->Fetch())
					{
						if (strpos($filter['FILTER_ID'], static::FILTER_IBLOCK_TABLE_NAME) === 0 && md5($filter['NAME']) === $name)
						{
							$hash = substr($filter['FILTER_ID'], strlen(static::FILTER_IBLOCK_TABLE_NAME));
							$hash = substr($hash, 0, strlen($hash) - 7); // strlen('_filter') == 7
							if (md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId) === $hash)
							{
								return $this->createId($filter['ID']);
							}
						}
					}
				}
			}
		}
		return null;
	}

	public function createId($id)
	{
		return \Intervolga\Migrato\Data\RecordId::createNumericId($id);
	}

	public function setXmlId($id, $xmlId)
	{
		// XML ID is autogenerated, cannot be modified
	}
}