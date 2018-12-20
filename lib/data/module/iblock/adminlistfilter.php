<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use CUserTypeEntity;
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
 * Class AdminListFilter - фильтры на админ. страницах списка элементов и разделов инфоблока.
 *
 * В рамках текущей сущности:
 *  - таблица БД - b_user_option,
 *  - настройка - запись таблицы БД,
 *  - название настройки - поле NAME настройки,
 *  - категория настройки - поле CATEGORY настройки,
 *
 * Название настройки фильтра: <FILTER_NAME_PREFIX><HASH> , где:
 *    - <FILTER_NAME_PREFIX> - одно из значений массива FILTER_NAME_PREFIXES,
 *    - <HASH> - md5(IBLOCK_TYPE_ID + "." + IBLOCK_ID)
 *
 * @package Intervolga\Migrato\Data\Module\Iblock
 */
class AdminListFilter extends BaseData
{
	/**
	 * Символ-разделитель логических блоков в строке с xmlId.
	 */
	const XML_ID_SEPARATOR = '.';

	/**
	 * REGEX: имя фильтра
	 */
	const FILTER_NAME_REGEX = '/^([a-z_]+_)(.+)$/';

	/**
	 * REGEX: название фильтра свойства ИБ
	 */
	const IB_PROPERTY_NAME_REGEX = '/^([^_]*_?)PROPERTY_([^_\s]+)(_?.*)$/';

	/**
	 * REGEX: название фильтра UF-поля
	 */
	const UF_NAME_REGEX = '/^(UF_[A-Z0-9_]+)(_?.*)$/';

	/**
	 * Префиксы названия фильтров.
	 * После префиксов в названиях идет <hash>:
	 * filter_name=<prefix><hash>
	 *
	 * IB_MIXED_LIST - список элементов и разделов ИБ              ( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 * PRODUCTS_MIXED_LIST - список товаров и разделов каталога    ( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 *
	 * IB_SECTION_LIST - список разделов ИБ                        ( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 * PRODUCTS_SECTION_LIST - список разделов каталога            ( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 *
	 * IB_ELEMENT_LIST - список элементов ИБ                       ( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 * PRODUCTS_LIST - список товаров                              ( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 *
	 * IB_LIST - список ИБ конкретного типа                        ( <hash> = md5(IBLOCK_TYPE_CODE) )
	 * IB_LIST_ADMIN - список ИБ конкретного типа (admin=Y)        ( <hash> = md5(IBLOCK_TYPE_CODE) )
	 *
	 * IB_PROPERTIES_LIST - список свойств ИБ                      ( <hash>=IBLOCK_ID )
	 */
	const FILTER_NAME_PREFIXES = array(
		'IB_MIXED_LIST' => 'tbl_iblock_list_',
		'PRODUCTS_MIXED_LIST' => 'tbl_product_list_',

		'IB_SECTION_LIST' => 'tbl_iblock_section_',
		'PRODUCTS_SECTION_LIST' => 'tbl_catalog_section_',


		'IB_ELEMENT_LIST' => 'tbl_iblock_element_',
		'PRODUCTS_LIST' => 'tbl_product_admin_',

		'IB_LIST' => 'tbl_iblock_',
		'IB_LIST_ADMIN' => 'tbl_iblock_admin_',

		'IB_PROPERTIES_LIST' => 'tbl_iblock_property_admin_'
	);

	/**
	 * Категории настроек фильтра.
	 */
	const FILTER_CATEGORIES = array(
		'PERSONAL' => 'main.ui.filter',
		'COMMON' => 'main.ui.filter.common',
	);

	/**
	 * Префикс свойства элемента ИБ в поле фильтра.
	 */
	const PROPERTY_FIELD_PREFIX = 'PROPERTY_';

	/**
	 * Префикс UF-поля в поле фильтра.
	 */
	const UF_FIELD_PREFIX = 'UF_';

