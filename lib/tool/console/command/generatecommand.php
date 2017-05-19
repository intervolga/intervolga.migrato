<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\TypeTable;
use Bitrix\Main\Localization\CultureTable;
use Bitrix\Main\Localization\LanguageTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Mail\Internal\EventTypeTable;
use Bitrix\Main\SiteTable;
use Bitrix\Main\SiteTemplateTable;
use Bitrix\Main\Loader;
use Bitrix\Main\UserFieldTable;
use Bitrix\Sale\Internals\OrderPropsTable;
use Bitrix\Sale\Internals\PersonTypeTable;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Console\Logger;

Loc::loadMessages(__FILE__);

class GenerateCommand extends BaseCommand
{
	protected $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	protected function configure()
	{
		$this->setName('generate');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.GENERATE_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->createMainGroup();
		$this->createMainCulture();
		$this->createMainLanguage();
		//TODO $this->createMainSite();
		//TODO $this->createMainSiteTemplate();
		$this->createMainEventType();
		$this->createMainEvent();

		if (Loader::IncludeModule('iblock'))
		{
			$this->createIBlockType();
			$this->createIBlockIBlock();
			$this->createUserField('IBLOCK_#ENTITY#_SECTION');
			$this->createUserFieldEnum('iblock', 'iblockFieldEnumFilter');
			$this->createIBlockProperty();
		}
		if (Loader::IncludeModule('highloadblock'))
		{
			//TODO $this->createHighLoadBlock();
			$this->createUserField('HLBLOCK_#ENTITY#');
			$this->createUserFieldEnum('highloadblock', 'hlblockFieldEnumFilter');
		}

		if (Loader::IncludeModule('sale'))
		{
			$this->createSalePersonType();
			$this->createSalePropertyGroup();
			$this->createSaleProperty();
		}

		if (Loader::IncludeModule('catalog'))
		{
			$this->createCatalogPriceType();
		}
	}

