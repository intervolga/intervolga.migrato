<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\TypeTable;
use Bitrix\Main\Localization\CultureTable;
use Bitrix\Main\Localization\LanguageTable;
use Bitrix\Main\Mail\Internal\EventTypeTable;
use Bitrix\Main\SiteTable;
use Bitrix\Main\SiteTemplateTable;
use Bitrix\Main\Loader;
use Bitrix\Main\UserFieldTable;
use Bitrix\Sale\Internals\OrderPropsTable;
use Bitrix\Sale\Internals\OrderPropsVariantTable;
use Bitrix\Sale\Internals\PersonTypeTable;

class GenerationData extends BaseProcess
{
	public static function run()
	{
		parent::run();
		static::createMainGroup();
		static::createMainCulture();
		static::createMainLanguage();
		static::createMainSite();
		static::createMainSiteTemplate();
		static::createMainEventType();
		static::createMainEvent();

		if(Loader::IncludeModule("iblock"))
		{
			static::createIBlockType();
			static::createIBlockIBlock();
			static::createUserField("IBLOCK_#ENTITY#_SECTION");
			static::createUserFieldEnum("iblock", "static::iblockFieldEnumFilter");
			static::createIBlockProperty();
		}
		if(Loader::IncludeModule("highloadblock"))
		{
			static::createHighLoadBlock();
			if(Loader::IncludeModule("iblock"))
			{
				static::createUserField("HLBLOCK_#ENTITY#");
			}
			static::createUserFieldEnum("highloadblock", "static::hlblockFieldEnumFilter");
		}

		if(Loader::IncludeModule("sale"))
		{
			static::createSalePersonType();
			static::createSalePropertyGroup();
			static::createSaleProperty();
			static::createSalePropertyVariant();
		}

		if(Loader::IncludeModule("catalog"))
		{
			static::createCatalogPriceType();
		}

		parent::finalReport();
	}

	/********************************************************** Main ***************************************************/