	protected function configure()
	{
		Loader::includeModule('iblock');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ADMIN_LIST_FILTERS.ENTITY_NAME'));
		$this->setVirtualXmlId(true);
		$this->setFilesSubdir('/type/iblock/admin/');
		$this->setDependencies(array(
			'IBLOCK' => new Link(MigratoIblock::getInstance()),
			'IBLOCK_TYPE' => new Link(Type::getInstance()),
			'PROPERTY' => new Link(Property::getInstance()),
			'ENUM' => new Link(Enum::getInstance()),
			'FIELD' => new Link(Field::getInstance()),
			'FIELDENUM' => new Link(FieldEnum::getInstance()),
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

		foreach ($optionTypeFilters as $optionTypeFilter)
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
				Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ADMIN_LIST_FILTERS.UPDATE_ERROR')
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
			Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ADMIN_LIST_FILTERS.ADD_ERROR')
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
				Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ADMIN_LIST_FILTERS.DELETE_ERROR')
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
		$xmlIdFields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$filterUserId = $xmlIdFields[2];
		$filterName = $this->getFilterName($xmlId);
		$filterCommon = $filterUserId == 0;
		$filterCategory = $filterCommon ? static::FILTER_CATEGORIES['COMMON'] : static::FILTER_CATEGORIES['PERSONAL'];

		$filter = CUserOptions::GetList(
			array(),
			array(
				'CATEGORY' => $filterCategory,
				'NAME' => $filterName,
				'USER_ID' => $filterUserId
			)
		)->GetNext();

		return $filter['ID'] ? $this->createId($filter['ID']) : null;
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
		$this->setIblockDependency($record, $option);
		$this->setFilterValue($record, $option);

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
		$xmlIdFields = explode(static::XML_ID_SEPARATOR, $record->getXmlId());

		$arFilter['USER_ID'] = $xmlIdFields[2];
		$arFilter['NAME'] = $this->getFilterName($record->getXmlId());
		$arFilter['VALUE'] = $this->convertValueFromXml($arFilter);

		return $arFilter;
	}

	/**
	 * Добавляет в запись миграции $record поле VALUE фильтра.
	 * Предварительно поле конвертируется в формат (xml), пригодный для выгрузки.
	 * Также добавляются зависимости от xml_id сконвертированных данных.
	 *
	 * @param Record $record запись миграции настройки.
	 * @param array $filter данные фильтра.
	 */
	protected function setFilterValue(Record $record, array $filter)
	{
		$dependencies = array();

		$arFields = $this->convertValueToXml($filter, $dependencies);
		$record->setFieldRaw('VALUE', serialize($arFields));

		/**
		 * Зависимости от сторонних сущностей:
		 * - Списки свойств (ИБ)
		 * - Свойства (ИБ)
		 * - Списки UF-полей
		 * - UF-поля
		 */
		foreach ($dependencies as $dependencyName => $dependencyXmlIds)
		{
			if ($dependencyXmlIds)
			{
				$dependencyXmlIds = array_unique($dependencyXmlIds);
				$dependency = clone $this->getDependency($dependencyName);
				$dependency->setValues($dependencyXmlIds);
				$record->setDependency($dependencyName, $dependency);
			}
		}
	}

	/**
	 * Добавляет в запись миграции $record
	 * зависимость от ИБ или типа ИБ.
	 *
	 * @param Record $record запись миграции фильтра.
	 * @param array $filter массив данных фильтра.
	 */
	protected function setIblockDependency(Record $record, array $filter)
	{
		$dependencyXmlId = $this->getFilterIblockDependency($filter['NAME']);
		$dependencyName = $this->isFilterForIblock($filter['NAME']) ? 'IBLOCK' : 'IBLOCK_TYPE';

		if ($dependencyXmlId)
		{
			$dependency = clone $this->getDependency($dependencyName);
			$dependency->setValue($dependencyXmlId);
			$record->setDependency($dependencyName, $dependency);
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
		if ($arFilter['CATEGORY'] && $arFilter['NAME'] && $arFilter['VALUE'])
		{
			$success = CUserOptions::SetOption(
				$arFilter['CATEGORY'],
				$arFilter['NAME'],
				$arFilter['VALUE'],
				$arFilter['COMMON'] === 'Y',
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
					'USER_ID' => $arFilter['USER_ID'],
				)
			)->GetNext();

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
			$res = \CIBlock::GetList();
			while ($iblock = $res->Fetch())
			{
				if ($this->isFilterIblock($iblock, $filterName))
				{
					return $iblock;
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
			$iblockRecord = MigratoIblock::getInstance()->findRecord($iblockXmlId);
			if ($iblockRecord)
			{
				$iblockId = $iblockRecord->getValue();
				$iblockInfo = \CIBlock::GetByID($iblockId)->GetNext();
			}
		}

		return $iblockInfo;
	}

	/**
	 * Возвращает xml_id основной зависимости фильтра - зависимости от ИБ или типа ИБ.
	 *
	 * @param string $filterName название фильтра.
	 *
	 * @return string xml_id основной зависимости фильтра.
	 */
	protected function getFilterIblockDependency($filterName)
	{
		$dependencyXmlId = '';

		$iblock = $this->getIblockByFilterName($filterName);
		if ($iblock)
		{
			if ($this->isFilterForIblock($filterName))
			{
				$id = $iblock['ID'];
				$className = Iblock::class;
			}
			else
			{
				$id = $iblock['IBLOCK_TYPE_ID'];
				$className = Type::class;
			}

			/** @var BaseData $className */
			$idObj = $className::getInstance()->createId($id);
			$dependencyXmlId = $className::getInstance()->getXmlId($idObj);
		}

		return $dependencyXmlId;
	}

	/**
	 * Возвращает свойства ИБ из БД.
	 *
	 * @param int $iblockId id инфоблока для фильтрации (опционально).
	 *
	 * @return array массив свойств ИБ.
	 */
	protected function getIbProperties($iblockId = 0)
	{
		$filter = array();
		if ($iblockId)
		{
			$filter = array('IBLOCK_ID' => $iblockId);
		}

		$dbProperties = array();
		$dbRes = \CIBlockProperty::GetList(array(), $filter);
		while ($prop = $dbRes->Fetch())
		{
			$dbProperties[$prop['ID']] = $prop;
		}

		return $dbProperties;
	}

	/**
	 * Возвращает UF-поля из БД.
	 *
	 * @param string $entityId id сущности для фильтрации (опционально).
	 *
	 * @return array данные UF-полей.
	 */
	protected function getUfFields($entityId = '')
	{
		$filter = array();
		if ($entityId)
		{
			$filter['ENTITY_ID'] = $entityId;
		}

		$ufFields = array();
		$dbRes = CUserTypeEntity::GetList(array(), $filter);
		while ($ufField = $dbRes->Fetch())
		{
			$ufFields[$ufField['FIELD_NAME']] = $ufField;
		}

		return $ufFields;
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

		$dependencyXmlId = $this->getFilterIblockDependency($filter['NAME']);
		if ($dependencyXmlId)
		{
			$filterType = $this->getFilterType($filter['NAME']);
			$result = $filterType
				. static::XML_ID_SEPARATOR
				. $dependencyXmlId
				. static::XML_ID_SEPARATOR
				. $filter['USER_ID'];
		}

		return $result;
	}

	/**
	 * Возвращает тип фильтра.
	 *
	 * @param string $filterName название фильтра (поле NAME фильтра).
	 *
	 * @return string тип фильтра.
	 */
	protected function getFilterType($filterName)
	{
		$isMatch = preg_match(static::FILTER_NAME_REGEX, $filterName, $matches);
		if ($isMatch && $matches[1])
		{
			$prefix = $matches[1];
			$key = array_search($prefix, static::FILTER_NAME_PREFIXES);

			return $key !== false ? $key : '';
		}

		return '';
	}

	/**
	 * Возвращает хэш названия фильтра.
	 *
	 * @param string $filterName название фильтра (поле NAME фильтра).
	 *
	 * @return string хэш названия фильтра.
	 */
	protected function getFilterHash($filterName)
	{
		$isMatch = preg_match(static::FILTER_NAME_REGEX, $filterName, $matches);
		if ($isMatch && $matches[2])
		{
			return $matches[2];
		}

		return '';
	}

	/**
	 * Возвращает название фильтра по его xml_id.
	 *
	 * @param string $filterXmlId xml_id фильтра.
	 *
	 * @return string название фильтра.
	 */
	protected function getFilterName($filterXmlId)
	{
		$xmlIdFields = explode(static::XML_ID_SEPARATOR, $filterXmlId);
		$xmlId = $xmlIdFields[1];
		$filterType = $xmlIdFields[0];
		$filterTypePrefix = static::FILTER_NAME_PREFIXES[$filterType];

		if (in_array($filterType, $this->getIblockFilterTypes()))
		{
			/** @var RecordId $iblockRecord */
			$iblockRecord = MigratoIblock::getInstance()->findRecord($xmlId);
			if ($iblockRecord)
			{
				$iblockInfo = \CIBlock::GetByID($iblockRecord->getValue())->GetNext();
				if ($filterType === 'IB_PROPERTIES_LIST')
				{
					return $filterTypePrefix . $iblockInfo['ID'];
				}
				else
				{
					return $filterTypePrefix . md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockInfo['ID']);
				}
			}
		}
		else
		{
			/** @var RecordId $iblockTypeRecord */
			$iblockTypeRecord = Type::getInstance()->findRecord($xmlId);
			if ($iblockTypeRecord)
			{
				$iblockTypeInfo = \CIBlockType::GetByID($iblockTypeRecord->getValue())->GetNext();
				return $filterTypePrefix . md5($iblockTypeInfo['ID']);
			}
		}

		return '';
	}

	/**
	 * Возвращает типы фильтров для ИБ.
	 * <hash> таких фильтров содержит id ИБ.
	 *
	 * @return array типы фильтров для ИБ.
	 */
	protected function getIblockFilterTypes()
	{
		$filterTypes = array_keys(static::FILTER_NAME_PREFIXES);
		$iblockTypeFilterTypes = array('IB_LIST_ADMIN', 'IB_LIST');

		return array_diff($filterTypes, $iblockTypeFilterTypes);
	}

	/**
	 * Возвращает типы фильтров, поле VALUE которых подлежит конвертации в формат выгрузки (xml).
	 *
	 * @return array типы конвертируемых фильтров.
	 */
	protected function getConvertibleFilterTypes()
	{
		$filterTypes = array_keys(static::FILTER_NAME_PREFIXES);
		$nonConvertibleFilterTypes = array('IB_LIST_ADMIN', 'IB_LIST', 'IB_PROPERTIES_LIST');

		return array_diff($filterTypes, $nonConvertibleFilterTypes);
	}

	/**
	 * Проверяет, является ли фильтр $filter конвертируемым.
	 *
	 * @param array $filter данные фильтра.
	 *
	 * @return bool true, если фильтр конвертируемый, иначе - false.
	 */
	protected function isConvertibleFilter(array $filter)
	{
		$filterType = $this->getFilterType($filter['NAME']);
		$convertibleFilterTypes = $this->getConvertibleFilterTypes();

		return in_array($filterType, $convertibleFilterTypes);
	}

	/**
	 * Проверяет, привязан ли фильтр к определенному ИБ
	 * (если нет, то привязан к типу ИБ)
	 *
	 * @param string $filterName название фильтра.
	 *
	 * @return bool true, если фильтр привязан к ИБ, иначе - false.
	 */
	protected function isFilterForIblock($filterName)
	{
		$filterType = $this->getFilterType($filterName);

		return ($filterType === 'IB_LIST_ADMIN' || $filterType === 'IB_LIST')
			? false
			: true;
	}

	/**
	 * Проверяет, принадлежит ли фильтр $filterName инфоблоку $iblockInfo.
	 * Проверка происходит по закодированному в названиии фильтра <hash>.
	 *
	 * @param array $iblockInfo данные ИБ (ID и IBLOCK_TYPE_ID).
	 * @param string $filterName название фильтра.
	 *
	 * @return bool true, если фильтр принадлежит ИБ, иначе - false.
	 */
	protected function isFilterIblock(array $iblockInfo, $filterName)
	{
		$iblockId = $iblockInfo['ID'];
		$iblockTypeCode = $iblockInfo['IBLOCK_TYPE_ID'];
		$hash = $this->getFilterHash($filterName);
		$type = $this->getFilterType($filterName);

		if ($hash && $type)
		{
			switch ($type)
			{
				case 'IB_MIXED_LIST':
				case 'PRODUCTS_MIXED_LIST':
				case 'IB_SECTION_LIST':
				case 'PRODUCTS_SECTION_LIST':
				case 'IB_ELEMENT_LIST':
				case 'PRODUCTS_LIST':
				{
					return md5($iblockTypeCode . '.' . $iblockId) === $hash;
				}
				break;
				case 'IB_LIST':
				case 'IB_LIST_ADMIN':
				{
					return md5($iblockTypeCode) === $hash;
				}
				break;
				case 'IB_PROPERTIES_LIST':
				{
					return $iblockId === $hash;
				}
				break;
				default:
				{
					return false;
				}
				break;
			}
		}

		return false;
	}

	/**
	 * Проверяет, является ли $option настройкой фильтра:
	 *  - поле CATEGORY должно соответствовать одному из FILTER_CATEGORIES
	 *    - префикс поля NAME должен соответствовать одному на FILTER_NAME_PREFIXES.
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
	 * @return bool true, если $filterRowName - фильтр свойства элемента ИБ, иначе - false.
	 */
	protected function isIbPropertyFilterRow($filterRowName)
	{
		$isMatch = false;
		$matches = array();

		$this->testStringAgainstIbPropertyRegex($filterRowName, $isMatch, $matches);

		return ($isMatch && $matches[2]);
	}

	/**
	 * Проверяет, что название поля фильтра $filterRowName, является
	 * UF-полем.
	 *
	 * @param string $filterRowName название поля фильтра.
	 *
	 * @return bool true, если $filterRowName - фильтр UF-поля, иначе - false.
	 */
	protected function isUfFilterRow($filterRowName)
	{
		$length = strlen(static::UF_FIELD_PREFIX);

		return (substr($filterRowName, 0, $length) === static::UF_FIELD_PREFIX);
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
	 * Возвращает название UF-поля по названию поля фильтра.
	 *
	 * @param string $filterRowName название поля фильтра.
	 *
	 * @return string название UF-поля.
	 */
	protected function getUfNameByFilterRow($filterRowName)
	{
		$ufName = '';

		$isMatch = preg_match(static::UF_NAME_REGEX, $filterRowName, $matches);
		if ($isMatch && $matches[1])
		{
			$ufName = rtrim($matches[1], '_');
		}

		return $ufName;
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
	 * Проверяет строку $string на соответсвие регулярному выражению
	 * для названия фильтра UF-полей.
	 *
	 * @param string $string проверяемая строка.
	 * @param bool $isMatch признак соответствия проверяемой строки регулярному выражению.
	 * @param array $matches массив совпадений.
	 */
	protected function testStringAgainstUFRegex($string, &$isMatch, &$matches)
	{
		$isMatch = preg_match(static::UF_NAME_REGEX, $string, $matches);
	}

	/**
	 * Конвертирует поля фильтра в формат (xml), пригодный для выгрузки.
	 *
	 * @param array $filterData данные фильтра.
	 * @param array $dependencies xmlId зависимостей сущности.
	 *
	 * @return array поля фильтра в форме, пригодном для выгрузки.
	 */
	protected function convertValueToXml(array $filterData, array &$dependencies)
	{
		$filterFields = unserialize($filterData['VALUE']);

		if (!$this->isConvertibleFilter($filterData))
		{
			return $filterFields;
		}

		$iblockId = $this->getIblockIdByFilterName($filterData['NAME']);
		$propertyIds = $this->getIbPropertiesUsedInFilter($filterFields);
		$properties = $this->getIbPropertiesById($propertyIds);
		$ufFields = $this->getUfFields('IBLOCK_' . $iblockId . '_SECTION');

		// Конвертируем данные фильтров
		foreach ($filterFields['filters'] as &$filter)
		{
			// Конвертируем названия Свойств в подмассиве filter_rows
			$arFilterRows = explode(',', $filter['filter_rows']);
			foreach ($arFilterRows as &$filterRow)
			{
				if ($this->isIbPropertyFilterRow($filterRow))
				{
					$this->convertIbPropertyFilterRowToXml($filterRow);
				}
			}
			unset($filterRow);
			$filter['filter_rows'] = implode(',', $arFilterRows);


			// Конвертируем названия Свойств, Списков свойств, Списков UF-полей в подмассиве fields,
			$newFields = array();
			foreach ($filter['fields'] as $fieldName => $fieldVal)
			{
				$newFieldName = $fieldName;
				$newFieldVal = $fieldVal;

				if ($this->isIbPropertyFilterRow($newFieldName))
				{
					// Конвертируем название поля фильтра
					$propertyXmlId = $this->convertIbPropertyFilterRowToXml($newFieldName);

					// Конфертируем значение поля фильтра
					if ($propertyXmlId)
					{
						$propertyId = static::getIbPropertyIdByFilterRow($fieldName);
						$propertyData = $properties[$propertyId];
						$dependencies['PROPERTY'][] = $propertyXmlId;

						// Значение списочных свойств
						if ($propertyData['PROPERTY_TYPE'] === 'L')
						{
							if ($propertyData['MULTIPLE'] === 'Y')
							{
								foreach ($newFieldVal as &$fieldValId)
								{
									$enumPropId = Enum::getInstance()->createId($fieldValId);
									$enumPropXmlId = Enum::getInstance()->getXmlId($enumPropId);
									if ($enumPropXmlId)
									{
										$fieldValId = $enumPropXmlId;
										$dependencies['ENUM'][] = $enumPropXmlId;
									}
								}
							}
							else
							{
								$enumPropId = Enum::getInstance()->createId($newFieldVal);
								$enumPropXmlId = Enum::getInstance()->getXmlId($enumPropId);
								if ($enumPropXmlId)
								{
									$newFieldVal = $enumPropXmlId;
									$dependencies['ENUM'][] = $enumPropXmlId;
								}
							}
						}
					}
				}
				elseif ($this->isUfFilterRow($newFieldName))
				{
					$ufName = $this->getUfNameByFilterRow($newFieldName);
					$ufField = $ufFields[$ufName];
					if ($ufField)
					{
						// Зависимости от UF-полей
						$ufFieldId = $ufField['ID'];
						$ufFieldIdObj = Field::getInstance()->createId($ufFieldId);
						$dependencies['FIELD'][] = Field::getInstance()->getXmlId($ufFieldIdObj);

						// Обработка значений фильтров Списоков UF-полей
						if ($ufField['USER_TYPE_ID'] === 'enumeration' && is_array($newFieldVal))
						{
							foreach ($newFieldVal as &$ufFieldValId)
							{
								$ufFieldValIdObj = FieldEnum::getInstance()->createId($ufFieldValId);
								$ufFieldValXmlId = FieldEnum::getInstance()->getXmlId($ufFieldValIdObj);
								if ($ufFieldValXmlId)
								{
									$dependencies['FIELDENUM'][] = $ufFieldValXmlId;
									$ufFieldValId = $ufFieldValXmlId;
								}
							}
						}
					}
				}

				$newFields[$newFieldName] = $newFieldVal;
			}
			$filter['fields'] = $newFields;
		}

		return $filterFields;
	}

	/**
	 * Конвертирует название поля фильтра, являющегося фильтром свойства ИБ,
	 * в формат (xml), пригодный для выгрузки.
	 *
	 * @param string $filterRow название поля фильтра (свойство ИБ).
	 *
	 * @return string xml_id свойства элемента ИБ
	 *                или
	 *                  пустая строка, если конвертация не производилась.
	 */
	protected function convertIbPropertyFilterRowToXml(&$filterRow)
	{
		$propertyXmlId = '';
		$isMatch = false;
		$matches = array();

		/**
		 * Проверка, что поле фильтра является фильтром свойства элемента ИБ
		 */
		$this->testStringAgainstIbPropertyRegex($filterRow, $isMatch, $matches);
		if ($isMatch && $matches[2])
		{
			$filterRowPrefix = $matches[1];
			$filterRowPostfix = $matches[3];
			$propertyId = $matches[2];

			$propertyIdObj = Property::getInstance()->createId($propertyId);
			$propertyXmlId = Property::getInstance()->getXmlId($propertyIdObj);

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

		return $propertyXmlId;
	}

	/**
	 * Конвертирует название поля фильтра, являющегося фильтром свойства ИБ,
	 * в формат, пригодный для сохранения в БД.
	 *
	 * @param string $filterRow название поля фильтра (свойство ИБ).
	 *
	 * @return int id свойства элемента ИБ
	 *             или
	 *             0, если конвертация не производилась.
	 */
	protected function convertIbPropertyFilterRowFromXml(&$filterRow)
	{
		$propertyId = 0;
		$isMatch = false;
		$matches = array();

		/**
		 * Проверка, что поле фильтра является фильтром свойства элемента ИБ
		 */
		$this->testStringAgainstIbPropertyRegex($filterRow, $isMatch, $matches);
		if ($isMatch && $matches[2])
		{
			$filterRowPrefix = $matches[1];
			$filterRowPostfix = $matches[3];
			$propertyXmlId = $matches[2];

			$propertyRecord = Property::getInstance()->findRecord($propertyXmlId);
			if ($propertyRecord)
			{
				$propertyId = $propertyRecord->getValue();
				$filterRow = static::PROPERTY_FIELD_PREFIX . $propertyId;
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

		return $propertyId;
	}

	/**
	 * Конвертирует поля фильтра в формат, пригодный для сохранения в БД.
	 *
	 * @param array $filterData данные фильтра.
	 *
	 * @return array поля фильтра в формате, пригодном для сохранения в БД.
	 */
	protected function convertValueFromXml(array $filterData)
	{
		$filterFields = unserialize($filterData['FIELDS']);

		if (!$this->isConvertibleFilter($filterData))
		{
			return $filterFields;
		}

		$iblockId = $this->getIblockIdByFilterName($filterData['NAME']);
		$dbProperties = $this->getIbProperties($iblockId);
		$ufFields = $this->getUfFields('IBLOCK_' . $iblockId . '_SECTION');

		// Конвертируем данные фильтров
		foreach ($filterFields['filters'] as &$filter)
		{
			// Конвертируем названия Свойств в подмассиве filter_rows
			$filterRows = explode(',', $filter['filter_rows']);
			foreach ($filterRows as &$filterRowName)
			{
				if ($this->isIbPropertyFilterRow($filterRowName))
				{
					$this->convertIbPropertyFilterRowFromXml($filterRowName);
				}

			}
			$filter['filter_rows'] = implode(',', $filterRows);
			unset($filterRowName);

			// Конвертируем названия Свойств, Списков свойств, Списков UF-полей в подмассиве fields,
			$newFilterFields = array();
			foreach ($filter['fields'] as $filterRowName => $filterRowValue)
			{
				$newFilterRowName = $filterRowName;
				$newFilterRowValue = $filterRowValue;

				if ($this->isIbPropertyFilterRow($newFilterRowName))
				{
					// Конвертируем название поля фильтра
					$propertyId = $this->convertIbPropertyFilterRowFromXml($newFilterRowName);

					// Конфертируем значение поля фильтра
					if ($propertyId)
					{
						// Значение списочных свойств
						$propertyData = $dbProperties[$propertyId];
						if ($propertyData['PROPERTY_TYPE'] === 'L')
						{
							if ($propertyData['MULTIPLE'] === 'Y')
							{
								foreach ($newFilterRowValue as &$filterRowValueId)
								{
									$enumRecord = Enum::getInstance()->findRecord($filterRowValueId);
									if ($enumRecord)
									{
										$filterRowValueId = $enumRecord->getValue();
										$filterRowValueId = strval($filterRowValueId);
									}
								}
							}
							else
							{
								$enumRecord = Enum::getInstance()->findRecord($newFilterRowValue);
								if ($enumRecord)
								{
									$newFilterRowValue = $enumRecord->getValue();
									$newFilterRowValue = strval($newFilterRowValue);
								}
							}
						}
					}
				}
				elseif($this->isUfFilterRow($newFilterRowName))
				{
					$ufName = $this->getUfNameByFilterRow($newFilterRowName);
					$ufField = $ufFields[$ufName];
					if ($ufField)
					{
						// Обработка значений фильтров Списоков UF-полей
						if ($ufField['USER_TYPE_ID'] === 'enumeration' && is_array($newFilterRowValue))
						{
							foreach ($newFilterRowValue as &$ufFieldValXmlId)
							{
								$fieldEnumRecord = FieldEnum::getInstance()->findRecord($ufFieldValXmlId);
								if ($fieldEnumRecord)
								{
									$ufFieldValXmlId = $fieldEnumRecord->getValue();
									$ufFieldValXmlId = strval($ufFieldValXmlId);
								}
							}
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