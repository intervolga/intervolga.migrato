<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use CUserOptions;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CUserTypeEntity;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

/**
 * Class AdminListOptions - настройки отображения админ. страниц списка элементов и разделов инфоблока.
 *
 * Хэш названия настройки (<hash>): строка в которой закодирована информация об ИБ.
 * Конвертируемые настройки: настройки, поле VALUE которых подлежит конвертации в формат выгрузки (xml).
 *
 * @package Intervolga\Migrato\Data\Module\Iblock
 */
class AdminListOption extends BaseData
{
	/**
	 * Категории настроек.
	 */
	const OPTION_CATEGORIES = array(
		'PERSONAL' => 'main.interface.grid',
		'COMMON' => 'main.interface.grid.common',
	);

	/**
	 * Префиксы названия настроек. После префиксов в названиях настроек идет <hash>:
	 * option_name=<prefix><hash>
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
	const OPTION_NAME_PREFIXES = array(
		'IB_MIXED_LIST' => 'tbl_iblock_list_',
		'PRODUCTS_MIXED_LIST' => 'tbl_product_list_',

		'IB_SECTION_LIST' => 'tbl_iblock_section_',
		'PRODUCTS_SECTION_LIST' => 'tbl_catalog_section_',

		'IB_ELEMENT_LIST' => 'tbl_iblock_element_',
		'PRODUCTS_LIST' => 'tbl_product_admin_',

		'IB_LIST' => 'tbl_iblock_',
		'IB_LIST_ADMIN' => 'tbl_iblock_admin_',

		'IB_PROPERTIES_LIST' => 'tbl_iblock_property_admin_',
	);

	/**
	 * REGEX: имя настройки
	 */
	const OPTION_NAME_REGEX = '/^([a-z_]+_)(.+)$/';

	/**
	 * REGEX: название свойства ИБ
	 */
	const IBLOCK_PROPERTY_NAME_REGEX = '/^PROPERTY_(.+)$/';

	/**
	 * REGEX: название UF-поля
	 */
	const UF_FIELD_NAME_REGEX = '/^(UF_[A-Z0-9_]+)$/';

	/**
	 * Символ-разделитель логических блоков в строке с xmlId.
	 */
	const XML_ID_SEPARATOR = '.';

	/**
	 * Префикс свойства ИБ.
	 */
	const PROPERTY_FIELD_PREFIX = 'PROPERTY_';

	/**
	 * Префикс UF-поля.
	 */
	const UF_FIELD_PREFIX = 'UF_';

