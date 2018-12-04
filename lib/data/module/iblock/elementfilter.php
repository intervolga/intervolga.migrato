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
	/**
	 * Символ-разделитель логических блоков в строке с xmlId.
	 */
	const XML_ID_SEPARATOR = '.';

	/**
	 * Регулярное выражения для определения, является ли поле фильтра - фильтром свойства элемента ИБ.
	 */
	const IB_PROPERTY_NAME_REGEX = '/(.*)PROPERTY_(\d+)(.*)/';

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
					$record = $this->createRecordFromArray($option);
					$result[] = $record;
				}
			}
		}

		return $result;
	}

	/**
	 * @param Record $record
	 *
	 * @throws \Exception
	 */
	public function update(Record $record)
	{
		$filterId = 0;
		if ($record->getId())
		{
			$filterId = $this->saveFilterFromRecord($record);
		}

		if (!$filterId)
		{
			$exceptionMessage = ExceptionText::getFromString(
				Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ELEMENT_FILTER.UPDATE_ERROR')
			);
			throw new \Exception($exceptionMessage);
		}
	}

	/**
	 * @param Record $record
	 *
	 * @return RecordId
	 * @throws \Exception
	 */
	protected function createInner(Record $record)
	{
		$filterId = $this->saveFilterFromRecord($record);
		if ($filterId)
		{
			return $this->createId($filterId);
		}

		$exceptionMessage = ExceptionText::getFromString(
			Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ELEMENT_FILTER.ADD_ERROR')
		);
		throw new \Exception($exceptionMessage);
	}

	/**
	 * @param RecordId $id
	 *
	 * @throws \Exception
	 */
	protected function deleteInner(RecordId $id)
	{
		$success = false;

		$dbRes = CUserOptions::GetList(array(), array('ID' => $id->getValue()));
		if ($filter = $dbRes->Fetch())
		{
			$success = CUserOptions::DeleteOptionsByName($filter['CATEGORY'], $filter['NAME']);
		}

		if (!$success)
		{
			$exceptionMessage = ExceptionText::getFromString(
				Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ELEMENT_FILTER.DELETE_ERROR')
			);
			throw new \Exception($exceptionMessage);
		}
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

		// Данные инфблока
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
	 * Создает запись миграции фильтра
	 * на основе $option.
	 * 
	 * @param array $option массив данных фильтра.
	 *
	 * @return Record запись миграции фильтра.
	 */
	protected function createRecordFromArray(array $option)
	{
		$record = new Record($this);
		$record->setId($this->createId($option['ID']));
		$record->setXmlId($this->getXmlIdFromArray($option));
		$record->setFieldRaw('COMMON', $option['COMMON']);
		$record->setFieldRaw('CATEGORY', $option['CATEGORY']);
		$this->addPropertiesDependencies($record, $option['VALUE']);
		$this->setRecordDependencies($record, $option);
		
		return $record;
	}

	/**
	 * Создает массив данных фильтра
	 * на основе $record.
	 *
	 * @param Record $record запись миграции фильтра.
	 *
	 * @return string[] массив данных фильтра.
	 */
	protected function createArrayFromRecord(Record $record)
	{
		$arFilter = $record->getFieldsRaw();
		$xmlIdFields = $this->getArrayFromXmlId($record->getXmlId());
		$iblockInfo = $this->getIblockByXmlId($xmlIdFields[5]);

		// Формируем поля фильтра
		$arFilter['COMMON'] = $arFilter['COMMON'] === 'Y';
		$arFilter['USER_ID'] = ($xmlIdFields[1] === 'Y') ? 1 : 0;
		$arFilter['FIELDS'] = $this->convertFieldsFromXml(unserialize($arFilter['FIELDS']));
		if ($iblockInfo)
		{
			$arFilter['NAME'] = $xmlIdFields[0] . md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockInfo['ID']);
		}

		return $arFilter;
	}
	
	protected function addPropertiesDependencies(Record $record, $fields)
	{
		$propXmlIds = array();
		$enumPropXmlIds = array();

		$arFields = $this->convertFieldsToXml(unserialize($fields), $propXmlIds, $enumPropXmlIds);
		$record->setFieldRaw('FIELDS', serialize($arFields));

		// Зависимости от сторонней сущности: значения списочных свойства ИБ
		if ($enumPropXmlIds)
		{
			$enumPropXmlIds = array_unique($enumPropXmlIds);
			$dependency = clone $this->getDependency('PROPERTY_ENUM_ID');
			$dependency->setValues($enumPropXmlIds);
			$record->setDependency('PROPERTY_ENUM_ID', $dependency);
		}

		// Зависимости от сторонней сущности: свойства ИБ
		if ($propXmlIds)
		{
			$propXmlIds = array_unique($propXmlIds);
			$dependency = clone $this->getDependency('PROPERTY_ID');
			$dependency->setValues($propXmlIds);
			$record->setDependency('PROPERTY_ID', $dependency);
		}
	}

	/**
	 * Добавляет в запись миграции $record
	 * зависимости от сторонных сущностей.
	 *
	 * @param Record $record запись миграции фильтра.
	 * @param array $filter массив данных фильтра.
	 */
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
	 * Формирует настройку фильтра из $record и сохраняет ее в БД.
	 *
	 * @param Record $record запись миграции, получаемая на входе методов update(), createInner().
	 *
	 * @return int id сохраненной настройки фильтра или 0.
	 */
	protected function saveFilterFromRecord(Record $record)
	{
		$arFilter = $this->createArrayFromRecord($record);

		// Сохраняем фильтр
		$filterId = 0;
		$success = false;
		if ($arFilter['CATEGORY'] && $arFilter['NAME'] && $arFilter['FIELDS'])
		{
			$success = CUserOptions::SetOption(
				$arFilter['CATEGORY'],
				$arFilter['NAME'],
				$arFilter['FIELDS'],
				$arFilter['COMMON'],
				$arFilter['USER_ID']
			);
		}

		// Проверяем успешность сохранения фильтра
		if ($success)
		{
			$option = CUserOptions::GetList(
				array(),
				array(
					'CATEGORY' => $arFilter['CATEGORY'],
					'NAME' => $arFilter['NAME'],
					'USER_ID' => $arFilter['USER_ID']
				)
			)->Fetch();

			$filterId = $option['ID'] ?: 0;
		}

		return $filterId;
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
	 * Возвращает ИБ по его xmlId.
	 *
	 * @param string $iblockXmlId xmlId ИБ.
	 *
	 * @return array данные ИБ.
	 */
	protected function getIblockByXmlId($iblockXmlId)
	{
		$iblockInfo = array();
		if (Loader::includeModule('iblock'))
		{
			$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
			$iblockInfo = \CIBlock::GetByID($iblockId)->GetNext();
		}

		return $iblockInfo;
	}

	/**
	 * Возвращает свойства ИБ из БД.
	 *
	 * @return array массив свойств ИБ.
	 */
	protected function getIbProperties()
	{
		$dbProperties = array();
		$dbRes = \CIBlockProperty::GetList();
		while ($prop = $dbRes->Fetch())
		{
			$dbProperties[$prop['ID']] = $prop;
		}

		return $dbProperties;
	}

	/**
	 * Возвращает свойства ИБ из БД, отфильтрованные по id.
	 *
	 * @param array $ibPropertyIds массив id свойств ИБ.
	 *
	 * @return array массив свойств ИБ.
	 */
	protected function getIbPropertiesById(array $ibPropertyIds)
	{
		$dbProperties = array();
		$dbRes = \CIBlockProperty::GetList();
		while ($prop = $dbRes->Fetch())
		{
			if (in_array($prop['ID'], $ibPropertyIds))
			{
				$dbProperties[$prop['ID']] = $prop;
			}
		}

		return $dbProperties;
	}

	/**
	 * Возвращает xmlId фильтра
	 * на основе $filter.
	 *
	 * @param array $filter массив данных фильтра.
	 *
	 * @return string xmlId фильтра.
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
	 * Возвращает массив данных фильтра
	 * на основе $xmlId.
	 *
	 * @param string $xmlId xmlId фильтра.
	 *
	 * @return array массив данных фильтра.
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
		$isMatch = false;
		$matches = array();

		$this->testStringAgainstIbPropertyRegex($filterRowName, $isMatch, $matches);

		return ($isMatch && $matches[2]);
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
		$isMatch = false;
		$matches = array();

		$this->testStringAgainstIbPropertyRegex($filterRowName, $isMatch, $matches);

		return ($isMatch && $matches[2]) ? $matches[2] : '';
	}

	/**
	 * Возвращает id свойств ИБ, используемых в полях фильтра $filterFields.
	 *
	 * @param array $filterFields поля фильтра.
	 *
	 * @return array id свойств ИБ, используемых в фильтре.
	 */
	protected function getIbPropertiesUsedInFilter(array $filterFields)
	{
		$propertyIds = array();
		foreach ($filterFields['filters'] as $filter)
		{
			$filterRows = explode(',', $filter['filter_rows']);
			foreach ($filterRows as $filterRow)
			{
				$isMatch = false;
				$matches = array();

				$this->testStringAgainstIbPropertyRegex($filterRow, $isMatch, $matches);
				if ($isMatch && $matches[2])
				{
					$propertyIds[] = $matches[2];
				}
			}
		}

		return array_unique($propertyIds);
	}

	/**
	 * Проверяет строку $string на соответсвие регулярному выражению
	 * для названия фильтра свойства элемента ИБ.
	 *
	 * @param string $string проверяемая строка.
	 * @param bool $isMatch признак соответствия проверяемой строки регулярному выражению.
	 * @param array $matches массив совпадений.
	 */
	protected function testStringAgainstIbPropertyRegex($string, &$isMatch, &$matches)
	{
		$isMatch = preg_match(static::IB_PROPERTY_NAME_REGEX, $string, $matches);
	}

	/**
	 * Конвертирует поля фильтра в формат (xml), пригодный для выгрузки.
	 *
	 * @param array $filterFields поля фильтра.
	 * @param array $propXmlIds xmlId свойств ИБ, которые были сконвертированы.
	 * @param array $enumPropXmlIds xmlId значений списочных свойств ИБ, которые были сконвертированы.
	 *
	 * @return array поля фильтра в форме, пригодном для выгрузки.
	 */
	protected function convertFieldsToXml(array $filterFields, array &$propXmlIds, array &$enumPropXmlIds)
	{
		$propertyIds = $this->getIbPropertiesUsedInFilter($filterFields);
		$properties = $this->getIbPropertiesById($propertyIds);

		// Получаем xml_id свойств
		foreach ($propertyIds as $propertyId)
		{
			$propertyIdObj = Property::getInstance()->createId($propertyId);
			$propertyXmlId = Property::getInstance()->getXmlId($propertyIdObj);

			$properties[$propertyId]['PROPERTY_OBJECT'] = $propertyIdObj;
			$properties[$propertyId]['PROPERTY_XML_ID'] = $propertyXmlId;
		}

		// Массив вида: <property_id> => <property_xml_id>
		$propertiesXmlId = array_map(function ($property) {
			return $property['PROPERTY_XML_ID'];
		}, $properties);

		// Конвертируем данные фильтров
		foreach ($filterFields['filters'] as &$filter)
		{
			/**
			 * Конвертируем названия свойств в подмассиве filter_rows
			 * из PROPERTY_<property_id> в PROPERTY_<property_xml_id>
			 */
			$arFilterRows = explode(',', $filter['filter_rows']);
			foreach	($arFilterRows as &$filterRow)
			{
				$this->convertFilterRowToXml($filterRow, $propertiesXmlId);
			}
			unset($filterRow);
			$filter['filter_rows'] = implode(',', $arFilterRows);

			/**
			 * Конвертируем названия свойств в подмассиве fields
			 * из PROPERTY_<property_id> в PROPERTY_<property_xml_id>, а также
			 * значения списочных свойств из <enum_property_id> в <enum_property_xml_id>
			 */
			$newFields = array();
			foreach	($filter['fields'] as $fieldName => $fieldVal)
			{
				$newFieldName = $fieldName;
				if (static::isIbPropertyFilterRow($fieldName))
				{
					$propertyId = static::getIbPropertyIdByFilterRow($fieldName);
					$propertyData = $properties[$propertyId];
					$propertyXmlId = $propertyData['PROPERTY_XML_ID'];
					$propXmlIds[] = $propertyXmlId;

					// Название
					$this->convertFilterRowToXml($newFieldName, $propertiesXmlId);

					// Значение списочных свойств
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

		return $filterFields;
	}

	/**
	 * Конвертирует название поля фильтра в формат (xml), пригодный для выгрузки.
	 *
	 * @param string $filterRow название поля фильтра
	 * @param array $propertiesXmlId массив с xml_id свойств.
	 */
	protected function convertFilterRowToXml(&$filterRow, $propertiesXmlId)
	{
		$isMatch = false;
		$matches = array();

		$this->testStringAgainstIbPropertyRegex($filterRow, $isMatch, $matches);

		if ($isMatch && $matches[2])
		{
			$filterRowPrefix = $matches[1];
			$filterRowPostfix = $matches[3];
			$propertyId = $matches[2];

			$propertyXmlId = $propertiesXmlId[$propertyId];

			$filterRow = static::PROPERTY_FIELD_PREFIX . $propertyXmlId;
			if ($filterRowPrefix)
			{
				$filterRow = $filterRowPrefix . $filterRow;
			}
			if ($filterRowPostfix)
			{
				$filterRow = $filterRow . $filterRowPostfix;
			}
		}
	}

	/**
	 * Конвертирует поля фильтра в формат, пригодный для сохранения в БД.
	 *
	 * @param array $filterFields поля фильтра.
	 *
	 * @return array поля фильтра в формате, пригодном для сохранения в БД.
	 */
	protected function convertFieldsFromXml(array $filterFields)
	{
		$dbProperties = $this->getIbProperties();

		// Конвертируем данные фильтров
		$filterIbProperties = array();
		foreach ($filterFields['filters'] as &$filter)
		{
			/**
			 * Конвертируем названия свойств в подмассиве filter_rows
			 * из PROPERTY_<property_xml_id> в PROPERTY_<property_id>
			 */
			$filterRows = explode(',', $filter['filter_rows']);
			foreach	($filterRows as &$filterRowName)
			{
				$propXmlId = $this->getIbPropertyIdByFilterRow($filterRowName);
				if ($propXmlId)
				{
					$propId = $filterIbProperties[$propXmlId] ?:
						Property::getInstance()->findRecord($propXmlId);

					if ($propId)
					{
						$filterIbProperties[$propXmlId] = $propId;
						$filterRowName = static::PROPERTY_FIELD_PREFIX . $propId->getValue();
					}
				}
			}
			$filter['filter_rows'] = implode(',', $filterRows);
			unset($filterRowName);

			/**
			 * Конвертируем названия свойств в подмассиве fields
			 * из PROPERTY_<property_xml_id> в PROPERTY_<property_id>, а также
			 * значения списочных свойств из <enum_property_xml_id> в <enum_property_id>
			 */
			$newFilterFields = array();
			foreach	($filter['fields'] as $filterRowName => $filterRowValue)
			{
				$newFilterRowName = $filterRowName;
				$newFilterRowValue = $filterRowValue;

				$propXmlId = $this->getIbPropertyIdByFilterRow($newFilterRowName);
				if ($propXmlId)
				{
					$propId = $filterIbProperties[$propXmlId] ?:
						Property::getInstance()->findRecord($propXmlId);

					// Название
					$newFilterRowName = static::PROPERTY_FIELD_PREFIX . $propId->getValue();

					// Значение списочных свойств
					$propertyData = $dbProperties[$propId->getValue()];
					if ($propertyData['PROPERTY_TYPE'] === 'L')
					{
						if ($propertyData['MULTIPLE'] === 'Y')
						{
							foreach ($newFilterRowValue as &$filterRowValueId)
							{
								$filterRowValueId = Enum::getInstance()->findRecord($filterRowValueId)->getValue();
							}
						}
						else
						{
							$newFilterRowValue = Enum::getInstance()->findRecord($newFilterRowValue)->getValue();
						}
					}
				}

				$newFilterFields[$newFilterRowName] = $newFilterRowValue;
			}
			$filter['fields'] = $newFilterFields;
		}

		return $filterFields;
	}
}