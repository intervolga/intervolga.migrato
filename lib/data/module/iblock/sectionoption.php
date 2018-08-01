<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Localization\Loc,
	Intervolga\Migrato\Data\BaseData,
	Intervolga\Migrato\Data\Link,
	Intervolga\Migrato\Data\Record,
	Intervolga\Migrato\Data\Module\Iblock\Iblock as MigratoIblock,
	Bitrix\Main\Loader;


Loc::loadMessages(__FILE__);

/**
 * Class SectionOption - настройки показа списка разделов инфоблока в административной части
 * (раздельный режим просмотра).
 *
 * @package Intervolga\Migrato\Data\Module\Iblock
 */
class SectionOption extends BaseData
{
	const CATEGORY = 'list';
	const NAME_PREFIX = 'tbl_iblock_section_';
	const XML_ID_SEPARATOR = '.';
	const COLUMNS_DELIMITER = ',';

	protected function configure()
	{
		Loader::includeModule("iblock");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_SECTION_LIST_OPTIONS.ENTITY_NAME'));
		$this->setVirtualXmlId(true);
		$this->setFilesSubdir('/type/iblock/admin/');
		$this->setDependencies(array(
			'IBLOCK_ID' => new Link(MigratoIblock::getInstance()),
			'FIELD' => new Link(Field::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$filter['CATEGORY'] = self::CATEGORY;
		$filterParams = array(
			0 => array('USER_ID' => 1),
			1 => array('COMMON' => 'Y'),
		);
		$recordsId = array();
		foreach ($filterParams as $filterParam)
		{
			$newFilter = array_merge($filter, $filterParam);
			$dbRes = \CUserOptions::getList(array(), $filter);
			while ($uoption = $dbRes->fetch())
			{
				if (strpos($uoption['NAME'], static::NAME_PREFIX) === 0 && !in_array($uoption['ID'], $recordsId))
				{
					if ($value = unserialize($uoption['VALUE']))
					{
						$recordsId[] = $uoption['ID'];
						$record = new Record($this);
						$record->setId($this->createId($uoption['ID']));
						$record->setXmlId($this->getXmlIdByObject($uoption));
						$record->setFieldRaw('COMMON', $uoption['COMMON']);
						$record->setFieldRaw('CATEGORY', $uoption['CATEGORY']);
						$this->setRecordDependencies($record, $uoption);
						$iblockId = $this->getIblockIdByName($uoption['NAME']);
						$this->addUFieldDependencies($record, $value, $iblockId);
						$result[] = $record;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * @param Record $record
	 * @param array $value - VALUE field
	 * @param int $iblockId
	 */
	protected function addUFieldDependencies(Record $record, $value, $iblockId)
	{
		//Get fields Id
		$fieldsIds = array();
		$fields = array();
		$fieldsForConvert = array();
		if ($value['columns'])
		{
			$columns = $fields = explode(static::COLUMNS_DELIMITER, $value['columns']);
		}
		if ($value['by'] && strpos($value['by'], 'UF_') == 0 && !in_array($value['by'], $fields))
		{
			$fields[] = $value['by'];
		}
		if ($fields)
		{
			foreach ($fields as $fieldName)
			{
				if (strpos($fieldName, 'UF_') == 0)
				{
					if (Loader::includeModule('iblock'))
					{
						$dbRes = \CUserTypeEntity::GetList(array(), array(
							'ENTITY_ID' => 'IBLOCK_' . $iblockId . '_SECTION',
							'FIELD_NAME' => $fieldName,
						));
						while ($ufField = $dbRes->Fetch())
						{
							if ($ufField['ID'])
							{
								$xmlId = Field::getInstance()->getXmlId(Field::getInstance()->createId($ufField['ID']));
								$fieldsIds[] = $xmlId;
								$fieldsForConvert[$ufField['FIELD_NAME']] = $xmlId;
							}
						}
					}
				}
			}
		}
		//Set dependencies
		if ($fieldsIds)
		{
			$dependency = clone $this->getDependency('FIELD');
			$dependency->setValues($fieldsIds);
			$record->setDependency('FIELD', $dependency);
		}
		//Set VALUE field
		$newValue = $this->convertValueFieldToXml($value, $fieldsForConvert);
		$record->setFieldRaw('VALUE', serialize($newValue));
	}

	/**
	 * @param array $value - VALUE field
	 * @param array $fieldsXmlId - array, where key - field name, value - field xml id
	 * @return array
	 */
	private function convertValueFieldToXml($value, $fieldsXmlId)
	{
		$newValueField = $value;
		$columns = explode(static::COLUMNS_DELIMITER, $value['columns']);
		if ($columns)
		{
			$newColumns = $columns;
			if ($fieldsXmlId)
			{
				foreach ($columns as $key => $columnName)
				{
					if ($fieldsXmlId[$columnName])
					{
						$newColumns[$key] = 'UF_' . $fieldsXmlId[$columnName];
					}
				}
			}
			$newValueField['columns'] = implode(static::COLUMNS_DELIMITER, $newColumns);
		}


		//Convert field 'BY'
		if ($value['by'] && $fieldsXmlId[$value['by']])
		{
			$newValueField['by'] = 'UF_' . $fieldsXmlId[$value['by']];
		}
		return $newValueField;
	}

	/**
	 * Replace fields xml id with field name in VALUE field.
	 * @param array $value - VALUE field
	 * @param int $iblockId
	 * @return array
	 */
	private function convertValueFieldFromXml($value, $iblockId)
	{
		if ($value['columns'])
		{
			$columns = explode(static::COLUMNS_DELIMITER, $value['columns']);
			$newColumns = $columns;
			foreach ($columns as $key => $column)
			{
				if (strpos($column, 'UF_') === 0)
				{
					$fieldXmlId = substr($column, 3); //strlen('UF_') == 3
					if ($fieldXmlId)
					{

						$fieldId = Field::getInstance()->findRecord($fieldXmlId);
						if ($fieldId->getValue())
						{
							$dbRes = \CUserTypeEntity::GetList(array(), array(
								'ENTITY_ID' => 'IBLOCK_' . $iblockId . '_SECTION',
								'ID' => $fieldId->getValue(),
							));
							if ($ufField = $dbRes->Fetch())
							{
								$newColumns[$key] = $ufField['FIELD_NAME'];
							}
						}
					}
				}
			}
			$value['columns'] = implode(static::COLUMNS_DELIMITER, $newColumns);
		}
		if ($value['by'])
		{
			if (strpos($value['by'], 'UF_') === 0)
			{
				$fieldXmlId = substr($value['by'], 3); //strlen('UF_') == 3
				if ($fieldXmlId)
				{
					$fieldId = Field::getInstance()->findRecord($fieldXmlId);
					if ($fieldId)
					{
						$dbRes = \CUserTypeEntity::GetList(array(), array(
							'ENTITY_ID' => 'IBLOCK_' . $iblockId . '_SECTION',
							'ID' => $fieldId,
						));
						if ($ufField = $dbRes->Fetch())
						{
							$value['by'] = $ufField['FIELD_NAME'];
						}
					}
				}
			}
		}
		return $value;
	}

	/**
	 * Generate xmlId
	 * @param array $uoption
	 * @return string
	 */
	protected function getXmlIdByObject(array $uoption)
	{
		$iblockId = $this->getIblockIdByName($uoption['NAME']);
		if ($iblockId)
		{
			$iblockXmlId = MigratoIblock::getInstance()->getXmlId(MigratoIblock::getInstance()->createId($iblockId));
			if ($iblockXmlId)
			{
				return (
					($uoption["USER_ID"] == 1 ? 'Y' : 'N') . static::XML_ID_SEPARATOR .
					$uoption["COMMON"] . static::XML_ID_SEPARATOR .
					$iblockXmlId);
			}
		}
		return '';
	}

	/**
	 * @param string $xmlId
	 * @return array
	 */
	protected function xmlIdToArray($xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		if (count($fields) == 3)
		{
			return array(
				'IS_ADMIN' => $fields[0],
				'COMMON' => $fields[1],
				'IBLOCK_XML_ID' => $fields[2],
			);
		}
		return array();
	}

	/**
	 * @param string $name - NAME field
	 * @return string
	 */
	private function getIblockIdByName($name)
	{
		if (Loader::includeModule('iblock'))
		{
			$hash = substr($name, strlen(static::NAME_PREFIX));
			$res = \CIBlock::GetList();
			while ($iblock = $res->Fetch())
			{
				if (md5($iblock['IBLOCK_TYPE_ID'] . '.' . $iblock['ID']) == $hash)
				{
					return $iblock['ID'];
				}
			}
		}
		return '';
	}

	/**
	 * @param Record $record
	 * @param array $uoption
	 */
	public function setRecordDependencies(Record $record, array $uoption)
	{
		if ($uoption['NAME'])
		{
			$iblockId = $this->getIblockIdByName($uoption['NAME']);
			if ($iblockId)
			{
				$dependency = clone $this->getDependency('IBLOCK_ID');
				$dependency->setValue(MigratoIblock::getInstance()->getXmlId(MigratoIblock::getInstance()->createId($iblockId)));
				$record->setDependency('IBLOCK_ID', $dependency);
			}
		}
	}

	public function getXmlId($id)
	{
		$dbRes = \CUserOptions::GetList(
			array(),
			array(
				'ID' => $id,
			)
		);
		if ($uoption = $dbRes->Fetch())
		{
			return $this->getXmlIdByObject($uoption);
		}
		return "";
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$xmlId = $record->getXmlId();
		$xmlFields = $this->xmlIdToArray($xmlId);

		if ($xmlFields['IS_ADMIN'] == 'Y')
		{
			$fields['USER_ID'] = 1;
		}
		else
		{
			$fields['USER_ID'] = false;
		}

		//������� NAME ������
		$iblockXmlId = $xmlFields['IBLOCK_XML_ID'];
		$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
		if (Loader::includeModule('iblock'))
		{
			$dbres = \CIBlock::GetById($iblockId);
			if ($iblockInfo = $dbres->GetNext())
			{
				$fields['NAME'] = static::NAME_PREFIX . md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId);
				if ($value = unserialize($fields['VALUE']))
				{
					$fields['VALUE'] = $this->convertValueFieldFromXml($value, $iblockId);
				}
				If (\CUserOptions::SetOption($fields['CATEGORY'], $fields['NAME'], $fields['VALUE'], $fields['COMMON'] === 'Y', $fields['USER_ID']))
				{
					$filter = array(
						'NAME' => $fields['NAME'],
						'CATEGORY' => $fields['CATEGORY'],
						'COMMON' => $fields['COMMON'],
						'VALUE' => $fields['VALUE'],
					);
					$dbres = \CUserOptions::GetList(array(), $filter);
					if ($newOption = $dbres->fetch())
					{
						return $this->createId($newOption['ID']);
					}
				}
			}
		}
		throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_SECTION_LIST_OPTIONS.ADD_ERROR'));
	}

	protected function deleteInner($xmlId)
	{
		$RecordId = $this->findRecord($xmlId);
		if ($RecordId)
		{
			$id = $RecordId->getValue();
			$dbres = \CUserOptions::GetList(array(), array('ID' => $id));
			if ($uoption = $dbres->fetch())
			{
				$res = \CUserOptions::DeleteOptionsByName($uoption['CATEGORY'], $uoption['NAME']);
				if (!$res)
				{
					throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_SECTION_LIST_OPTIONS.DELETE_ERROR'));
				}
			}
		}
	}

	public function update(Record $record)
	{
		$fields = $this->recordToArray($record);

		$options = new \CUserOptions();
		$isUpdated = false;

		if ($fields['CATEGORY'] && $fields['NAME'])
		{
			$xmlId = $record->getXmlId();
			$xmlFields = $this->xmlIdToArray($xmlId);
			$iblockXmlId = $xmlFields['IBLOCK_XML_ID'];
			$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
			if ($value = unserialize($fields['VALUE']))
			{
				;
			}
			$fields['VALUE'] = $this->convertValueFieldFromXml($value, $iblockId);
			$isUpdated = $options->setOption(
				$fields['CATEGORY'],
				$fields['NAME'],
				$fields['VALUE'],
				$fields['COMMON'] == 'Y',
				$fields['USER_ID']
			);
		}

		if (!$isUpdated)
		{
			throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_SECTION_LIST_OPTIONS.NOT_UPDATED');
		}
	}

	/**
	 * @param Record $record
	 * @return \string[]
	 */
	protected function recordToArray(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$xmlId = $record->getXmlId();
		$xmlFields = $this->xmlIdToArray($xmlId);
		if ($xmlFields['IS_ADMIN'] == 'Y')
		{
			$fields['USER_ID'] = 1;
		}
		//������� NAME ������
		$iblockXmlId = $xmlFields['IBLOCK_XML_ID'];
		$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
		if (Loader::includeModule('iblock'))
		{
			$dbres = \CIBlock::GetById($iblockId);
			if ($iblockInfo = $dbres->GetNext())
			{
				$fields['NAME'] = static::NAME_PREFIX . md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId);
			}
		}
		return $fields;
	}

	public function findRecord($xmlId)
	{
		$id = null;
		$fields = $this->xmlIdToArray($xmlId);

		$arFilter = array('COMMON' => $fields['COMMON'],
			'CATEGORY' => static::CATEGORY);
		if ($fields['IS_ADMIN'] === 'Y')
		{
			$arFilter['USER_ID'] = 1;
		}
		$iblockXmlId = $fields['IBLOCK_XML_ID'];

		$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
		if (Loader::includeModule('iblock') && $iblockId)
		{
			$dbres = \CIBlock::GetById($iblockId);
			if ($iblockInfo = $dbres->GetNext())
			{
				$dbres = \CUserOptions::getList([], $arFilter);
				while ($uoption = $dbres->Fetch())
				{
					if (strpos($uoption['NAME'], static::NAME_PREFIX) === 0)
					{
						$hash = substr($uoption['NAME'], strlen(static::NAME_PREFIX));
						if (md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId) === $hash)
						{
							return $this->createId($uoption['ID']);
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