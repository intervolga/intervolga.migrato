<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Iblock\Iblock as MigratoIblock;
use CUserOptions;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

/**
 * Class ElementFilter - настройки фильтра для списка элементов инфоблока в административной части
 * (совместный и раздельный режимы просмотра).
 *
 * В рамках текущей сущности:
 *  - таблица БД - b_user_option,
 *  - настройка - запись таблицы БД,
 *  - название настройки - поле NAME настройки,
 *  - категория настройки - поле CATEGORY настройки,
 *
 * Название настройки фильтра: <FILTER_NAME_PREFIX><HASH> , где:
 * 	- <FILTER_NAME_PREFIX> - одно из значений массива FILTER_NAME_PREFIXES,
 * 	- <HASH> - md5(IBLOCK_TYPE_ID + "." + IBLOCK_ID)
 *
 *
 * @package Intervolga\Migrato\Data\Module\Iblock
 */
class ElementFilter extends BaseData
{
	const XML_ID_SEPARATOR = '.';

	/**
	 * Соответствие типов фильтра названиям настроек.
	 * COMMON_VIEW - фильтр для ИБ (режим прссмотра - совместный).
	 * SEPARATE_VIEW_SECTION - фильтр для разделов ИБ (режим просмотра - раздельный)
	 * SEPARATE_VIEW_ELEMENT - фильтр для элементов ИБ (режим просмотра - раздельный)
	 */
	const FILTER_NAME_PREFIXES = array(
		'COMMON_VIEW' => 'tbl_iblock_list_',
		'SEPARATE_VIEW_SECTION' => 'tbl_iblock_section_',
		'SEPARATE_VIEW_ELEMENT' => 'tbl_iblock_element_',
	);

	/**
	 * Категории настроек фильтра.
	 */
	const FILTER_CATEGORIES = array(
		'main.ui.filter',
		'main.ui.filter.common',
		'main.ui.filter.common.presets'
	);

	/**
	 * Префикс свойства элемента ИБ в поле фильтра.
	 */
	const PROPERTY_FIELD_PREFIX = 'PROPERTY_';

	protected function configure()
	{
		Loader::includeModule('iblock');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ELEMENT_FILTER.ENTITY_NAME'));
		$this->setVirtualXmlId(true);
		$this->setFilesSubdir('/type/iblock/admin/');
		$this->setDependencies(array(
			'IBLOCK_ID' => new Link(MigratoIblock::getInstance()),
			'PROPERTY_ID' => new Link(Property::getInstance()),
			'PROPERTY_ENUM_ID' => new Link(Enum::getInstance()),
		));
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$result = array();
		$filtersName = array();

		/**
		 * Типы мигрируемых фильтров:
		 * - фильтры для админа
		 * - общие фильтры
		 */
		$optionTypeFilters = array(
			'ADMIN_OPTIONS' => array('USER_ID' => '1'),
			'COMMON_OPTIONS' => array('COMMON' => 'Y', 'USER_ID' => '0'),
		);

		foreach	($optionTypeFilters as $optionTypeFilter)
		{
			$optionsFilter = array_merge($filter, $optionTypeFilter);
			$dbRes = CUserOptions::GetList(array(), $optionsFilter);
			while ($option = $dbRes->Fetch())
			{
				if (static::isFilter($option))
				{
					$filtersName[] = $option;
					$record = new Record($this);
					$record->setId($this->createId($option['ID']));
					$record->setXmlId($this->getXmlIdFromArray($option));
					$record->setFieldRaw('COMMON', $option['COMMON']);
					$record->setFieldRaw('CATEGORY', $option['CATEGORY']);

					// TODO: Нужно ли это поле ?
					//$record->setFieldRaw('IS_ADMIN', $arFilter['USER_ID'] == 1 ? 'Y' : 'N');

					$this->addPropertiesDependencies($record, $option['VALUE']);
					$this->setRecordDependencies($record, $option);
					$result[] = $record;
				}
			}
		}

		return $result;
	}

	public function update(Record $record)
	{
		$xmlId = $record->getXmlId();
		$filterId = $this->findRecord($xmlId);
	}