	protected function configure()
	{
		Loader::includeModule('iblock');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ADMIN_LIST_OPTIONS.ENTITY_NAME'));
		$this->setVirtualXmlId(true);
		$this->setFilesSubdir('/type/iblock/admin/');
		$this->setDependencies(array(
			'IBLOCK' => new Link(Iblock::getInstance()),
			'IBLOCK_TYPE' => new Link(Type::getInstance()),
			'PROPERTY' => new Link(Property::getInstance()),
			'FIELD' => new Link(Field::getInstance()),
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
				if (static::isOption($option))
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
	 * @return RecordId
	 * @throws \Exception
	 */
	protected function createInner(Record $record)
	{
		$optionId = $this->saveOptionFromRecord($record);
		if ($optionId)
		{
			return $this->createId($optionId);
		}

		$exceptionMessage = ExceptionText::getFromString(
			Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ADMIN_LIST_OPTIONS.ADD_ERROR')
		);
		throw new \Exception($exceptionMessage);
	}

	/**
	 * @param Record $record
	 *
	 * @throws \Exception
	 */
	public function update(Record $record)
	{
		$optionId = 0;
		if ($record->getId())
		{
			$optionId = $this->saveOptionFromRecord($record);
		}

		if (!$optionId)
		{
			$exceptionMessage = ExceptionText::getFromString(
				Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ADMIN_LIST_OPTIONS.UPDATE_ERROR')
			);
			throw new \Exception($exceptionMessage);
		}
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
				Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ADMIN_LIST_OPTIONS.DELETE_ERROR')
			);
			throw new \Exception($exceptionMessage);
		}
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
	 *
	 * @return RecordId|null
	 */
	public function findRecord($xmlId)
	{
		$xmlIdFields = explode(static::XML_ID_SEPARATOR, $xmlId);
		$optionUserId = $xmlIdFields[2];
		$optionName = $this->getOptionName($xmlId);
		$optionCommon = $optionUserId == 0;
		$optionCategory = $optionCommon ? static::OPTION_CATEGORIES['COMMON'] : static::OPTION_CATEGORIES['PERSONAL'];

		$option = CUserOptions::GetList(
			array(),
			array(
				'CATEGORY' => $optionCategory,
				'NAME' => $optionName,
				'USER_ID' => $optionUserId
			)
		)->GetNext();

		return $option['ID'] ? $this->createId($option['ID']) : null;
	}

	/**
	 * Формирует настройку из $record и сохраняет ее в БД.
	 *
	 * @param Record $record запись миграции, получаемая на входе методов update(), createInner().
	 *
	 * @return int id сохраненной настройки или 0.
	 */
	protected function saveOptionFromRecord(Record $record)
	{
		$arOption = $this->createArrayFromRecord($record);

		// Сохраняем настройку
		$optionId = 0;
		$success = false;
		if ($arOption['CATEGORY'] && $arOption['NAME'] && $arOption['VALUE'])
		{
			$success = CUserOptions::SetOption(
				$arOption['CATEGORY'],
				$arOption['NAME'],
				$arOption['VALUE'],
				$arOption['COMMON'] === 'Y',
				$arOption['USER_ID']
			);
		}

		// Проверяем успешность сохранения
		if ($success)
		{
			$option = CUserOptions::GetList(
				array(),
				array(
					'CATEGORY' => $arOption['CATEGORY'],
					'NAME' => $arOption['NAME'],
					'USER_ID' => $arOption['USER_ID'],
				)
			)->GetNext();

			$optionId = $option['ID'] ?: 0;
		}

		return $optionId;
	}

	/**
	 * Создает массив данных настройки
	 *
	 * @param Record $record запись миграции настройки.
	 *
	 * @return string[] массив данных настройки.
	 */
	protected function createArrayFromRecord(Record $record)
	{
		$arOption = $record->getFieldsRaw();
		$xmlIdFields = explode(static::XML_ID_SEPARATOR, $record->getXmlId());

		$arOption['USER_ID'] = $xmlIdFields[2];
		$arOption['NAME'] = $this->getOptionName($record->getXmlId());
		$arOption['VALUE'] = $this->convertValueFromXml($arOption);

		return $arOption;
	}

	/**
	 * Создает запись миграции настройки
	 *
	 * @param array $option массив данных настройки.
	 *
	 * @return Record запись миграции настройки.
	 */
	protected function createRecordFromArray(array $option)
	{
		$record = new Record($this);
		$record->setId($this->createId($option['ID']));
		$record->setXmlId($this->getXmlIdFromArray($option));
		$record->setFieldRaw('COMMON', $option['COMMON']);
		$record->setFieldRaw('CATEGORY', $option['CATEGORY']);
		$this->setIblockDependency($record, $option);
		$this->setOptionValue($record, $option);

		return $record;
	}

	/**
	 * Добавляет в запись миграции $record поле VALUE настройки.
	 * Предварительно поле конвертируется в формат (xml), пригодный для выгрузки.
	 * Также добавляются зависимости от xml_id сконвертированных данных.
	 *
	 * @param Record $record запись миграции настройки.
	 * @param array $option данные настройки.
	 */
	protected function setOptionValue(Record $record, array $option)
	{
		$dependencies = array();

		$arFields = $this->convertValueToXml($option, $dependencies);
		$record->setFieldRaw('VALUE', serialize($arFields));


		/**
		 * Зависимости от сторонних сущностей:
		 * - Свойства ИБ
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
	 * Конвертирует поле VALUE настройки в формат (xml), пригодный для выгрузки.
	 *
	 * @param array $option данные настройки.
	 * @param array $xmlIds xml_id сконвертированных данных.
	 *
	 * @return array массив сконвертированных данных.
	 */
	protected function convertValueToXml(array $option, array &$xmlIds)
	{
		$arOptionValue = unserialize($option['VALUE']);

		if (!$this->isConvertibleOption($option))
		{
			return $arOptionValue;
		}

		// Для общих и персональных настроек структура поля VALUE отличается
		if($option['COMMON'] === 'Y')
		{
			$this->convertOptionViewToXml($arOptionValue['view'], $option, $xmlIds);
		}
		else
		{
			foreach	($arOptionValue['views'] as &$view)
			{
				$this->convertOptionViewToXml($view, $option, $xmlIds);
			}
		}

		return $arOptionValue;
	}

	/**
	 * Конвертирует поле VALUE настройки в формат, пригодный для сохранения в БД.
	 *
	 * @param array $option данные настройки.
	 *
	 * @return array массив сконвертированных данных.
	 */
	protected function convertValueFromXml(array $option)
	{
		$arOptionValue = unserialize($option['VALUE']);

		if (!$this->isConvertibleOption($option))
		{
			return $arOptionValue;
		}

		// Для общих и персональных настроек структура поля VALUE отличается
		if($option['COMMON'] === 'Y')
		{
			$this->convertOptionViewFromXml($arOptionValue['view']);
		}
		else
		{
			foreach	($arOptionValue['views'] as &$view)
			{
				$this->convertOptionViewFromXml($view);
			}
		}

		return $arOptionValue;
	}

	/**
	 * Конвертирует массив view в поле VALUE настройки в формат (xml), пригодный для выгрузки.
	 *
	 * @param array $view конвертируемый массив.
	 * @param array $option данные настройки.
	 * @param array $xmlIds xml_id сконвертированных данных.
	 */
	protected function convertOptionViewToXml(array &$view, array $option, array &$xmlIds)
	{
		// Конвертация массива 'columns' (отображаемые колонки)
		if ($view['columns'])
		{
			$arViewColumns = explode(',', $view['columns']);
			foreach ($arViewColumns as &$viewColumn)
			{
				$this->convertStringToXml($viewColumn, $option, $xmlIds);
			}
			$view['columns'] = implode(',', $arViewColumns);
		}

		// Конвертируем массивы
		$arraysToConvert = array();
		if ($view['columns_sizes']['columns'])
		{
			$arraysToConvert[] = &$view['columns_sizes']['columns'];
		}
		if ($view['custom_names'])
		{
			$arraysToConvert[] = &$view['custom_names'];
		}

		foreach ($arraysToConvert as &$arrayToConvert)
		{
			$convertedArray = array();
			foreach	($arrayToConvert as $arrayKey => $arrayVal)
			{
				$this->convertStringToXml($arrayKey, $option, $xmlIds);
				$convertedArray[$arrayKey] = $arrayVal;
			}
			$arrayToConvert = $convertedArray;
		}
		unset($arrayToConvert);
		unset($arraysToConvert);

		// Конвертируем строки
		$stringsToConvert = array();
		if ($view['last_sort_by'])
		{
			$stringsToConvert[] = &$view['last_sort_by'];
		}
		if ($view['sort_by'])
		{
			$stringsToConvert[] = &$view['sort_by'];
		}

		foreach	($stringsToConvert as &$stringToConvert)
		{
			if ($stringToConvert)
			{
				$this->convertStringToXml($stringToConvert, $option, $xmlIds);
			}
		}
		unset($stringToConvert);
		unset($stringsToConvert);
	}

	/**
	 * Конвертирует массив view в поле VALUE настройки в формат, пригодный для сохранения в БД.
	 *
	 * @param array $view конвертируемый массив.
	 */
	protected function convertOptionViewFromXml(array &$view)
	{
		// Конвертация массива 'columns' (отображаемые колонки)
		if ($view['columns'])
		{
			$arViewColumns = explode(',', $view['columns']);
			foreach ($arViewColumns as &$viewColumn)
			{
				$this->convertStringFromXml($viewColumn);
			}
			$view['columns'] = implode(',', $arViewColumns);
		}

		// Конвертируем массивы
		$arraysToConvert = array();
		if ($view['columns_sizes']['columns'])
		{
			$arraysToConvert[] = &$view['columns_sizes']['columns'];
		}
		if ($view['custom_names'])
		{
			$arraysToConvert[] = &$view['custom_names'];
		}

		foreach ($arraysToConvert as &$arrayToConvert)
		{
			$convertedArray = array();
			foreach	($arrayToConvert as $arrayKey => $arrayVal)
			{
				$this->convertStringFromXml($arrayKey);
				$convertedArray[$arrayKey] = $arrayVal;
			}
			$arrayToConvert = $convertedArray;
		}
		unset($arrayToConvert);
		unset($arraysToConvert);

		// Конвертируем строки
		$stringsToConvert = array();
		if ($view['last_sort_by'])
		{
			$stringsToConvert[] = &$view['last_sort_by'];
		}
		if ($view['sort_by'])
		{
			$stringsToConvert[] = &$view['sort_by'];
		}

		foreach	($stringsToConvert as &$stringToConvert)
		{
			if ($stringToConvert)
			{
				$this->convertStringFromXml($stringToConvert);
			}
		}
		unset($stringToConvert);
		unset($stringsToConvert);
	}

	/**
	 * Конвертирует строку в формат (xml), пригодный для выгрузки.
	 *
	 * @param string $stringToConvert конвертируемая строка.
	 * @param array $option данные настройки.
	 * @param array $xmlIds xml_id сконвертированных данных.
	 */
	protected function convertStringToXml(&$stringToConvert, array $option, array &$xmlIds)
	{
		if ($this->isIblockProperty($stringToConvert))
		{
			$propertyXmlId = $this->convertIblockPropertyNameToXml($stringToConvert);
			$xmlIds['PROPERTY'][] = $propertyXmlId;
		}
		elseif($this->isUfField($stringToConvert))
		{
			$iblock = $this->getIblockByOptionName($option['NAME']);
			$ufField = $this->getUfField(array(
				'ENTITY_ID' => 'IBLOCK_' . $iblock['ID'] . '_SECTION',
				'FIELD_NAME' => $stringToConvert
			));

			if ($ufField)
			{
				$ufFieldIdObj = Field::getInstance()->createId($ufField['ID']);
				$xmlIds['FIELD'][] = Field::getInstance()->getXmlId($ufFieldIdObj);
			}
		}
	}

	/**
	 * Конвертирует строку в формат (xml), пригодный для сохранения в БД.
	 *
	 * @param string $stringToConvert конвертируемая строка.
	 */
	protected function convertStringFromXml(&$stringToConvert)
	{
		if ($this->isIblockProperty($stringToConvert))
		{
			$this->convertIblockPropertyNameFromXml($stringToConvert);
		}
	}

	/**
	 * Конвертирует название свойства ИБ в формат (xml), пригодный для выгрузки.
	 *
	 * @param string $iblockPropertyName название свойства ИБ.
	 *
	 * @return string xml_id свойства ИБ
	 *                или
	 *                пустая строка, если конвертация не производилась.
	 */
	protected function convertIblockPropertyNameToXml(&$iblockPropertyName)
	{
		$propertyXmlId = '';

		$isMatch = preg_match(static::IBLOCK_PROPERTY_NAME_REGEX, $iblockPropertyName, $matches);
		if ($isMatch && $matches[1])
		{
			// Конвертируем свойство
			$propertyId = intval($matches[1]);
			$propertyIdObj = Property::getInstance()->createId($propertyId);
			$propertyXmlId = Property::getInstance()->getXmlId($propertyIdObj);
			$iblockPropertyName = static::PROPERTY_FIELD_PREFIX . $propertyXmlId;
		}

		return $propertyXmlId;
	}

	/**
	 * Конвертирует название свойства ИБ в формат, пригодный для сохранения в БД.
	 *
	 * @param string $iblockPropertyName название свойства ИБ.
	 *
	 * @return string id свойства ИБ
	 *                или
	 *                пустая строка, если конвертация не производилась.
	 */
	protected function convertIblockPropertyNameFromXml(&$iblockPropertyName)
	{
		$propertyId = 0;

		$isMatch = preg_match(static::IBLOCK_PROPERTY_NAME_REGEX, $iblockPropertyName, $matches);
		if ($isMatch && $matches[1])
		{
			$propertyRecord = Property::getInstance()->findRecord($matches[1]);
			if ($propertyRecord)
			{
				$propertyId = $propertyRecord->getValue();
				$iblockPropertyName = static::PROPERTY_FIELD_PREFIX . $propertyId;
			}
		}

		return $propertyId;
	}

	/**
	 * Добавляет в запись миграции $record
	 * зависимость от ИБ или типа ИБ.
	 *
	 * @param Record $record запись миграции настройки.
	 * @param array $option массив данных настройки.
	 */
	protected function setIblockDependency(Record $record, array $option)
	{
		$dependencyXmlId = $this->getOptionIblockDependency($option['NAME']);
		$dependencyName = $this->isOptionForIblock($option['NAME']) ? 'IBLOCK' : 'IBLOCK_TYPE';

		if ($dependencyXmlId)
		{
			$dependency = clone $this->getDependency($dependencyName);
			$dependency->setValue($dependencyXmlId);
			$record->setDependency($dependencyName, $dependency);
		}
	}

	/**
	 * Возвращает xmlId настройки
	 * на основе массива ее данных $option.
	 *
	 * @param array $option массив данных настройки.
	 *
	 * @return string xmlId настройки.
	 */
	protected function getXmlIdFromArray(array $option)
	{
		$result = '';

		$dependencyXmlId = $this->getOptionIblockDependency($option['NAME']);
		if ($dependencyXmlId)
		{
			$optionType = $this->getOptionType($option['NAME']);
			$result = $optionType
				. static::XML_ID_SEPARATOR
				. $dependencyXmlId
				. static::XML_ID_SEPARATOR
				. $option['USER_ID'];
		}

		return $result;
	}

	/**
	 * Возвращает xml_id основной зависимости настроки - зависимости от ИБ или типа ИБ.
	 *
	 * @param string $optionName название настройки.
	 *
	 * @return string xml_id основной зависимости настроки.
	 */
	protected function getOptionIblockDependency($optionName)
	{
		$dependencyXmlId = '';

		$iblock = $this->getIblockByOptionName($optionName);
		if ($iblock)
		{
			if ($this->isOptionForIblock($optionName))
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
	 * Возвращает ИБ по названию настройки.
	 *
	 * @param string $optionName название настройки.
	 *
	 * @return array данные ИБ.
	 */
	protected function getIblockByOptionName($optionName)
	{
		if (Loader::includeModule('iblock'))
		{
			$res = \CIBlock::GetList();
			while ($iblock = $res->Fetch())
			{
				if ($this->isOptionIblock($iblock, $optionName))
				{
					return $iblock;
				}
			}
		}

		return array();
	}

	/**
	 * Возвращает название настройки по ее xml_id.
	 *
	 * @param string $optionXmlId xml_id настройки.
	 *
	 * @return string название настройки.
	 */
	protected function getOptionName($optionXmlId)
	{
		$xmlIdFields = explode(static::XML_ID_SEPARATOR, $optionXmlId);
		$xmlId = $xmlIdFields[1];
		$optionType = $xmlIdFields[0];
		$optionTypePrefix = static::OPTION_NAME_PREFIXES[$optionType];

		if (in_array($optionType, $this->getIblockOptionTypes()))
		{
			/** @var RecordId $iblockRecord */
			$iblockRecord = Iblock::getInstance()->findRecord($xmlId);
			if ($iblockRecord)
			{
				$iblockInfo = \CIBlock::GetByID($iblockRecord->getValue())->GetNext();
				if ($optionType === 'IB_PROPERTIES_LIST')
				{
					return $optionTypePrefix . $iblockInfo['ID'];
				}
				else
				{
					return $optionTypePrefix . md5($iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockInfo['ID']);
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
				return $optionTypePrefix . md5($iblockTypeInfo['ID']);
			}
		}

		return '';
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
		$identifier = 0;
		$iblockInfo = array();

		if (Loader::includeModule('iblock'))
		{
			$iblockRecord = Iblock::getInstance()->findRecord($iblockXmlId);
			if ($iblockRecord)
			{
				$identifier = $iblockRecord->getValue();
				$iblockInfo = \CIBlock::GetByID($identifier)->GetNext();
			}
			else
			{
				$iblockTypeRecord = Type::getInstance()->findRecord($iblockXmlId);
				if ($iblockTypeRecord)
				{
					$identifier = $iblockTypeRecord->getValue();
					$iblockInfo = \CIBlockType::GetByID($identifier)->GetNext();
				}
			}
		}

		return $iblockInfo;
	}

	/**
	 * Проверяет, принадлежит ли настройка $optionName инфоблоку $iblockInfo.
	 * Проверка происходит по закодированному в названиии настройки <hash>.
	 *
	 * @param array $iblockInfo данные ИБ (ID и IBLOCK_TYPE_ID).
	 * @param string $optionName название настройки.
	 *
	 * @return bool true, если настройка принадлежит ИБ, иначе - false.
	 */
	protected function isOptionIblock(array $iblockInfo, $optionName)
	{
		$iblockId = $iblockInfo['ID'];
		$iblockTypeCode = $iblockInfo['IBLOCK_TYPE_ID'];
		$hash = $this->getOptionHash($optionName);
		$type = $this->getOptionType($optionName);

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
	 * Возвращает типы настроек, поле VALUE которых подлежит конвертации в формат выгрузки (xml).
	 *
	 * @return array типы конвертируемых настроек.
	 */
	protected function getConvertibleOptionTypes()
	{
		$optionTypes = array_keys(static::OPTION_NAME_PREFIXES);
		$nonConvertibleOptionTypes = array('IB_LIST_ADMIN', 'IB_LIST', 'IB_PROPERTIES_LIST');

		return array_diff($optionTypes, $nonConvertibleOptionTypes);
	}

	/**
	 * Возвращает типы настроек для ИБ.
	 * <hash> таких настроек содержит id ИБ.
	 *
	 * @return array типы настроек для ИБ.
	 */
	protected function getIblockOptionTypes()
	{
		$optionTypes = array_keys(static::OPTION_NAME_PREFIXES);
		$iblockTypeOptionTypes = array('IB_LIST_ADMIN', 'IB_LIST');

		return array_diff($optionTypes, $iblockTypeOptionTypes);
	}

	/**
	 * Проверяет, является ли настройка $option конвертируемой.
	 *
	 * @param array $option данные настройки.
	 *
	 * @return bool true, если настройка конвертируемая, иначе - false.
	 */
	protected function isConvertibleOption(array $option)
	{
		$optionType = $this->getOptionType($option['NAME']);
		$convertibleOptionTypes = $this->getConvertibleOptionTypes();

		return in_array($optionType, $convertibleOptionTypes);
	}

	/**
	 * Проверяет, привязана ли настройка к определенному ИБ
	 * (если нет, то привязана к типу ИБ)
	 *
	 * @param string $optionName название настройки.
	 *
	 * @return bool true, если настройка привязана к ИБ, иначе - false.
	 */
	protected function isOptionForIblock($optionName)
	{
		$optionType = $this->getOptionType($optionName);

		return ($optionType === 'IB_LIST_ADMIN' || $optionType === 'IB_LIST')
			? false
			: true;
	}

	/**
	 * Возвращает тип настройки.
	 *
	 * @param string $optionName название настройки (поле NAME настройки).
	 *
	 * @return string тип настройки.
	 */
	protected function getOptionType($optionName)
	{
		$isMatch = preg_match(static::OPTION_NAME_REGEX, $optionName, $matches);
		if ($isMatch && $matches[1])
		{
			$prefix = $matches[1];
			$key = array_search($prefix, static::OPTION_NAME_PREFIXES);

			return $key !== false ? $key : '';
		}

		return '';
	}

	/**
	 * Возвращает хэш названия настройки.
	 *
	 * @param string $optionName название настройки (поле NAME настройки).
	 *
	 * @return string хэш названия настройки.
	 */
	protected function getOptionHash($optionName)
	{
		$isMatch = preg_match(static::OPTION_NAME_REGEX, $optionName, $matches);
		if ($isMatch && $matches[2])
		{
			return $matches[2];
		}

		return '';
	}

	/**
	 * Проверяет, является ли $option настройкой отображения:
	 *    - поле CATEGORY должно соответствовать одному из OPTION_CATEGORIES
	 *    - префикс поля NAME должен соответствовать одному на OPTION_NAME_PREFIXES.
	 *
	 * @param array $option настройка.
	 *
	 * @return bool true, если $option - настройка отображения, иначе - false.
	 */
	protected static function isOption($option)
	{
		return static::isOptionCategory($option['CATEGORY'])
			   && static::isOptionName($option['NAME']);
	}

	/**
	 * Проверяет, является ли категория настройки $optionCategory
	 * категорией настроек отображения.
	 *
	 * @param string $optionCategory категория настройки (поле CATEGORY настройки).
	 *
	 * @return bool true, если $optionCategory - категория настроек отображения, иначе - false.
	 */
	protected static function isOptionCategory($optionCategory)
	{
		return in_array($optionCategory, static::OPTION_CATEGORIES);
	}

	/**
	 * Проверяет, является ли название настройки $optionName
	 * названием настроек отображения.
	 *
	 * @param string $optionName название настройки (поле NAME настройки).
	 *
	 * @return bool true, если $optionName - название настроек отображения, иначе - false.
	 */
	protected static function isOptionName($optionName)
	{
		foreach (static::OPTION_NAME_PREFIXES as $optionNamePrefix)
		{
			if (strpos($optionName, $optionNamePrefix) === 0)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Проверяет, что строка $string является названием свойства ИБ.
	 *
	 * @param string $string проверяемая строка.
	 *
	 * @return bool true, если $string - название свойства ИБ, иначе - false.
	 */
	protected function isIblockProperty($string)
	{
		$isMatch = preg_match(static::IBLOCK_PROPERTY_NAME_REGEX, $string, $matches);
		return ($isMatch && $matches[1]);
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
		return $dbRes->Fetch() ?: array();
	}

	/**
	 * Проверяет, что строка $string является названием UF-поля.
	 *
	 * @param string $string проверяемая строка.
	 *
	 * @return bool true, если $string - название UF-поля, иначе - false.
	 */
	protected function isUfField($string)
	{
		$length = strlen(static::UF_FIELD_PREFIX);

		return (substr($string, 0, $length) === static::UF_FIELD_PREFIX);
	}
}