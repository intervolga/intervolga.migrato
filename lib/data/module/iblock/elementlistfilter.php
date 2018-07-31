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

/**
 * Class ElementListFilter - настройки фильтра для списка элементов инфоблока в административной части
 * (совместный и раздельный режимы просмотра).
 *
 * @package Intervolga\Migrato\Data\Module\Iblock
 */
class ElementListFilter extends BaseData
{
	const XML_ID_SEPARATOR = '.';
	const TABLE_NAMES = array(
		'L' => 'tbl_iblock_list_',
		'E' => 'tbl_iblock_element_',
	);
	const PROPERTY_FIELD_PREFIX = 'find_el_property_';

	protected function configure()
	{
		Loader::includeModule('iblock');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ELEMENT_FILTER.ENTITY_NAME'));
		$this->setVirtualXmlId(true);
		$this->setFilesSubdir('/type/iblock/admin/');
		$this->setDependencies($this->getDependenciesArray());
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
			1 => ['COMMON' => 'Y'],
		];
		$filtersId = array();
		foreach ($filterParams as $filterParam)
		{
			$newFilter = array_merge($filter, $filterParam);
			$dbRes = \CAdminFilter::GetList(array(), $newFilter);
			while ($arFilter = $dbRes->Fetch())
			{
				if ($this->getTypeById($arFilter['FILTER_ID']) && !in_array($arFilter['ID'], $filtersId))
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
					$record->setFieldRaw('IS_ADMIN', $arFilter['USER_ID'] == 1 ? 'Y' : 'N');
					$this->addPropsDependencies($record, $arFilter['FIELDS']);
					$this->setRecordDependencies($record, $arFilter);
					$result[] = $record;
				}
			}
		}
		return $result;
	}

	/**
	 * @param $filterId - b_filter table field
	 * @return string
	 */
	private function getIblockXmlIdByFilterId($filterId)
	{
		$result = '';
		if (Loader::includeModule('iblock'))
		{
			$type = $this->getTypeById($filterId);
			$prefix = static::TABLE_NAMES[$type];
			if ($prefix)
			{
				$hash = substr($filterId, strlen($prefix));
				$hash = substr($hash, 0, strlen($hash) - 7); // strlen('_filter') == 7
				$res = \CIBlock::GetList();
				while ($iblock = $res->Fetch())
				{
					if (md5($iblock['IBLOCK_TYPE_ID'] . '.' . $iblock['ID']) == $hash)
					{
						$result = $iblock['ID'];
					}
				}
			}
		}
		return $result;
	}

	public function getDependenciesArray()
	{
		return array(
			'LANGUAGE' => new Link(Language::getInstance()),
			'IBLOCK_ID' => new Link(MigratoIblock::getInstance()),
			'PROPERTY_ID' => new Link(Property::getInstance()),
			'PROPERTY_ENUM_ID' => new Link(Enum::getInstance()),
		);
	}

	public function setRecordDependencies(Record $record, array $arFilter)
	{
		//LANGUAGE_ID
		if ($arFilter['LANGUAGE_ID'])
		{
			$dependency = clone $this->getDependency('LANGUAGE');
			$dependency->setValue(Language::getInstance()->getXmlId(Language::getInstance()->createId($arFilter['LANGUAGE_ID'])));
			$record->setDependency('LANGUAGE', $dependency);
		}
		//IBLOCK_ID
		if ($arFilter['FILTER_ID'])
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
			if (strpos($fieldName, static::PROPERTY_FIELD_PREFIX) === 0)
			{
				$propId = substr($fieldName, strlen(static::PROPERTY_FIELD_PREFIX));
				if ($propId)
				{
					$propsId[] = $propId;
					$idObject = Property::getInstance()->createId($propId);
					$propertyXmlId = Property::getInstance()->getXmlId($idObject);
					$propertyXmlIds[] = $propertyXmlId;
					//convert field name using propery xmlId
					unset($newArrFields[$fieldName]);
					$newArrFields[static::PROPERTY_FIELD_PREFIX . $propertyXmlId] = $arrField;
				}
			}
		}
		//add property enum dependency
		$newArrFields = $this->addPropsEnumDependencies($record, $newArrFields, $propsId);
		//add field
		$record->setFieldRaw('FIELDS', serialize($newArrFields));
		//add property dependency
		if ($propertyXmlIds)
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
		if (Loader::includeModule('iblock'))
		{
			$dbRes = \CIBlockProperty::GetList(
				array(),
				array(
					'PROPERTY_TYPE' => 'L',
				)
			);
			$enumXmlIds = array();
			while ($el = $dbRes->Fetch())
			{
				if (in_array($el['ID'], $propertyIds))
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
			if ($enumXmlIds)
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
			if (strpos($key, static::PROPERTY_FIELD_PREFIX) === 0)
			{
				$xmlId = substr($key, strlen(static::PROPERTY_FIELD_PREFIX));
				$id = Property::getInstance()->findRecord($xmlId);
				if ($id)
				{
					if (!is_array($arrField['value']))
					{
						$enumId = Enum::getInstance()->findRecord($arrField['value']);
						if ($enumId)
						{
							$arrField['value'] = $enumId->getValue();
						}
					}
					unset($newArrFields[$key]);
					$newArrFields[static::PROPERTY_FIELD_PREFIX . $id->getValue()] = $arrField;
				}
			}
		}
		return $newArrFields;
	}

	protected function xmlIdToArray($xmlId)
	{
		$xmlFields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$xmlFields[0] = static::TABLE_NAMES[$xmlFields[0]];
		return $xmlFields;
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$xmlId = $record->getXmlId();
		$xmlFields = $this->xmlIdToArray($xmlId);
		if ($xmlFields[1] == 'Y')
		{
			$fields['USER_ID'] = 1;
		}

		// FILTER_ID creating
		$iblockXmlId = $xmlFields[4];
		$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
		if (Loader::includeModule('iblock'))
		{
			$dbres = \CIBlock::GetById($iblockId);
			if ($iblockInfo = $dbres->GetNext())
			{
				$fields['FILTER_ID'] = $xmlFields[0] . md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId) . '_filter';
				$arFields = unserialize($fields['FIELDS']);
				if ($arFields)
				{
					$fields['FIELDS'] = $this->convertFieldsFromXml($arFields);
					$fields['SORT_FIELD'] = unserialize($fields['SORT_FIELD']);
					$fields['LANGUAGE_ID'] = $this->getLanguageFromDependency($record);
					$id = \CAdminFilter::Add($fields);
					if ($id)
					{
						return $this->createId($id);
					}
				}
			}
		}
		throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ELEMENT_FILTER.ADD_ERROR'));
	}

	private function getLanguageFromDependency(Record $record)
	{
		$link = $record->getDependency("LANGUAGE");
		if ($link)
		{
			$langXmlId = $link->getValue();
			$recId = Language::getInstance()->findRecord($langXmlId);
			if ($recId)
			{
				return $recId->getValue();
			}
		}
		return '';
	}

	public function update(Record $record)
	{
		$xmlId = $record->getXmlId();
		$filterId = $this->findRecord($xmlId);
		if ($filterId)
		{
			$fields = $record->getFieldsRaw();
			$xmlId = $record->getXmlId();
			$xmlFields = $this->xmlIdToArray($xmlId);
			if ($xmlFields[1] == 'Y')
			{
				$fields['USER_ID'] = 1;
			}

			// FILTER_ID creating
			$iblockXmlId = $xmlFields[4];
			$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
			if (Loader::includeModule('iblock'))
			{
				$dbres = \CIBlock::GetById($iblockId);
				if ($iblockInfo = $dbres->GetNext())
				{
					$fields['FILTER_ID'] = $xmlFields[0] . md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId) . '_filter';
					$arFields = unserialize($fields['FIELDS']);
					if ($arFields)
					{
						$fields['FIELDS'] = $this->convertFieldsFromXml($arFields);
						$fields['SORT_FIELD'] = unserialize($fields['SORT_FIELD']);
						$fields['LANGUAGE_ID'] = $this->getLanguageFromDependency($record);
						if (\CAdminFilter::Update($filterId->getValue(), $fields))
						{
							return;
						}
					}
				}
			}
		}
		throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ELEMENT_FILTER.UPDATE_ERROR'));
	}

	protected function deleteInner($xmlId)
	{
		$RecordId = $this->findRecord($xmlId);
		if ($RecordId)
		{
			$id = $RecordId->getValue();
			$res = \CAdminFilter::Delete($id);
			if (!$res)
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ELEMENT_FILTER.DELETE_ERROR'));
			}
		}
	}

	public function getXmlId($id)
	{
		$dbRes = \CAdminFilter::GetList(
			array(),
			array(
				'ID' => $id,
			)
		);
		if ($filter = $dbRes->Fetch())
		{
			return $this->getXmlIdByObject($filter);
		}
		return '';
	}

	/**
	 * @param array $filter - filter fields
	 * @return string
	 */
	protected function getXmlIdByObject(array $filter)
	{
		$result = '';
		$iblockId = $this->getIblockXmlIdByFilterId($filter['FILTER_ID']);
		if ($iblockId)
		{
			$iblockXmlId = MigratoIblock::getInstance()->getXmlId(MigratoIblock::getInstance()->createId($iblockId));
			if ($iblockXmlId)
			{
				$type = $this->getTypeById($filter['FILTER_ID']);
				$result = (
					$type . static::XML_ID_SEPARATOR .
					($filter['USER_ID'] == 1 ? 'Y' : 'N') . static::XML_ID_SEPARATOR .
					$filter['COMMON'] . static::XML_ID_SEPARATOR .
					md5($filter['NAME']) . static::XML_ID_SEPARATOR . $iblockXmlId);
			}
		}
		return $result;
	}

	protected function getTypeById($filterId)
	{
		$type = '';
		foreach (static::TABLE_NAMES as $key => $tableName)
		{
			if (strpos($filterId, $tableName) === 0)
			{
				$type = $key;
			}
		}
		return $type;
	}

	public function findRecord($xmlId)
	{
		$fields = $this->xmlIdToArray($xmlId);

		$arFilter = array('COMMON' => $fields[2]);
		if ($fields[1] === 'Y')
		{
			$arFilter['USER_ID'] = 1;
		}
		$name = $fields[3];
		$iblockXmlId = $fields[4];
		if ($iblockRecord = MigratoIblock::getInstance()->findRecord($iblockXmlId))
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
						if (strpos($filter['FILTER_ID'], $fields[0]) === 0 && md5($filter['NAME']) === $name)
						{
							$hash = substr($filter['FILTER_ID'], strlen($fields[0]));
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