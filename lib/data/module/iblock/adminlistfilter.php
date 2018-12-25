<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use CUserTypeEntity;
use Intervolga\Migrato\Data\BaseData;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\Link;
use CUserOptions;
use Intervolga\Migrato\Data\Module\Main\Site;
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
	const IB_PROPERTY_NAME_REGEX = '/^([a-z]+_)?PROPERTY_(.+?)(_[a-z]+)?$/';

	/**
	 * REGEX: название фильтра UF-поля
	 */
	const UF_NAME_REGEX = '/^(UF_[A-Z0-9_]+)(_[a-z]+)?$/';

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
			'IBLOCK' => new Link(Iblock::getInstance()),
			'IBLOCK_TYPE' => new Link(Type::getInstance()),
			'SITE' => new Link(Site::getInstance()),
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
		 * - Сайты
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
	 * Возвращает свойство ИБ из БД.
	 *
	 * @param array $filter фильтр выборки.
	 *
	 * @return array данные свойства ИБ.
	 */
	protected function getIblockProperty($filter = array())
	{
		$dbRes = \CIBlockProperty::GetList(array(), $filter);
		return $dbRes->GetNext() ?: array();
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
	 * Возвращает UF-поле из БД.
	 *
	 * @param array $filter фильтр выборки.
	 *
	 * @return array данные UF-поля.
	 */
	protected function getUfField(array $filter)
	{
		$dbRes = CUserTypeEntity::GetList(array(), $filter);
		return $dbRes->GetNext() ?: array();
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
			$iblockRecord = Iblock::getInstance()->findRecord($xmlId);
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
		$nonConvertibleFilterTypes = array('IB_PROPERTIES_LIST');

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
	 * Проверяет, что строка $string, является названием фильтра
	 * для Свойства ИБ.
	 *
	 * @param string $string проверяемая строка.
	 *
	 * @return bool true, если $string - название фильтра для Свойства ИБ, иначе - false.
	 */
	protected function isIblockPropertyFilterName($string)
	{
		$isMatch = preg_match(static::IB_PROPERTY_NAME_REGEX, $string, $matches);

		return ($isMatch && $matches[2]);
	}

	/**
	 * Проверяет, что строка $string, является названием фильтра
	 * для UF-поля.
	 *
	 * @param string $string проверяемая строка.
	 *
	 * @return bool true, если $string - название фильтра для UF-поля, иначе - false.
	 */
	protected function isUfFieldFilterName($string)
	{
		$length = strlen(static::UF_FIELD_PREFIX);

		return (substr($string, 0, $length) === static::UF_FIELD_PREFIX);
	}

	/**
	 * Проверяет, что строка $string, является названием фильтра
	 * для Сайта.
	 *
	 * @param string $string проверяемая строка.
	 *
	 * @return bool true, если $string - название фильтра для Сайта, иначе - false.
	 */
	protected function isSiteIdFilterName($string)
	{
		return $string === 'LID';
	}

	/**
	 * Возвращает id свойства элемента ИБ по названию поля фильтра.
	 *
	 * @param string $filterFieldName название поля фильтра.
	 *
	 * @return string id свойства элемента ИБ.
	 */
	protected function getIblockPropertyId($filterFieldName)
	{
		$isMatch = preg_match(static::IB_PROPERTY_NAME_REGEX, $filterFieldName, $matches);
		return ($isMatch && $matches[2]) ? $matches[2] : '';
	}

	/**
	 * Возвращает название UF-поля по названию поля фильтра.
	 *
	 * @param string $filterFieldName название поля фильтра.
	 *
	 * @return string название UF-поля.
	 */
	protected function getUfFieldName($filterFieldName)
	{
		$isMatch = preg_match(static::UF_NAME_REGEX, $filterFieldName, $matches);
		return ($isMatch && $matches[1]) ? $matches[1] : '';
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
				$isMatch = preg_match(static::IB_PROPERTY_NAME_REGEX, $filterRow, $matches);
				if ($isMatch && $matches[2])
				{
					$propertyIds[] = $matches[2];
				}
			}
		}

		return array_unique($propertyIds);
	}

	/**
	 * Конвертирует поля фильтра в формат (xml), пригодный для выгрузки.
	 *
	 * @param array $filterData данные фильтра.
	 * @param array $xmlIds xmlId сконвертированных данных.
	 *
	 * @return array поля фильтра в форме, пригодной для выгрузки.
	 */
	protected function convertValueToXml(array $filterData, array &$xmlIds)
	{
		$filterFields = unserialize($filterData['VALUE']);

		if (!$this->isConvertibleFilter($filterData))
		{
			return $filterFields;
		}

		$iblockId = $this->getIblockIdByFilterName($filterData['NAME']);
		$filterData['IBLOCK_ID'] = $iblockId;

		// Конвертируем данные фильтров
		foreach ($filterFields['filters'] as &$filter)
		{
			// Конвертируем названия полей фильтра
			$arFilterRows = explode(',', $filter['filter_rows']);
			foreach ($arFilterRows as &$filterFieldName)
			{
				$filterField = array($filterFieldName);
				$this->convertFilterFieldToXml($filterField, $filterData, $xmlIds);
				list($filterFieldName) = $filterField;

			}
			unset($filterFieldName);
			$filter['filter_rows'] = implode(',', $arFilterRows);


			// Конвертируем пары <называние_поля_фильтра> => <значение_поля_фильтра>
			$newFields = array();
			foreach ($filter['fields'] as $fieldName => $fieldVal)
			{
				$filterField = array($fieldName, $fieldVal);
				$this->convertFilterFieldToXml($filterField, $filterData, $xmlIds);
				list($newFieldName, $newFieldVal) = $filterField;

				$newFields[$newFieldName] = $newFieldVal;
			}
			$filter['fields'] = $newFields;
		}

		return $filterFields;
	}

	/**
	 * Конвертирует поле фильтра в формат (xml), пригодный для выгрузки.
	 * Поле фильтра - пара <название_поля_фильтра> => <значение_поля_фильтра>.
	 *
	 * @param array $filter поле фильтра.
	 * @param array $arFilter данные фильтра.
	 * @param array $xmlIds xml_id сконвертированных данных.
	 */
	protected function convertFilterFieldToXml(array &$filter, array $arFilter, array &$xmlIds)
	{
		// $filter - пара <название_поля_фильтра> => <значение_поля_фильтра>
		list($filterName, $filterValue) = $filter;

		if ($propId = $this->getIblockPropertyId($filterName))
		{ // Если фильтр - фильтр Свойства ИБ
			$this->convertIblockPropertyFilterNameToXml($filterName, $xmlIds);

			if ($filterValue)
			{
				$property = $this->getIblockProperty(array(
					'IBLOCK_ID' => $arFilter['IBLOCK_ID'],
					'ID' => $propId,
				));

				if ($property['PROPERTY_TYPE'] == 'L')
				{
					$this->convertEnumPropertyValueToXml($filterValue, $xmlIds);
				}
			}
		}
		elseif($ufName = $this->getUfFieldName($filterName))
		{ // Если фильтр - фильтр UF-поля
			$ufField = $this->getUfField(array(
				'ENTITY_ID' => 'IBLOCK_' . $arFilter['IBLOCK_ID'] . '_SECTION',
				'FIELD_NAME' => $ufName
			));
			$ufFieldIdObj = Field::getInstance()->createId($ufField['ID']);
			$xmlIds['FIELD'][] = Field::getInstance()->getXmlId($ufFieldIdObj);

			if ($filterValue)
			{
				if ($ufField['USER_TYPE_ID'] === 'enumeration')
				{
					$this->convertEnumUfFieldValueToXml($filterValue, $xmlIds);
				}
			}
		}
		elseif($this->isSiteIdFilterName($filterName))
		{ // Если фильтр - фильтр Сайта
			if ($filterValue)
			{
				$this->convertSiteIdValueToXml($filterValue, $xmlIds);
			}
		}

		$filter = array($filterName, $filterValue);
	}

	/**
	 * Конвертирует название фильтра Свойства ИБ
	 * в формат (xml), пригодный для выгрузки.
	 *
	 * @param string $filterName название поля фильтра (свойство ИБ).
	 * @param array $xmlIds xml_id сконвертированных данных.
	 */
	protected function convertIblockPropertyFilterNameToXml(&$filterName, &$xmlIds)
	{
		/**
		 * Проверка, что поле фильтра является фильтром свойства элемента ИБ
		 */
		$isMatch = preg_match(static::IB_PROPERTY_NAME_REGEX, $filterName, $matches);
		if ($isMatch && $matches[2])
		{
			$filterRowPrefix = $matches[1];
			$filterRowPostfix = $matches[3];
			$propertyId = $matches[2];

			$propertyIdObj = Property::getInstance()->createId($propertyId);
			$propertyXmlId = Property::getInstance()->getXmlId($propertyIdObj);
			if ($propertyXmlId)
			{
				$xmlIds['PROPERTY'][] = $propertyXmlId;

				$filterName = static::PROPERTY_FIELD_PREFIX . $propertyXmlId;
				if ($filterRowPrefix)
				{
					$filterName = $filterRowPrefix . $filterName;
				}
				if ($filterRowPostfix)
				{
					$filterName = $filterName . $filterRowPostfix;
				}
			}
		}
	}

	/**
	 * Конвертирует значение фильтра Свойства ИБ
	 * в формат (xml), пригодный для выгрузки.
	 *
	 * @param string|array $propertyValue значение списочного Свойства ИБ.
	 * @param array $xmlIds xml_id сконвертированных данных.
	 */
	protected function convertEnumPropertyValueToXml(&$propertyValue, array &$xmlIds)
	{
		if (is_array($propertyValue))
		{
			foreach	($propertyValue as &$propertyValueId)
			{
				$enumPropId = Enum::getInstance()->createId($propertyValueId);
				$enumPropXmlId = Enum::getInstance()->getXmlId($enumPropId);
				if ($enumPropXmlId)
				{
					$propertyValueId = $enumPropXmlId;
					$xmlIds['ENUM'][] = $enumPropXmlId;
				}
			}
		}
		else
		{
			$enumPropId = Enum::getInstance()->createId($propertyValue);
			$enumPropXmlId = Enum::getInstance()->getXmlId($enumPropId);
			if ($enumPropXmlId)
			{
				$propertyValue = $enumPropXmlId;
				$xmlIds['ENUM'][] = $enumPropXmlId;
			}
		}
	}

	/**
	 * Конвертирует значение фильтра UF-поля
	 * в формат (xml), пригодный для выгрузки.
	 *
	 * @param string|array $ufFieldValue значение списочного UF-поля.
	 * @param array $xmlIds xml_id сконвертированных данных.
	 */
	protected function convertEnumUfFieldValueToXml(&$ufFieldValue, array &$xmlIds)
	{
		if (is_array($ufFieldValue))
		{
			foreach ($ufFieldValue as &$ufFieldValId)
			{
				$ufFieldValIdObj = FieldEnum::getInstance()->createId($ufFieldValId);
				$ufFieldValXmlId = FieldEnum::getInstance()->getXmlId($ufFieldValIdObj);
				if ($ufFieldValXmlId)
				{
					$ufFieldValId = $ufFieldValXmlId;
					$xmlIds['FIELDENUM'][] = $ufFieldValXmlId;
				}
			}
		}
		else
		{
			$ufFieldValIdObj = FieldEnum::getInstance()->createId($ufFieldValue);
			$ufFieldValXmlId = FieldEnum::getInstance()->getXmlId($ufFieldValIdObj);
			if ($ufFieldValXmlId)
			{
				$ufFieldValue = $ufFieldValXmlId;
				$xmlIds['FIELDENUM'][] = $ufFieldValXmlId;
			}
		}
	}

	/**
	 * Конвертирует значение фильтра Сайта
	 * в формат (xml), пригодный для выгрузки.
	 *
	 * @param string|array $siteId id сайта.
	 * @param array $xmlIds xml_id сконвертированных данных.
	 */
	protected function convertSiteIdValueToXml(&$siteId, array &$xmlIds)
	{
		if (is_array($siteId))
		{
			foreach	($siteId as &$siteIdVal)
			{
				$siteIdObj = Site::getInstance()->createId($siteIdVal);
				$siteXmlId = Site::getInstance()->getXmlId($siteIdObj);
				if ($siteXmlId)
				{
					$xmlIds['SITE'][] = $siteXmlId;
					$siteIdVal = $siteXmlId;
				}
			}
		}
		else
		{
			$siteIdObj = Site::getInstance()->createId($siteId);
			$siteXmlId = Site::getInstance()->getXmlId($siteIdObj);
			if ($siteXmlId)
			{
				$xmlIds['SITE'][] = $siteXmlId;
				$siteId = $siteXmlId;
			}
		}
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
		$filterFields = unserialize($filterData['VALUE']);

		if (!$this->isConvertibleFilter($filterData))
		{
			return $filterFields;
		}

        $filterData['IBLOCK_ID'] = $this->getIblockIdByFilterName($filterData['NAME']);

		// Конвертируем данные фильтров
		foreach ($filterFields['filters'] as &$filter)
		{
			// Конвертируем названия Свойств в подмассиве filter_rows
			$filterRows = explode(',', $filter['filter_rows']);
			foreach ($filterRows as &$filterFieldName)
			{
                $filterField = array($filterFieldName);
                $this->convertFilterFieldFromXml($filterField, $filterData);
                list($filterFieldName) = $filterField;
            }
			$filter['filter_rows'] = implode(',', $filterRows);
			unset($filterFieldName);

            // Конвертируем пары <называние_поля_фильтра> => <значение_поля_фильтра>
            $newFields = array();
            foreach ($filter['fields'] as $fieldName => $fieldVal)
            {
                $filterField = array($fieldName, $fieldVal);
                $this->convertFilterFieldFromXml($filterField, $filterData);
                list($newFieldName, $newFieldVal) = $filterField;

                $newFields[$newFieldName] = $newFieldVal;
            }
            $filter['fields'] = $newFields;
		}

		return $filterFields;
	}

    /**
     * Конвертирует поле фильтра в формат, пригодный для сохранения в БД.
     * Поле фильтра - пара <название_поля_фильтра> => <значение_поля_фильтра>.
     *
     * @param array $filter поле фильтра.
     * @param array $arFilter данные фильтра.
     */
	protected function convertFilterFieldFromXml(array &$filter, array $arFilter)
    {
        // $filter - пара <название_поля_фильтра> => <значение_поля_фильтра>
        list($filterName, $filterValue) = $filter;

        if ($this->isIblockPropertyFilterName($filterName))
        { // Если фильтр - фильтр Свойства ИБ
            $this->convertIblockPropertyFilterNameFromXml($filterName);

            if ($filterValue)
            {
                $propertyId = $this->getIblockPropertyId($filterName);
                $property = $this->getIblockProperty(array(
                    'IBLOCK_ID' => $arFilter['IBLOCK_ID'],
                    'ID' => $propertyId,
                ));

                if ($property['PROPERTY_TYPE'] == 'L')
                {
                    $this->convertEnumPropertyValueFromXml($filterValue);
                }
            }
        }
        elseif($ufName = $this->getUfFieldName($filterName))
        { // Если фильтр - фильтр UF-поля
            if ($filterValue)
            {
                $ufField = $this->getUfField(array(
                    'ENTITY_ID' => 'IBLOCK_' . $arFilter['IBLOCK_ID'] . '_SECTION',
                    'FIELD_NAME' => $ufName
                ));

                if ($ufField['USER_TYPE_ID'] === 'enumeration')
                {
                    $this->convertEnumUfFieldValueFromXml($filterValue);
                }
            }
        }
        elseif($this->isSiteIdFilterName($filterName))
        { // Если фильтр - фильтр Сайта
            if ($filterValue)
            {
                $this->convertSiteIdValueFromXml($filterValue);
            }
        }

        $filter = array($filterName, $filterValue);
    }

    /**
     * Конвертирует название фильтра Свойства ИБ
     * в формат, пригодный для сохранения в БД.
     *
     * @param string $filterRow название поля фильтра (свойство ИБ).
     */
    protected function convertIblockPropertyFilterNameFromXml(&$filterRow)
    {
        /**
         * Проверка, что поле фильтра является фильтром свойства элемента ИБ
         */
        $isMatch = preg_match(static::IB_PROPERTY_NAME_REGEX, $filterRow, $matches);
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
    }

    /**
     * Конвертирует значение фильтра Свойства ИБ
     * в формат, пригодный для сохранения в БД.
     *
     * @param string|array $propertyValue значение списочного Свойства ИБ.
     */
    protected function convertEnumPropertyValueFromXml(&$propertyValue)
    {
        if (is_array($propertyValue))
        {
            foreach	($propertyValue as &$propertyValueId)
            {
                $enumRecord = Enum::getInstance()->findRecord($propertyValueId);
                if ($enumRecord)
                {
                    $propertyValueId = $enumRecord->getValue();
                    $propertyValueId = strval($propertyValueId);
                }
            }
        }
        else
        {
            $enumRecord = Enum::getInstance()->findRecord($propertyValue);
            if ($enumRecord)
            {
                $propertyValue = $enumRecord->getValue();
                $propertyValue = strval($propertyValue);
            }
        }
    }

    /**
     * Конвертирует значение фильтра UF-поля
     * в формат, пригодный для сохранения в БД.
     *
     * @param string|array $ufFieldValue значение списочного UF-поля.
     */
    protected function convertEnumUfFieldValueFromXml(&$ufFieldValue)
    {
        if (is_array($ufFieldValue))
        {
            foreach ($ufFieldValue as &$ufFieldValXmlId)
            {
                $fieldEnumRecord = FieldEnum::getInstance()->findRecord($ufFieldValXmlId);
                if ($fieldEnumRecord)
                {
                    $ufFieldValXmlId = $fieldEnumRecord->getValue();
                    $ufFieldValXmlId = strval($ufFieldValXmlId);
                }
            }
        }
        else
        {
            $fieldEnumRecord = FieldEnum::getInstance()->findRecord($ufFieldValue);
            if ($fieldEnumRecord)
            {
                $ufFieldValue = $fieldEnumRecord->getValue();
                $ufFieldValue = strval($ufFieldValue);
            }
        }
    }

    /**
     * Конвертирует значение фильтра Сайта
     * в формат,пригодный для сохранения в БД.
     *
     * @param string|array $siteId id сайта.
     */
    protected function convertSiteIdValueFromXml(&$siteId)
    {
        if (is_array($siteId))
        {
            foreach	($siteId as &$siteIdVal)
            {
                $siteRecord = Site::getInstance()->findRecord($siteIdVal);
                if ($siteRecord)
                {
                    $siteIdVal = $siteRecord->getValue();
                    $siteIdVal = strval($siteIdVal);
                }
            }
        }
        else
        {
            $siteRecord = Site::getInstance()->findRecord($siteId);
            if ($siteRecord)
            {
                $siteId = $siteRecord->getValue();
                $siteId = strval($siteId);
            }
        }
    }
}