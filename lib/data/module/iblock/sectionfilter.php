<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use CUserTypeEntity;
use \Intervolga\Migrato\Data\BaseData,
	\Intervolga\Migrato\Data\Record,
	\Intervolga\Migrato\Data\Link,
	\Intervolga\Migrato\Data\Module\Main\Language,
	Intervolga\Migrato\Data\Module\Iblock\Iblock as MigratoIblock,
    \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

class SectionFilter extends BaseData
{
	const XML_ID_SEPARATOR = '.';
	const TABLE_NAME = 'tbl_iblock_section_';
	const UF_PREFIX = 'find_UF_';

	public function __construct()
	{
		Loader::includeModule('iblock');
	}

	public function getFilesSubdir()
	{
		return '/';
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
				if (strpos($arFilter['FILTER_ID'], static::TABLE_NAME)===0 && !in_array($arFilter['ID'], $filtersId))
				{
					$iblockId = $this->getIblockIdByFilterId($arFilter['FILTER_ID']);
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
					$this->addUfDependencies($record, $arFilter['FIELDS'],$iblockId);
					$this->setDependencies($record, $arFilter);
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
	private function getIblockIdByFilterId($filterId)
	{
		$result = '';
		if(Loader::includeModule('iblock'))
		{
			$hash = substr($filterId, strlen(static::TABLE_NAME));
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
		return $result;
	}

	public function getDependencies()
	{
		return array(
			'LANGUAGE_ID' => new Link(Language::getInstance()),
			'IBLOCK_ID' => new Link(MigratoIblock::getInstance()),
			'FIELD' => new Link(Field::getInstance()),
			'FIELDENUM' => new Link(FieldEnum::getInstance()),
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
			$iblockId = $this->getIblockIdByFilterId($arFilter['FILTER_ID']);
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
	protected function addUfDependencies(Record $record, $fields, $iblockId)
	{
		$arrFields = unserialize($fields);
		$ufEnumValues = array();
		$ufEnumFields = array();
		$ufFieldIds = array();
		if($iblockId)
		{
			foreach($arrFields as $fieldName => $arrField)
			{
				if(strpos($fieldName, static::UF_PREFIX) === 0)
				{
					$ufName = substr($fieldName, strlen(static::UF_PREFIX));
					if($ufName)
					{
						$ufName = 'UF_'.$ufName;
						if(Loader::includeModule('iblock'))
						{
							$dbRes = CUserTypeEntity::GetList(array(), array(
								'ENTITY_ID' => 'IBLOCK_'.$iblockId.'_SECTION',
								'FIELD_NAME' => $ufName
							));
							while ($ufField = $dbRes->Fetch())
							{
								if($ufField['ID'])
									$ufFieldIds[] = Field::getInstance()->getXmlId(Field::getInstance()->createId($ufField['ID']));
								if ($ufField['USER_TYPE_ID'] == 'enumeration')
								{
									$ufEnumValues = array_merge($ufEnumValues,$arrField['value']);
									$ufEnumFields[] = $fieldName;
								}
							}
						}
					}
				}
			}
		}
		if($ufFieldIds)
		{
			$dependency = clone $this->getDependency('FIELD');
			$dependency->setValues($ufFieldIds);
			$record->setDependency('FIELD', $dependency);
		}
		if($ufEnumValues)
		{
			$this->setUfEnumDependencies($record, $ufEnumValues);
		}
		$this->addFieldsProperty($record, $fields, $ufEnumFields);
	}

	private function setUfEnumDependencies(Record $record, $arEnumId)
	{
		$enumFields = array();
		foreach ($arEnumId as $enumId)
		{
			$enumFields[] = FieldEnum::getInstance()->getXmlId(FieldEnum::getInstance()->createId($enumId));
		}
		$dependency = $this->getDependency('FIELDENUM');
		$dependency->setValues($enumFields);
		$record->setDependency('FIELDENUM', $dependency);
	}

	private function addFieldsProperty(Record $record, $fields, $enumFields)
	{
		$arrNewFields = $arrFields = unserialize($fields);
		if($enumFields)
		{
			foreach ($arrFields as $fieldName => $arrValue)
			{
				if (in_array($fieldName, $enumFields))
				{
					$arrNewFields[$fieldName]['value'] = array();
					foreach ($arrValue['value'] as $key => $value)
					{
						$enumXmlId = FieldEnum::getInstance()->getXmlId(FieldEnum::getInstance()->createId($value));
						$newKey = $key;
						if ($key === 'sel_' . $value)
						{
							$newKey = 'sel_' . $enumXmlId;
						}
						$arrNewFields[$fieldName]['value'][$newKey] = $enumXmlId;
					}
				}
			}
		}
		$record->setFieldRaw("FIELDS",serialize($arrNewFields));
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
		if($xmlFields[1] == 'Y')
			$fields['USER_ID'] = 1;

		// FILTER_ID creating
		$iblockXmlId = $xmlFields[4];
		$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
		if(Loader::includeModule('iblock'))
		{
			$dbres = \CIBlock::GetById($iblockId);
			if($iblockInfo = $dbres->GetNext())
			{
				$fields['FILTER_ID'] = $xmlFields[0] . md5( $iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId ) . '_filter';
				$arFields = unserialize($fields['FIELDS']);
				if($arFields)
				{
					$fields['FIELDS'] = $this->convertFieldsFromXml($arFields);
					$fields['SORT_FIELD'] = unserialize($fields['SORT_FIELD']);
					//TODO add LANGUAGE_ID field
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
			if($xmlFields[1] == 'Y')
				$fields['USER_ID'] = 1;

			// FILTER_ID creating
			$iblockXmlId = $xmlFields[4];
			$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
			if(Loader::includeModule('iblock'))
			{
				$dbres = \CIBlock::GetById($iblockId);
				if($iblockInfo = $dbres->GetNext())
				{
					$fields['FILTER_ID'] = $xmlFields[0] . md5( $iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId ) . '_filter';
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
		return '';
	}

	/**
	 * @param array $filter - filter fields
	 * @return string
	 */
	protected function getXmlIdByObject(array $filter)
	{
		$result = '';
		$iblockId = $this->getIblockIdByFilterId($filter['FILTER_ID']);
		if($iblockId)
		{
			$iblockXmlId = MigratoIblock::getInstance()->getXmlId(MigratoIblock::getInstance()->createId($iblockId));
			if ($iblockXmlId)
			{
				$result = (
					($filter['USER_ID'] == 1 ? 'Y' : 'N') . static::XML_ID_SEPARATOR .
					$filter['COMMON'] . static::XML_ID_SEPARATOR .
					md5($filter['NAME']) . static::XML_ID_SEPARATOR . $iblockXmlId);
			}
		}
		\Bitrix\Main\Diag\Debug::writeToFile(array($iblockId,$filter, $result),date("Y-m-d H:i:s").' '.__FILE__.':'.__LINE__,"/debug111209.txt");
		return $result;
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
						if (strpos($filter['FILTER_ID'], static::TABLE_NAME) === 0 && md5($filter['NAME']) === $name)
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