	/**
	 * @param int $count
	 */
	protected function createMainGroup($count = 2)
	{
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$group = new \CGroup();
				$name = $this->generateRandom('STRING0-10');
				$id = $group->add(array(
					'ACTIVE' => $this->generateRandom('STRING_BOOL'),
					'C_SORT' => $this->generateRandom('NUMBER0-100'),
					'NAME' => $name,
					'DESCRIPTION' => $this->generateRandom('TEXT0-100'),
					'STRING_ID' => $name,
				));
				if ($id)
				{
					$this->reportCreated('main', 'group', $id);
				}
				else
				{
					$this->reportErrors('main', 'group', array($group->LAST_ERROR));
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('main', 'group', $exp);
			}
		}
	}

	public function generateRandom($randomType, $list = array())
	{
		$count = intval(preg_replace('/.*\-/', '', $randomType));
		$result = '';
		if ($randomType == 'STRING_BOOL')
		{
			$result = rand(0, 1) ? 'Y' : 'N';
		}
		elseif (strstr($randomType, 'STRING') !== false)
		{
			for ($i = 0; $i < $count; $i++)
			{
				$result .= $this->characters[rand(0, strlen($this->characters) - 1)];
			}
		}
		elseif (strstr($randomType, 'TEXT') !== false)
		{
			for ($i = 0; $i < $count; $i++)
			{
				$result .= $this->characters[rand(0, strlen($this->characters) - 1)];
			}
			$result = implode(' ', str_split($result, rand(3, 9)));
		}
		elseif (strstr($randomType, 'NUMBER') !== false)
		{
			$result = rand(0, $count);
		}
		elseif (strstr($randomType, 'BOOL') !== false)
		{
			$result = !!rand(0, 1);
		}
		elseif ($randomType == 'FROM_LIST')
		{
			$result = $list[rand(0, count($list) - 1)];
		}

		return $result;
	}

	/**
	 * @param string $module
	 * @param string $entity
	 * @param $id
	 */
	protected function reportCreated($module, $entity, $id)
	{
		$this->logger->addDb(
			array(
				'MODULE_NAME' => $module,
				'ENTITY_NAME' => $entity,
				'ID' => RecordId::createStringId($id),
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_CREATE'),
			),
			Logger::TYPE_OK
		);
	}

	/**
	 * @param string $module
	 * @param string $entity
	 * @param array $errors
	 */
	protected function reportErrors($module, $entity, array $errors = array())
	{
		$this->logger->addDb(
			array(
				'MODULE_NAME' => $module,
				'ENTITY_NAME' => $entity,
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_CREATE'),
				'RESULT' => Logger::TYPE_FAIL,
				'COMMENT' => implode(', ', $errors),
			),
			Logger::TYPE_FAIL
		);
	}

	/**
	 * @param string $module
	 * @param string $entity
	 * @param \Exception $exception
	 */
	protected function reportException($module, $entity, \Exception $exception = null)
	{
		$this->logger->addDb(
			array(
				'MODULE_NAME' => $module,
				'ENTITY_NAME' => $entity,
				'EXCEPTION' => $exception,
				'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_CREATE'),
			),
			Logger::TYPE_FAIL
		);
	}

	/**
	 * @param int $count
	 */
	protected function createMainCulture($count = 2)
	{
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$name = strtolower($this->generateRandom('STRING0-2'));
				$id = CultureTable::add(array(
					'NAME' => $name,
					'CODE' => $name,
					'FORMAT_DATE' => 'MM/DD/YYYY',
					'FORMAT_DATETIME' => 'MM/DD/YYYY H:MI:SS T',
					'FORMAT_NAME' => '#NAME# #LAST_NAME#',
					'WEEK_START' => $this->generateRandom('NUMBER0-6'),
					'CHARSET' => 'UTF-8',
					'DIRECTION' => $this->generateRandom('STRING_BOOL'),
				));
				if ($id->isSuccess())
				{
					$this->reportCreated('main', 'culture', $id->getId());
				}
				else
				{
					$this->reportErrors('main', 'culture', $id->getErrorMessages());
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('main', 'culture', $exp);
			}
		}
	}

	/**
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createMainLanguage($count = 2)
	{
		$cultures = $this->collectIds(CultureTable::getList(array('select' => array('ID'))));
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$id = LanguageTable::add(array(
					'LID' => $this->generateRandom('STRING0-2'),
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'DEF' => $this->generateRandom('STRING_BOOL'),
					'ACTIVE' => $this->generateRandom('STRING_BOOL'),
					'NAME' => $this->generateRandom('STRING0-10'),
					'CULTURE_ID' => $this->generateRandom('FROM_LIST', $cultures),
				));
				if ($id->isSuccess())
				{
					$this->reportCreated('main', 'language', $id->getId());
				}
				else
				{
					$this->reportErrors('main', 'language', $id->getErrorMessages());
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('main', 'language', $exp);
			}
		}
	}

	/**
	 * @param \Bitrix\Main\DB\Result|\CDBResult $rsCollection
	 * @param string $field
	 *
	 * @return array
	 */
	public function collectIds($rsCollection, $field = 'ID')
	{
		$ids = array();
		while ($arItem = $rsCollection->Fetch())
		{
			$ids[] = $arItem[$field];
		}

		return $ids;
	}

	/**
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createMainEventType($count = 2)
	{
		$languages = $this->collectIds(LanguageTable::getList(array('select' => array('ID'))));
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$et = new \CEventType();
				$name = $this->generateRandom('STRING0-10');
				$id = $et->add(array(
					'LID' => $this->generateRandom('FROM_LIST', $languages),
					'EVENT_NAME' => $name,
					'NAME' => $name,
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'DESCRIPTION' => $this->generateRandom('TEXT0-50'),
				));
				if ($id)
				{
					$this->reportCreated('main', 'eventtype', $id);
				}
				else
				{
					global $APPLICATION;
					$this->reportErrors(
						'main',
						'eventtype',
						array(
							$APPLICATION->GetException()->GetString(),
						)
					);
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('main', 'eventtype', $exp);
			}
		}
	}

	/**
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createMainEvent($count = 2)
	{
		$eventTypes = $this->collectIds(EventTypeTable::getList(array('select' => array('EVENT_NAME'))), 'EVENT_NAME');
		$sites = $this->collectIds(SiteTable::getList(array('select' => array('LID'))), 'LID');
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$et = new \CEventMessage();
				$fields = array(
					'EVENT_NAME' => $this->generateRandom('FROM_LIST', $eventTypes),
					'LID' => array($this->generateRandom('FROM_LIST', $sites)),
					'ACTIVE' => $this->generateRandom('STRING_BOOL'),
					'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
					'EMAIL_TO' => '#DEFAULT_EMAIL_FROM#',
					'BODY_TYPE' => 'text',
					'SUBJECT' => $this->generateRandom('TEXT0-20'),
					'MESSAGE' => $this->generateRandom('TEXT0-200'),
				);
				$id = $et->add($fields);
				if ($id)
				{
					$this->reportCreated('main', 'event', $id);
				}
				else
				{
					global $APPLICATION;
					$this->reportErrors(
						'main',
						'event',
						array(
							$APPLICATION->GetException()->GetString(),
						)
					);
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('main', 'event', $exp);
			}
		}
	}

	/**
	 * @param int $count
	 */
	protected function createIBlockType($count = 1)
	{
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$obBlocktype = new \CIBlockType();
				$id = $this->generateRandom('STRING0-10');
				$id = $obBlocktype->add(array(
					'ID' => $this->generateRandom('STRING0-10'),
					'SECTIONS' => $this->generateRandom('STRING_BOOL'),
					'IN_RSS' => $this->generateRandom('STRING_BOOL'),
					'SORT' => $this->generateRandom('NUMBER0-1000'),
					'LANG' => Array(
						'en' => Array(
							'NAME' => $id,
							'SECTION_NAME' => 'Sections',
							'ELEMENT_NAME' => 'Products',
						),
					),
				));
				if ($id)
				{
					$this->reportCreated('iblock', 'type', $id);
				}
				else
				{
					$this->reportErrors('iblock', 'type', array($obBlocktype->LAST_ERROR));
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('iblock', 'type', $exp);
			}
		}
	}

	/**
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createIBlockIBlock($count = 1)
	{
		$types = $this->collectIds(TypeTable::getList(array('select' => array('ID'))));
		$sites = $this->collectIds(SiteTable::getList(array('select' => array('LID'))), 'LID');
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$obBlock = new \CIBlock();
				$name = $this->generateRandom('STRING0-10');
				$arField = array(
					'IBLOCK_TYPE_ID' => $this->generateRandom('FROM_LIST', $types),
					'SITE_ID' => array($this->generateRandom('FROM_LIST', $sites)),
					'CODE' => $name,
					'NAME' => $name,
					'ACTIVE' => $this->generateRandom('STRING_BOOL'),
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'DESCRIPTION' => $this->generateRandom('TEXT0-200'),
					'DESCRIPTION_TYPE' => 'text',
					'RSS_ACTIVE' => 'N',
					'INDEX_ELEMENT' => $this->generateRandom('STRING_BOOL'),
					'INDEX_SECTION' => $this->generateRandom('STRING_BOOL'),
				);
				$id = $obBlock->add($arField);
				if ($id)
				{
					$this->reportCreated('iblock', 'iblock', $id);
				}
				else
				{
					$this->reportErrors('iblock', 'iblock', array($obBlock->LAST_ERROR));
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('iblock', 'iblock', $exp);
			}
		}
	}

	/**
	 * @param string $entity
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createUserField($entity, $count = 1)
	{
		$iblocks = $this->collectIds(IblockTable::getList(array('select' => array('ID'))));
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$obIBlockField = new \CUserTypeEntity();
				$name = strtoupper($this->generateRandom('STRING0-10'));
				$id = $obIBlockField->add(array(
					'FIELD_NAME' => 'UF_' . $name,
					'XML_ID' => $name,
					'ENTITY_ID' => str_replace('#ENTITY#', $this->generateRandom('FROM_LIST', $iblocks), $entity),
					'MANDATORY' => $this->generateRandom('STRING_BOOL'),
					'ACTIVE' => $this->generateRandom('STRING_BOOL'),
					'MULTIPLE' => 'N',
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'USER_TYPE_ID' => $this->generateRandom('FROM_LIST', array('enumeration', 'double', 'integer', 'boolean', 'string')),
					'EDIT_FORM_LABEL' => array(
						'ru' => $this->generateRandom('TEXT0-10'),
						'en' => $this->generateRandom('TEXT0-10'),
					),
					'LIST_COLUMN_LABEL' => array(
						'ru' => $this->generateRandom('TEXT0-10'),
						'en' => $this->generateRandom('TEXT0-10'),
					),
					'LIST_FILTER_LABEL' => array(
						'ru' => $this->generateRandom('TEXT0-10'),
						'en' => $this->generateRandom('TEXT0-10'),
					),
					'ERROR_MESSAGE' => array(
						'ru' => $this->generateRandom('TEXT0-10'),
						'en' => $this->generateRandom('TEXT0-10'),
					),
					'HELP_MESSAGE' => array(
						'ru' => $this->generateRandom('TEXT0-10'),
						'en' => $this->generateRandom('TEXT0-10'),
					),
				));
				if ($id)
				{
					$this->reportCreated('iblock', 'field', $id);
				}
				else
				{
					global $APPLICATION;
					$this->reportErrors(
						'iblock',
						'field',
						array(
							$APPLICATION->GetException()->GetString(),
						)
					);
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('iblock', 'field', $exp);
			}
		}
	}

	/**
	 * @param string $module
	 * @param array $filter
	 */
	protected function createUserFieldEnum($module, $filter)
	{
		try
		{
			$userFields = array();
			$rsFields = UserFieldTable::getList(array(
					'filter' => array('USER_TYPE_ID' => 'enumeration'),
					'select' => array('ID', 'ENTITY_ID', 'FIELD_NAME'),
				)
			);
			while ($arField = $rsFields->fetch())
			{
				$userFields[] = $arField;
			}

			$userFields = array_diff(array_map($filter, $userFields), array(null));

			if (count($userFields) > 0)
			{
				$obEnum = new \CUserFieldEnum();
				$arAddEnum = array();
				$count = rand(1, 3);
				for ($i = 0; $i < $count; $i++)
				{
					$value = $this->generateRandom('STRING0-10');
					$arAddEnum['n' . $i] = array(
						'XML_ID' => $value,
						'VALUE' => $value,
						'DEF' => $this->generateRandom('STRING_BOOL'),
						'SORT' => $this->generateRandom('NUMBER0-1000'),
					);
				}
				$ufFieldId = $this->generateRandom('FROM_LIST', $userFields);
				$result = $obEnum->SetEnumValues($ufFieldId, $arAddEnum);
				if ($result)
				{
					$this->reportCreated($module, 'fieldenum', '?');
				}
				else
				{
					global $APPLICATION;
					$this->reportErrors(
						$module,
						'fieldenum',
						array(
							$APPLICATION->GetException()->GetString(),
						)
					);
				}
			}
		}
		catch (\Exception $exp)
		{
			$this->reportException($module, 'fieldenum', $exp);
		}
	}

	/**
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createIBlockProperty($count = 1)
	{
		$iblocks = $this->collectIds(IblockTable::getList(array('select' => array('ID'))));
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$obIBlockProperty = new \CIBlockProperty();
				$name = $this->generateRandom('STRING0-10');
				$arField = array(
					'CODE' => $name,
					'NAME' => $name,
					'XML_ID' => $name,
					'IBLOCK_ID' => $this->generateRandom('FROM_LIST', $iblocks),
					'IS_REQUIRED' => $this->generateRandom('STRING_BOOL'),
					'ACTIVE' => $this->generateRandom('STRING_BOOL'),
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'PROPERTY_TYPE' => $this->generateRandom('FROM_LIST', array('S', 'N', 'L')),
					'WITH_DESCRIPTION' => $this->generateRandom('STRING_BOOL'),
				);
				if ($arField['PROPERTY_TYPE'] == 'L')
				{
					$count = rand(1, 3);
					for ($i = 0; $i < $count; $i++)
					{
						$value = $this->generateRandom('STRING0-10');
						$arField['VALUES'][$i] = array(
							'VALUE' => $value,
							'XML_ID' => $value,
							'DEF' => $this->generateRandom('STRING_BOOL'),
							'SORT' => $this->generateRandom('NUMBER0-1000'),
						);
					}
				}
				$id = $obIBlockProperty->add($arField);
				if ($id)
				{
					$this->reportCreated('iblock', 'property', $id);
				}
				else
				{
					$this->reportErrors('iblock', 'property', array($obIBlockProperty->LAST_ERROR));
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('iblock', 'property', $exp);
			}
		}
	}

	/**
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createSalePersonType($count = 1)
	{

		$sites = $this->collectIds(SiteTable::getList(array('select' => array('LID'))), 'LID');
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$object = new \CSalePersonType();
				$id = $object->add(array(
					'NAME' => $this->generateRandom('STRING0-10'),
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'ACTIVE' => $this->generateRandom('STRING_BOOL'),
					'LID' => $this->generateRandom('FROM_LIST', $sites),
				));
				if ($id)
				{
					$this->reportCreated('sale', 'personType', $id);
				}
				else
				{
					global $APPLICATION;
					$this->reportErrors(
						'sale',
						'personType',
						array(
							$APPLICATION->GetException()->GetString(),
						)
					);
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('sale', 'personType', $exp);
			}
		}
	}

	/**
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createSalePropertyGroup($count = 1)
	{
		$types = $this->collectIds(PersonTypeTable::getList(array('select' => array('ID'))));
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$object = new \CSaleOrderPropsGroup();
				$id = $object->add(array(
					'PERSON_TYPE_ID' => $this->generateRandom('FROM_LIST', $types),
					'NAME' => ucfirst(strtolower($this->generateRandom('STRING0-10'))),
					'SORT' => $this->generateRandom('NUMBER0-100'),
				));
				if ($id)
				{
					$this->reportCreated('sale', 'propertyGroup', $id);
				}
				else
				{
					global $APPLICATION;
					$this->reportErrors(
						'sale',
						'propertyGroup',
						array(
							$APPLICATION->GetException()->GetString(),
						)
					);
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('sale', 'propertyGroup', $exp);
			}
		}
	}

	/**
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createSaleProperty($count = 1)
	{
		$types = $this->collectIds(PersonTypeTable::getList(array('select' => array('ID'))));
		$obPropsGroup = new \CSaleOrderPropsGroup;
		$propsGroup = $this->collectIds($obPropsGroup->getList(array(), array(), false, false, array('ID')));
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$name = ucfirst(strtolower($this->generateRandom('STRING0-10')));
				$type = $this->generateRandom('FROM_LIST', array('CHECKBOX', 'TEXT', 'TEXTAREA', 'RADIO'));
				$result = OrderPropsTable::add(array(
					'PERSON_TYPE_ID' => $this->generateRandom('FROM_LIST', $types),
					'PROPS_GROUP_ID' => $this->generateRandom('FROM_LIST', $propsGroup),
					'NAME' => $name,
					'CODE' => $name,
					'TYPE' => $type,
					'REQUIRED' => $this->generateRandom('STRING_BOOL'),
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'USER_PROPS' => $this->generateRandom('STRING_BOOL'),
					'IS_LOCATION' => 'N',
					'DESCRIPTION' => $this->generateRandom('TEXT0-100'),
					'IS_EMAIL' => $this->generateRandom('STRING_BOOL'),
					'IS_PROFILE_NAME' => $this->generateRandom('STRING_BOOL'),
					'IS_PAYER' => $this->generateRandom('STRING_BOOL'),
					'IS_LOCATION4TAX' => $this->generateRandom('STRING_BOOL'),
					'IS_FILTERED' => $this->generateRandom('STRING_BOOL'),
					'IS_ZIP' => $this->generateRandom('STRING_BOOL'),
					'IS_PHONE' => $this->generateRandom('STRING_BOOL'),
					'IS_ADDRESS' => $this->generateRandom('STRING_BOOL'),
					'ACTIVE' => $this->generateRandom('STRING_BOOL'),
					'UTIL' => $this->generateRandom('STRING_BOOL'),
					'MULTIPLE' => 'N',
				));
				if ($result->isSuccess())
				{
					$this->reportCreated('sale', 'property', $result->getId());
				}
				else
				{
					$this->reportErrors('sale', 'property', $result->getErrorMessages());
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('sale', 'property', $exp);
			}
		}

	}

	/**
	 * @param int $count
	 */
	protected function createCatalogPriceType($count = 1)
	{
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$object = new \CCatalogGroup();
				$name = strtoupper($this->generateRandom('STRING0-5'));
				$id = $object->add(array(
					'NAME' => $name,
					'XML_ID' => $name,
					'BASE' => 'N',
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'USER_GROUP' => array(1),
					'USER_GROUP_BUY' => array(1),
					'USER_LANG' => array(
						'en' => $this->generateRandom('STRING0-5'),
						'ru' => $this->generateRandom('STRING0-5'),
					),
				));
				if ($id)
				{
					$this->reportCreated('catalog', 'pricetype', $id);
				}
				else
				{
					global $APPLICATION;
					$this->reportErrors(
						'catalog',
						'pricetype',
						array(
							$APPLICATION->GetException()->GetString(),
						)
					);
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('catalog', 'pricetype', $exp);
			}
		}
	}

	/**
	 * @param $var
	 *
	 * @return string
	 */
	public function mainFieldEnumFilter($var)
	{
		if (strstr($var['ENTITY_ID'], 'IBLOCK') === false && strstr($var['ENTITY_ID'], 'HLBLOCK') === false)
		{
			return $var['ID'];
		}
		else
		{
			return '';
		}
	}

	/**
	 * @param $var
	 *
	 * @return string
	 */
	public function iblockFieldEnumFilter($var)
	{
		if (strstr($var['ENTITY_ID'], 'IBLOCK') !== false)
		{
			return $var['ID'];
		}
		else
		{
			return '';
		}
	}

	/**
	 * @param int $count
	 */
	protected function createMainSite($count = 1)
	{
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$lid = strtolower($this->generateRandom('STRING0-2'));
				$result = SiteTable::add(array(
					'LID' => $lid,
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'DEF' => $this->generateRandom('STRING_BOOL'),
					'ACTIVE' => $this->generateRandom('STRING_BOOL'),
					'NAME' => $this->generateRandom('TEXT0-20'),
					'DIR' => '/' . $this->generateRandom('STRING0-6'),
					'DOMAIN_LIMITED' => 'N',
					'SITE_NAME' => $this->generateRandom('TEXT0-20'),
				));
				if ($result->isSuccess())
				{
					$this->reportCreated('main', 'site', $result->getId());
				}
				else
				{
					$this->reportErrors('main', 'site', $result->getErrorMessages());
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('main', 'site', $exp);
			}
		}
	}

	/**
	 * @param int $count
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function createMainSiteTemplate($count = 1)
	{
		$sites = $this->collectIds(SiteTable::getList(array('select' => array('LID'))), 'LID');
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$result = SiteTemplateTable::add(array(
					'SITE_ID' => $this->generateRandom('FROM_LIST', $sites),
					'CONDITION' => '',
					'SORT' => $this->generateRandom('NUMBER0-100'),
					'TEMPLATE' => $this->generateRandom('STRING0-15'),
				));
				if ($result->isSuccess())
				{
					$this->reportCreated('main', 'siteTemplate', $result->getId());
				}
				else
				{
					$this->reportErrors('main', 'siteTemplate', $result->getErrorMessages());
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('main', 'siteTemplate', $exp);
			}
		}
	}

	/**
	 * @param int $count
	 */
	protected function createHighLoadBlock($count = 1)
	{
		for ($i = 0; $i < $count; $i++)
		{
			try
			{
				$name = $this->generateRandom('STRING0-10');
				$result = HighloadBlockTable::add(array(
					'NAME' => ucfirst(strtolower($name)),
					'TABLE_NAME' => strtolower($name),
				));

				if ($result->isSuccess())
				{
					$this->reportCreated('hlblock', 'hlblock', $result->getId());
				}
				else
				{
					$this->reportErrors('hlblock', 'hlblock', $result->getErrorMessages());
				}
			}
			catch (\Exception $exp)
			{
				$this->reportException('hlblock', 'hlblock', $exp);
			}
		}
	}
}