	/**
	 * @param string $xmlId
	 *
	 * @return RecordId|null
	 */
	public function findRecord($xmlId)
	{
		// Получаем необходимые данные из xmlId
		$xmlFields = $this->getArrayFromXmlId($xmlId);
		$xmlFilterPrefix = $xmlFields[0];
		$xmlIsUserAdmin = $xmlFields[1];
		$xmlIsFilterCommon = $xmlFields[2];
		$xmlFilterCategory = $xmlFields[3];
		$xmlFilterName = $xmlFields[4];
		$xmlIblockXmlId = $xmlFields[5];

		// Данные ифнблока
		$iblockInfo = array();
		$iblockRecord = MigratoIblock::getInstance()->findRecord($xmlIblockXmlId);
		if ($iblockRecord)
		{
			$iblockId = $iblockRecord->getValue();
			if ($iblockId && Loader::includeModule('iblock'))
			{
				$iblockInfo = \CIBlock::GetById($iblockId)->Fetch();
			}
		}

		// Формируем фильтр для запроса
		$arFilter = array(
			'COMMON' => $xmlIsFilterCommon,
			'CATEGORY' => $xmlFilterCategory,
		);

		if ($xmlIsUserAdmin === 'Y')
		{
			$arFilter['USER_ID'] = 1;
		}

		if ($iblockInfo)
		{
			$dbRes = CUserOptions::GetList(array(), $arFilter);
			while ($filter = $dbRes->Fetch())
			{
				$filterName = $filter['NAME'];
				if (strpos($filterName, $xmlFilterPrefix) === 0
					&& md5($filterName) === $xmlFilterName
				)
				{
					$hash = substr($filterName, strlen($xmlFilterPrefix));
					if (md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockInfo['ID']) === $hash)
					{
						return $this->createId($filter['ID']);
					}
				}
			}
		}