	public static function createMainGroup($count = 2)
	{
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$group = new \CGroup();
				$name = static::generateRandom("STRING0-10");
				$id = $group->Add(array(
					"ACTIVE"       => static::generateRandom("STRING_BOOL"),
					"C_SORT"       => static::generateRandom("NUMBER0-100"),
					"NAME"         => $name,
					"DESCRIPTION"  => static::generateRandom("TEXT0-100"),
					"STRING_ID"    => $name
				));
				static::report("main:group №" . $i, $id ? "ok" : "fail");
			} catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(),"warning");
			}
		}
	}

	public static function createMainCulture($count = 2) {
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$name = strtolower(static::generateRandom("STRING0-2"));
				$id = CultureTable::add(array(
					"NAME"              => $name,
					"CODE"              => $name,
					"FORMAT_DATE"       => "MM/DD/YYYY",
					"FORMAT_DATETIME"   => "MM/DD/YYYY H:MI:SS T",
					"FORMAT_NAME"       => "#NAME# #LAST_NAME#",
					"WEEK_START"        => static::generateRandom("NUMBER0-6"),
					"CHARSET"           => "UTF-8",
					"DIRECTION"         => static::generateRandom("BOOL"),
				));
				static::report("main:culture №" . $i, $id->isSuccess() ? "ok" : "fail");
			} catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(),"warning");
			}
		}
	}

	public static function createMainLanguage($count = 2)
	{
		static::startStep(__FUNCTION__);
		$cultures = static::collectIds(CultureTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$id = LanguageTable::add(array(
					'LID'       => static::generateRandom("STRING0-2"),
					'SORT'      => static::generateRandom("NUMBER0-100"),
					'DEF'       => static::generateRandom("STRING_BOOL"),
					'ACTIVE'    => static::generateRandom("STRING_BOOL"),
					'NAME'      => static::generateRandom("STRING0-10"),
					'CULTURE_ID'=> static::generateRandom("FROM_LIST", $cultures),
				));
				static::report("main:culture №" . $i, $id ? "ok" : "fail");
			} catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(),"warning");
			}
		}
	}

	public static function createMainSite($count = 1)
	{
		static::startStep(__FUNCTION__);
		$cultures = static::collectIds(CultureTable::getList(array("select" => array("ID"))));
		$languages = static::collectIds(LanguageTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$lid = strtolower(static::generateRandom("STRING0-2"));
				$result = SiteTable::add(array(
					"LID" => $lid,
					'SORT' => static::generateRandom("NUMBER0-100"),
					'DEF' => static::generateRandom("STRING_BOOL"),
					'ACTIVE' => static::generateRandom("STRING_BOOL"),
					'NAME' => static::generateRandom("TEXT0-20"),
					'DIR' => "/" . static::generateRandom("STRING0-6"),
					'DOMAIN_LIMITED' => "N",
					'SERVER_NAME' => "khodnenko1.ivserv1.tmweb.ru",
					'SITE_NAME' => static::generateRandom("TEXT0-20"),
					'CULTURE_ID' => static::generateRandom("FROM_LIST", $cultures),
					'LANGUAGE_ID' => static::generateRandom("FROM_LIST", $languages),
				));
				static::report("main:site №" . $i, $result->isSuccess() ? "ok" : "fail");
			} catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(),"warning");
			}
		}
	}

	public static function createMainSiteTemplate($count = 1)
	{
		static::startStep(__FUNCTION__);
		$sites = static::collectIds(SiteTable::getList(array("select" => array("LID"))), "LID");
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$result = SiteTemplateTable::add(array(
					"SITE_ID"   => static::generateRandom("FROM_LIST", $sites),
					"CONDITION" => "",
					"SORT"      => static::generateRandom("NUMBER0-100"),
					"TEMPLATE"  => static::generateRandom("STRING0-15"),
				));
				static::report("main:siteTemplate №" . $i, $result->isSuccess() ? "ok" : "fail");
			} catch(\Exception $exp) {
				static::report("Exception: " . $exp->getMessage(),"warning");
			}
		}
	}

	public static function createMainEventType($count = 2)
	{
		static::startStep(__FUNCTION__);
		$languages = static::collectIds(LanguageTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$et = new \CEventType();
				$name = static::generateRandom("STRING0-10");
				$id = $et->Add(array(
					"LID" => static::generateRandom("FROM_LIST", $languages),
					"EVENT_NAME" => $name,
					"NAME" => $name,
					"SORT" => static::generateRandom("NUMBER0-100"),
					"DESCRIPTION" => static::generateRandom("TEXT0-50")
				));
				static::report("main:eventtype №" . $i, $id ? "ok" : "fail");
			} catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(), "warning");
			}
		}
	}

	public static function createMainEvent($count = 2)
	{
		static::startStep(__FUNCTION__);
		$eventTypes = static::collectIds(EventTypeTable::getList(array("select" => array("EVENT_NAME"))), "EVENT_NAME");
		$sites = static::collectIds(SiteTable::getList(array("select" => array("LID"))), "LID");
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$et = new \CEventMessage();
				$fields = array(
					"EVENT_NAME"    => static::generateRandom("FROM_LIST", $eventTypes),
					"LID"           => array(static::generateRandom("FROM_LIST", $sites)),
					"ACTIVE"        => static::generateRandom("STRING_BOOL"),
					"EMAIL_FROM"    => "#DEFAULT_EMAIL_FROM#",
					"EMAIL_TO"      => "#DEFAULT_EMAIL_FROM#",
					"BODY_TYPE"     => "text",
					"SUBJECT"       => static::generateRandom("TEXT0-20"),
					"MESSAGE"       => static::generateRandom("TEXT0-200"),
				);
				$id = $et->Add($fields);
				static::report("main:event №" . $i, $id ? "ok" : "fail");
			} catch(\Exception $exp) {
				static::report("Exception: " . $exp->getMessage(),"warning");
			}
		}
	}

	public static function mainFieldEnumFilter($var)
	{
		if(strstr($var["ENTITY_ID"], "IBLOCK") === false && strstr($var["ENTITY_ID"], "HLBLOCK") === false)
			return $var["ID"];
	}

	/******************************************************** Iblock ***************************************************/

	public static function createIBlockType($count = 1)
	{
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$obBlocktype = new \CIBlockType();
				$id = static::generateRandom("STRING0-10");
				$id = $obBlocktype->Add(array(
					'ID' => static::generateRandom("STRING0-10"),
					'SECTIONS' => static::generateRandom("STRING_BOOL"),
					'IN_RSS' => static::generateRandom("STRING_BOOL"),
					'SORT' => static::generateRandom("NUMBER0-1000"),
					'LANG' => Array(
						'en' => Array(
							'NAME' => $id,
							'SECTION_NAME' => 'Sections',
							'ELEMENT_NAME' => 'Products'
						)
					)
				));
				static::report("iblock:type №" . $i, $id ? "ok" : "fail");
			}
			catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(),"warning");
			}
		}
	}


	public static function createIBlockIBlock($count = 1)
	{
		static::startStep(__FUNCTION__);
		$types = static::collectIds(TypeTable::getList(array("select" => array("ID"))));
		$sites = static::collectIds(SiteTable::getList(array("select" => array("LID"))), "LID");
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$obBlock = new \CIBlock();
				$name = static::generateRandom("STRING0-10");
				$arField = array(
					"IBLOCK_TYPE_ID" => static::generateRandom("FROM_LIST", $types),
					"SITE_ID" => array(static::generateRandom("FROM_LIST", $sites)),
					"CODE" => $name,
					"NAME" => $name,
					"ACTIVE" => static::generateRandom("STRING_BOOL"),
					"SORT" => static::generateRandom("NUMBER0-100"),
					"DESCRIPTION" => static::generateRandom("TEXT0-200"),
					"DESCRIPTION_TYPE" => "text",
					"RSS_ACTIVE" => "N",
					"INDEX_ELEMENT" => static::generateRandom("STRING_BOOL"),
					"INDEX_SECTION" => static::generateRandom("STRING_BOOL"),
				);
				$id = $obBlock->Add($arField);
				static::report("iblock:iblock №" . $i, $id ? "ok" : "fail");
			}
			catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(), "warning");
			}
		}
	}

	public static function createIBlockProperty($count = 1)
	{
		static::startStep(__FUNCTION__);
		$iblocks = static::collectIds(IblockTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$obIBlockProperty = new \CIBlockProperty();
				$name = static::generateRandom("STRING0-10");
				$arField = array (
					"CODE" => $name,
					"NAME" => $name,
					"XML_ID" => $name,
					"IBLOCK_ID" => static::generateRandom("FROM_LIST", $iblocks),
					"IS_REQUIRED" => static::generateRandom("STRING_BOOL"),
					"ACTIVE" => static::generateRandom("STRING_BOOL"),
					"SORT" => static::generateRandom("NUMBER0-100"),
					"PROPERTY_TYPE" => static::generateRandom("FROM_LIST", array("S", "N", "L")),
					"WITH_DESCRIPTION" => static::generateRandom("STRING_BOOL"),
				);
				if($arField["PROPERTY_TYPE"] == "L")
				{
					$count = rand(1, 3);
					for($i = 0; $i < $count; $i++)
					{
						$value = static::generateRandom("STRING0-10");
						$arField["VALUES"][$i] = array(
							"VALUE"     => $value,
							"XML_ID"    => $value,
							"DEF"       => static::generateRandom("STRING_BOOL"),
							"SORT"      => static::generateRandom("NUMBER0-1000"),
						);
					}
				}
				$id = $obIBlockProperty->Add($arField);
				static::report("iblock:property №" . $i, $id ? "ok" : "fail");
			}
			catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(), "warning");
			}
		}
	}

	public static function iblockFieldEnumFilter($var)
	{
		if (strstr($var["ENTITY_ID"], "IBLOCK") !== false)
			return $var["ID"];
	}

	/************************************************** Highloadblock ***************************************************/

	public static function createHighLoadBlock($count = 1)
	{
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$name = static::generateRandom("STRING0-10");
				$result = HighloadBlockTable::add(array(
					"NAME" => ucfirst(strtolower($name)),
					"TABLE_NAME" => strtolower($name),
				));

				static::report("hlblock:hlblock №" . $i, $result->isSuccess() ? "ok" : "fail");
				if(!$result->isSuccess())
				{
					static::report(implode(", ", $result->getErrorMessages()), "fail");
				}
			}
			catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(), "warning");
			}
		}

	}

	public static function hlblockFieldEnumFilter($var)
	{
		if (strstr($var["ENTITY_ID"], "HLBLOCK") !== false)
			return $var["ID"];
	}

	/******************************************************** Sale *****************************************************/

	public static function createSalePersonType($count = 1)
	{
		static::startStep(__FUNCTION__);
		$sites = static::collectIds(SiteTable::getList(array("select" => array("LID"))), "LID");
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$object = new \CSalePersonType();
				$id = $object->add(array(
					'NAME' => static::generateRandom("STRING0-10"),
					'SORT' => static::generateRandom("NUMBER0-100"),
					'ACTIVE' => static::generateRandom("STRING_BOOL"),
					'LID' => static::generateRandom("FROM_LIST", $sites),
				));
				static::report("sale:personType №" . $i, $id ? "ok" : "fail");
			}
			catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(), "warning");
			}
		}
	}

	public static function createSalePropertyGroup($count = 1)
	{
		static::startStep(__FUNCTION__);
		$types = static::collectIds(PersonTypeTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$object = new \CSaleOrderPropsGroup();
				$id = $object->add(array(
					"PERSON_TYPE_ID" => static::generateRandom("FROM_LIST", $types),
					"NAME" => ucfirst(strtolower(static::generateRandom("STRING0-10"))),
					"SORT" => static::generateRandom("NUMBER0-100"),
				));
				static::report("sale:propertyGroup №" . $i, $id ? "ok" : "fail");
			}
			catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(), "warning");
			}
		}
	}

	public static function createSaleProperty($count = 1)
	{
		static::startStep(__FUNCTION__);
		$types = static::collectIds(PersonTypeTable::getList(array("select" => array("ID"))));
		$obPropsGroup = new \CSaleOrderPropsGroup;
		$propsGroup = static::collectIds($obPropsGroup->getList(array(), array(), false, false, array("ID")));
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$name = ucfirst(strtolower(static::generateRandom("STRING0-10")));
				$type = static::generateRandom("FROM_LIST", array("CHECKBOX", "TEXT", "TEXTAREA", "RADIO", "ENUM"));
				$result = OrderPropsTable::add(array(
					"PERSON_TYPE_ID" => static::generateRandom("FROM_LIST", $types),
					"PROPS_GROUP_ID" => static::generateRandom("FROM_LIST", $propsGroup),
					"NAME" => $name,
					"CODE" => $name,
					"TYPE" => $type,
					"REQUIRED" => static::generateRandom("STRING_BOOL"),
					"SORT" => static::generateRandom("NUMBER0-100"),
					"USER_PROPS" => static::generateRandom("STRING_BOOL"),
					"IS_LOCATION" => "N",
					"DESCRIPTION" => static::generateRandom("TEXT0-100"),
					"IS_EMAIL" => static::generateRandom("STRING_BOOL"),
					"IS_PROFILE_NAME" => static::generateRandom("STRING_BOOL"),
					"IS_PAYER" => static::generateRandom("STRING_BOOL"),
					"IS_LOCATION4TAX" => static::generateRandom("STRING_BOOL"),
					"IS_FILTERED" => static::generateRandom("STRING_BOOL"),
					"IS_ZIP" => static::generateRandom("STRING_BOOL"),
					"IS_PHONE" => static::generateRandom("STRING_BOOL"),
					"IS_ADDRESS" => static::generateRandom("STRING_BOOL"),
					"ACTIVE" => static::generateRandom("STRING_BOOL"),
					"UTIL" => static::generateRandom("STRING_BOOL"),
					"MULTIPLE" => "N",
				));
				static::report("sale:property №" . $i, $result->isSuccess() ? "ok" : "fail");
			}
			catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(), "warning");
			}
		}

	}

	public static function createSalePropertyVariant($count = 1)
	{
		static::startStep(__FUNCTION__);
		$types = static::collectIds(OrderPropsTable::getList(array("select" => array("ID"), "filter" => array("TYPE" => "ENUM"))));
		if(count($types) > 0)
		{
			for($i = 0; $i < $count; $i++)
			{
				$propId = static::generateRandom("FROM_LIST", $types);
				$result = OrderPropsVariantTable::add(array(
					"ORDER_PROPS_ID" => $propId,
					"NAME" => static::generateRandom("STRING0-10"),
					"VALUE" => static::generateRandom("STRING0-10"),
					"SORT" => static::generateRandom("NUMBER0-100"),
					"DESCRIPTION" => static::generateRandom("TEXT0-100"),
				));
				static::report("sale:propertyVariant set prop with id=" . $propId, $result->isSuccess() ? "ok" : "fail");
				if (!$result->isSuccess())
				{
					throw new \Exception(implode("\r\n", $result->getErrorMessages()));
				}
			}
		}
		else
			static::report("sale:propertyVariant no property of ENUM", "warning");
	}

	/***************************************************** Catalog *****************************************************/

	public static function createCatalogPriceType($count = 1)
	{
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$object = new \CCatalogGroup();
				$name = strtoupper(static::generateRandom("STRING0-5"));
				$id = $object->add(array(
					"NAME" => $name,
					"XML_ID" => $name,
					"BASE" => "N",
					"SORT" => static::generateRandom("NUMBER0-100"),
					"USER_GROUP" => array(1),
					"USER_GROUP_BUY" => array(1),
					"USER_LANG" => array(
						"en" => static::generateRandom("STRING0-5"),
						"ru" => static::generateRandom("STRING0-5"),
					)
				));
				static::report("catalog:pricetype №" . $i, $id ? "ok" : "fail");
			}
			catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(), "warning");
			}
		}
	}

	/************************************************ Class methods ****************************************************/

	public static function createUserField($entity, $count = 1)
	{
		static::startStep(__FUNCTION__);
		$iblocks = static::collectIds(IblockTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			try
			{
				$obIBlockField = new \CUserTypeEntity();
				$name = strtoupper(static::generateRandom("STRING0-10"));
				$entityId = str_replace("#ENTITY#", static::generateRandom("FROM_LIST", $iblocks), $entity);
				$id = $obIBlockField->Add(array(
					"FIELD_NAME" => "UF_" . $name,
					"XML_ID" => $name,
					"ENTITY_ID" => $entityId,
					"MANDATORY" => static::generateRandom("STRING_BOOL"),
					"ACTIVE" => static::generateRandom("STRING_BOOL"),
					"MULTIPLE" => "N",
					"SORT" => static::generateRandom("NUMBER0-100"),
					"USER_TYPE_ID" => static::generateRandom("FROM_LIST", array("enumeration", "double", "integer", "boolean", "string")),
					'EDIT_FORM_LABEL' => array(
						'ru' => static::generateRandom("TEXT0-10"),
						'en' => static::generateRandom("TEXT0-10"),
					),
					'LIST_COLUMN_LABEL' => array(
						'ru' => static::generateRandom("TEXT0-10"),
						'en' => static::generateRandom("TEXT0-10"),
					),
					'LIST_FILTER_LABEL' => array(
						'ru' => static::generateRandom("TEXT0-10"),
						'en' => static::generateRandom("TEXT0-10"),
					),
					'ERROR_MESSAGE' => array(
						'ru' => static::generateRandom("TEXT0-10"),
						'en' => static::generateRandom("TEXT0-10"),
					),
					'HELP_MESSAGE' => array(
						'ru' => static::generateRandom("TEXT0-10"),
						'en' => static::generateRandom("TEXT0-10"),
					),
				));
				static::report($entityId . ":field №" . $i, $id ? "ok" : "fail");
			}
			catch(\Exception $exp)
			{
				static::report("Exception: " . $exp->getMessage(), "warning");
			}
		}
	}

	/**
	 * @param $module string
	 * @param int $count
	 */
	public static function createUserFieldEnum($module, $filter, $count = 1)
	{
		static::startStep(__FUNCTION__);
		try
		{
			$userFields = array();
			$rsFields = UserFieldTable::getList(array(
					"filter" => array("USER_TYPE_ID" => "enumeration"),
					"select" => array("ID", "ENTITY_ID"),
				)
			);
			while($arField = $rsFields->fetch())
				$userFields[] = $arField;

			$userFields = array_diff(array_map($filter, $userFields), array(null));

			if(count($userFields) > 0)
			{
				$obEnum = new \CUserFieldEnum();
				$arAddEnum = array();
				for($i = 0; $i < $count; $i++)
				{
					$value = static::generateRandom("STRING0-10");
					$arAddEnum['n' . $i] = array(
						'XML_ID' => $value,
						'VALUE' => $value,
						'DEF' => static::generateRandom("STRING_BOOL"),
						'SORT' => static::generateRandom("NUMBER0-1000"),
					);
				}
				$ufFieldId = static::generateRandom("FROM_LIST", $userFields);
				$obEnum->SetEnumValues($ufFieldId, $arAddEnum);
				static::report($module . ":fieldenum added for userfield " . $ufFieldId, "ok");
			}
			else
				static::report("Not exist uf fields with type of list", "warning");

		}
		catch(\Exception $exp)
		{
			static::report("Exception: " . $exp->getMessage(), "warning");
		}
	}

	private static $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	public static function generateRandom($randomType, $list = array()) {
		$count = intval(preg_replace("/.*\-/", "" ,$randomType));
		$result = "";
		if($randomType == "STRING_BOOL")
		{
			$result = rand(0, 1) ? "Y" : "N";
		}
		elseif(strstr($randomType, "STRING") !== false)
		{
			for($i = 0; $i < $count; $i++)
			{
				$result .= static::$characters[rand(0, strlen(static::$characters) - 1)];
			}
		}
		elseif(strstr($randomType, "TEXT") !== false)
		{
			for($i = 0; $i < $count; $i++)
			{
				$result .= static::$characters[rand(0, strlen(static::$characters) - 1)];
			}
			$result = implode(" ", str_split($result, rand(3, 9)));
		}
		elseif(strstr($randomType, "NUMBER") !== false)
		{
			$result = rand(0, $count);
		}
		elseif(strstr($randomType, "BOOL") !== false)
		{
			$result = !!rand(0, 1);
		}
		elseif($randomType == "FROM_LIST")
		{
			$result = $list[rand(0, count($list) - 1)];
		}
		return $result;
	}

	/**
	 * @param $rsCollection \Bitrix\Main\DB\Result
	 */
	public static function collectIds($rsCollection, $field = "ID")
	{
		$ids = array();
		while($arItem = $rsCollection->Fetch())
		{
			$ids[] = $arItem[$field];
		}
		return $ids;
	}
}