		return null;
	}

	protected function deleteInner(RecordId $id)
	{
		$result = false;

		$dbRes = CUserOptions::GetList(array(), array('ID' => $id->getValue()));
		if ($filter = $dbRes->Fetch())
		{
			$result = CUserOptions::DeleteOptionsByName($filter['CATEGORY'], $filter['NAME']);
		}

		if (!$result)
		{
			$exceptionMessage = ExceptionText::getFromString(
				Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ELEMENT_FILTER.DELETE_ERROR')
			);
			throw new \Exception($exceptionMessage);
		}
	}

	protected function addPropertiesDependencies(Record $record, $fields)
	{
		// TODO: Рефакторинг метода (разбить на методы)

		$properties = array();
		$propXmlIds = array();
		$enumPropXmlIds = array();
		$arFields = unserialize($fields);

		// Соберем id всех, используемых в фильтрах свойств
		$propertyIds = array();
		foreach ($arFields['filters'] as $filter)
		{
			$filterRows = explode(',', $filter['filter_rows']);
			foreach ($filterRows as $filterRow)
			{
				if (static::isIbPropertyFilterRow($filterRow))
				{
					$propertyIds[] = static::getIbPropertyIdByFilterRow($filterRow);
				}
			}
		}
		$propertyIds = array_unique($propertyIds);

		// Даннные свойств из БД
		$dbRes = \CIBlockProperty::GetList();
		while ($prop = $dbRes->Fetch())
		{
			$propertyId = $prop['ID'];
			if (in_array($propertyId, $propertyIds))
			{
				$properties[$propertyId]['DATA'] = $prop;
			}
		}

		// Получаем xml_id свойств
		foreach ($propertyIds as $propertyId)
		{
			$propertyIdObj = Property::getInstance()->createId($propertyId);
			$propertyXmlId = Property::getInstance()->getXmlId($propertyIdObj);

			$properties[$propertyId]['PROPERTY_OBJECT'] = $propertyIdObj;
			$properties[$propertyId]['PROPERTY_XML_ID'] = $propertyXmlId;
		}

		// Заменяем id свойств на xml_id в исходных фильтрах
		foreach ($arFields['filters'] as &$filter)
		{
			// Заменяем свойства в filter_rows (используемые поля фильтра)
			$arFilterRows = explode(',', $filter['filter_rows']);
			foreach	($arFilterRows as &$filterRow)
			{
				if (static::isIbPropertyFilterRow($filterRow))
				{
					$propertyId = static::getIbPropertyIdByFilterRow($filterRow);
					$propertyXmlId = $properties[$propertyId]['PROPERTY_XML_ID'];
					$filterRow = static::PROPERTY_FIELD_PREFIX . $propertyXmlId;
				}
			}
			unset($filterRow);
			$filter['filter_rows'] = implode(',', $arFilterRows);

			// Заменяем свойства в fields (значения полей фильтра)
			$newFields = array();
			foreach	($filter['fields'] as $fieldName => $fieldVal)
			{
				$newFieldName = $fieldName;
				if (static::isIbPropertyFilterRow($fieldName))
				{
					$propertyId = static::getIbPropertyIdByFilterRow($fieldName);
					$propertyData = $properties[$propertyId]['DATA'];
					$propertyXmlId = $properties[$propertyId]['PROPERTY_XML_ID'];
					$propXmlIds[] = $propertyXmlId;

					// Новое название свойства
					$newFieldName = static::PROPERTY_FIELD_PREFIX . $propertyXmlId;

					// Новое значение для списочного свойства (заменить id значений на xml_id)
					if ($propertyData['PROPERTY_TYPE'] === 'L')
					{
						if ($propertyData['MULTIPLE'] === 'Y')
						{
							foreach ($fieldVal as &$fieldValId)
							{
								$enumPropId = Enum::getInstance()->createId($fieldValId);
								$enumPropXmlId = Enum::getInstance()->getXmlId($enumPropId);
								if ($enumPropXmlId)
								{
									$fieldValId = $enumPropXmlId;
									$enumPropXmlIds[] = $enumPropXmlId;
								}
							}
						}
						else
						{
							$enumPropId = Enum::getInstance()->createId($fieldVal);
							$enumPropXmlId = Enum::getInstance()->getXmlId($enumPropId);
							if ($enumPropXmlId)
							{
								$fieldVal = $enumPropXmlId;
								$enumPropXmlIds[] = $enumPropXmlId;
							}
						}
					}
				}

				$newFields[$newFieldName] = $fieldVal;
			}
			$filter['fields'] = $newFields;
		}
		$record->setFieldRaw('FIELDS', serialize($arFields));

		if ($enumPropXmlIds)
		{
			$enumPropXmlIds = array_unique($enumPropXmlIds);
			$dependency = clone $this->getDependency('PROPERTY_ENUM_ID');
			$dependency->setValues($enumPropXmlIds);
			$record->setDependency('PROPERTY_ENUM_ID', $dependency);
		}

		if ($propXmlIds)
		{
			$propXmlIds = array_unique($propXmlIds);
			$dependency = clone $this->getDependency('PROPERTY_ID');
			$dependency->setValues($propXmlIds);
			$record->setDependency('PROPERTY_ID', $dependency);
		}
	}

	public function setRecordDependencies(Record $record, array $filter)
	{
		//IBLOCK_ID
		if ($filter['NAME'])
		{
			$iblockId = $this->getIblockIdByFilterName($filter['NAME']);
			if ($iblockId)
			{
				$iblockIdObj = MigratoIblock::getInstance()->createId($iblockId);
				$iblockXmlId = MigratoIblock::getInstance()->getXmlId($iblockIdObj);

				$dependency = clone $this->getDependency('IBLOCK_ID');
				$dependency->setValue($iblockXmlId);
				$record->setDependency('IBLOCK_ID', $dependency);
			}
		}
	}

	/**
	 * Возвращает id ИБ по названию настройки фильтра.
	 *
	 * @param string $filterName название настройки фильтра.
	 *
	 * @return string id ИБ.
	 */
	protected function getIblockIdByFilterName($filterName)
	{
		$iblock = static::getIblockByFilterName($filterName);
		return $iblock['ID'] ?: '';
	}

	/**
	 * Возвращает тип ИБ по названию настройки фильтра.
	 *
	 * @param string $filterName название настройки фильтра.
	 *
	 * @return string тип ИБ.
	 */
	protected function getIblockTypeByFilterName($filterName)
	{
		$iblock = static::getIblockByFilterName($filterName);
		return $iblock['IBLOCK_TYPE_ID'] ?: '';
	}

	/**
	 * Возвращает ИБ по названию настройки фильтра.
	 *
	 * @param string $filterName название настройки фильтра.
	 *
	 * @return array данные ИБ.
	 */
	protected function getIblockByFilterName($filterName)
	{
		if (Loader::includeModule('iblock'))
		{
			$type = $this->getFilterTypeByName($filterName);
			$prefix = static::FILTER_NAME_PREFIXES[$type];
			if ($prefix)
			{
				$hash = substr($filterName, strlen($prefix));

				$res = \CIBlock::GetList();
				while ($iblock = $res->Fetch())
				{
					if (md5($iblock['IBLOCK_TYPE_ID'] . '.' . $iblock['ID']) == $hash)
					{
						return $iblock;
					}
				}
			}
		}
		return array();
	}

	/**
	 * @param array $filter - filter fields
	 * @return string
	 */
	protected function getXmlIdFromArray(array $filter)
	{
		$result = '';
		$iblockId = $this->getIblockIdByFilterName($filter['NAME']);
		if ($iblockId)
		{
			$iblockXmlId = MigratoIblock::getInstance()->getXmlId(MigratoIblock::getInstance()->createId($iblockId));
			if ($iblockXmlId)
			{
				$filterType = $this->getFilterTypeByName($filter['NAME']);
				$result = (
					$filterType
					. static::XML_ID_SEPARATOR .
					($filter['USER_ID'] == 1 ? 'Y' : 'N')
					. static::XML_ID_SEPARATOR .
					$filter['COMMON']
					. static::XML_ID_SEPARATOR .
					str_replace('.', '_', $filter['CATEGORY'])
					. static::XML_ID_SEPARATOR .
					md5($filter['NAME'])
					. static::XML_ID_SEPARATOR
					. $iblockXmlId
				);
			}
		}
		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 *
	 * @return string
	 */
	public function getXmlId($id)
	{
		$dbRes = CUserOptions::GetList(array(), array('ID' => $id->getValue()));
		if ($filter = $dbRes->Fetch())
		{
			return $this->getXmlIdFromArray($filter);
		}
		return '';
	}

	/**
	 * @param string $xmlId
	 * @return array
	 */
	protected function getArrayFromXmlId($xmlId)
	{
		$filterCategoryIndex = 3;
		$filterPrefixIndex = 0;

		$xmlFields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$xmlFields[$filterPrefixIndex] = static::FILTER_NAME_PREFIXES[$xmlFields[$filterPrefixIndex]];
		$xmlFields[$filterCategoryIndex] = str_replace('_', '.', $xmlFields[$filterCategoryIndex]);
		return $xmlFields;
	}

	/**
	 * Возвращает тип настройки фильтра по имени фильтра $filterName.
	 *
	 * @param string $filterName название фильтра (поле NAME настройки фильтра).
	 *
	 * @return string тип настройки - ключ массива FILTER_NAME_PREFIXES.
	 */
	protected function getFilterTypeByName($filterName)
	{
		$type = '';
		foreach (static::FILTER_NAME_PREFIXES as $key => $tableName)
		{
			if (strpos($filterName, $tableName) === 0)
			{
				$type = $key;
			}
		}
		return $type;
	}

	/**
	 * Проверяет, является ли $option настройкой фильтра:
	 *  - поле CATEGORY должно соответствовать одному из FILTER_CATEGORIES
	 * 	- префикс поля NAME должен соответствовать одному на FILTER_NAME_PREFIXES.
	 *
	 * @param array $option настройка.
	 *
	 * @return bool true, если $option - настройка фильтра, иначе - false.
	 */
	protected function isFilter(array $option)
	{
		return static::isFilterCategory($option['CATEGORY'])
			   && static::isFilterName($option['NAME']);
	}

	/**
	 * Проверяет, является ли категория настройки $optionCategory
	 * категорией настроек фильтра.
	 *
	 * Необходим для проверки принадлежности к настройке фильтра.
	 *
	 * @param string $optionCategory категория настройки (поле CATEGORY настройки).
	 *
	 * @return bool true, если $optionCategory - категория настроек фильтра, иначе - false.
	 */
	protected function isFilterCategory($optionCategory)
	{
		return in_array($optionCategory, static::FILTER_CATEGORIES);
	}

	/**
	 * Проверяет, является ли название настройки $optionName
	 * названием настроек фильтра.
	 *
	 * Необходим для проверки принадлежности к настройке фильтра.
	 *
	 * @param string $optionName название настройки (поле NAME настройки).
	 *
	 * @return bool true, если $optionName - название настроек фильтра, иначе - false.
	 */
	protected function isFilterName($optionName)
	{
		foreach (static::FILTER_NAME_PREFIXES as $filterNamePrefix)
		{
			if (strpos($optionName, $filterNamePrefix) === 0)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Проверяет, что название поля фильтра $filterRowName, является
	 * свойством элемента ИБ.
	 *
	 * @param string $filterRowName название поля фильтра.
	 *
	 * @return bool true, если $filterRowName - свойство элемента ИБ, иначе - false.
	 */
	protected function isIbPropertyFilterRow($filterRowName)
	{
		return strpos($filterRowName, static::PROPERTY_FIELD_PREFIX) === 0;
	}

	/**
	 * Возвращает id свойства элемента ИБ по названию поля фильтра.
	 *
	 * @param string $filterRowName название поля фильтра.
	 *
	 * @return string id свойства элемента ИБ.
	 */
	protected function getIbPropertyIdByFilterRow($filterRowName)
	{
		return substr($filterRowName, strlen(static::PROPERTY_FIELD_PREFIX)) ?: '';
	}
}