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

class GenerationData extends BaseProcess
{
	public static function run()
	{
		parent::run();
		/*static::createMainGroup();
		static::createMainCulture();
		static::createMainLanguage();
		static::createMainSite();
		static::createMainSiteTemplate();
		static::createMainEventType();
		static::createMainEvent();

		if(\CModule::IncludeModule("iblock"))
		{
			static::createIBlockType();
			static::createIBlockIBlock();
			static::createIBlockField();
			static::createIBlockFieldEnum();
			static::createIBlockProperty();
			static::createIBlockPropertyEnum();
		}
		*/
		if(Loader::IncludeModule("highloadblock"))
		{
			static::createHighLoadBlock();
			//static::createHighLoadField();
			//static::createHighLoadFieldEnum();
		}

		parent::finalReport();
	}

	/********************************************************** Main ***************************************************/

	public static function createMainGroup($count = 2)
	{
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
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
			if(!$id)
			{
				global $APPLICATION;
				static::report("Exception: " . $APPLICATION->GetException()->GetString(),"warning");
			}
		}
	}

	public static function createMainCulture($count = 2) {
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
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
			static::report("main:culture №" . $i, $id ? "ok" : "fail");
			if(!$id)
			{
				global $APPLICATION;
				static::report("Exception: " . $APPLICATION->GetException()->GetString(),"warning");
			}
		}
	}

	public static function createMainLanguage($count = 2)
	{
		static::startStep(__FUNCTION__);
		$cultures = static::collectIds(CultureTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
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
			if(!$id)
			{
				global $APPLICATION;
				static::report("Exception: " . $APPLICATION->GetException()->GetString(),"warning");
			}
		}
	}

	public static function createMainSite($count = 1)
	{
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
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
				'SITE_NAME' => static::generateRandom("TEXT0-20"),
			));
			static::report("main:site №" . $i, $result->isSuccess() ? "ok" : "fail");
			if(!$result->isSuccess())
			{
				static::report("Exception: " . $result->getErrorMessages(),"warning");
			}
		}
	}

	public static function createMainSiteTemplate($count = 1)
	{
		static::startStep(__FUNCTION__);
		$sites = static::collectIds(SiteTable::getList(array("select" => array("LID"))), "LID");
		for($i = 0; $i < $count; $i++)
		{
			$result = SiteTemplateTable::add(array(
				"SITE_ID"   => static::generateRandom("FROM_LIST", $sites),
				"CONDITION" => "",
				"SORT"      => static::generateRandom("NUMBER0-100"),
				"TEMPLATE"  => static::generateRandom("STRING0-15"),
			));
			static::report("main:siteTemplate №" . $i, $result->isSuccess() ? "ok" : "fail");
			if(!$result->isSuccess())
			{
				static::report("Exception: " . $result->getErrorMessages(), "warning");
			}
		}
	}

	public static function createMainEventType($count = 2)
	{
		static::startStep(__FUNCTION__);
		$languages = static::collectIds(LanguageTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			$et = new \CEventType();
			$name = static::generateRandom("STRING0-10");
			$id = $et->Add(array(
				"LID"           => static::generateRandom("FROM_LIST", $languages),
				"EVENT_NAME"    => $name,
				"NAME"          => $name,
				"SORT"          => static::generateRandom("NUMBER0-100"),
				"DESCRIPTION"   => static::generateRandom("TEXT0-50")
			));
			static::report("main:eventtype №" . $i, $id ? "ok" : "fail");
			if(!$id)
			{
				global $APPLICATION;
				static::report("Exception: " . $APPLICATION->GetException()->GetString(), "warning");
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
			global $APPLICATION;
			if(!$id && $APPLICATION->GetException())
			{
				static::report("Exception: " . $APPLICATION->GetException()->GetString(),"warning");
			}
		}
	}

	/******************************************************** Iblock ***************************************************/

	public static function createIBlockType($count = 1)
	{
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
		{
			$obBlocktype = new \CIBlockType();
			$id = static::generateRandom("STRING0-10");
			$id = $obBlocktype->Add(array(
				'ID'        => static::generateRandom("STRING0-10"),
				'SECTIONS'  => static::generateRandom("STRING_BOOL"),
				'IN_RSS'    => static::generateRandom("STRING_BOOL"),
				'SORT'      => static::generateRandom("NUMBER0-1000"),
				'LANG'      => Array(
					'en' =>Array(
						'NAME' => $id,
						'SECTION_NAME' => 'Sections',
						'ELEMENT_NAME' => 'Products'
					)
				)
			));
			static::report("iblock:type №" . $i, $id ? "ok" : "fail");
			if(!$id)
			{
				global $APPLICATION;
				static::report("Exception: " . $APPLICATION->GetException()->GetString(),"warning");
			}
		}
	}


	public static function createIBlockIBlock($count = 1)
	{
		static::startStep(__FUNCTION__);
		$types = static::collectIds(TypeTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			$obBlock = new \CIBlock();
			$name = static::generateRandom("STRING0-10");
			$rand = rand(0, count($types) - 1);
			$iblockType = $types[$rand];
			$id = $obBlock->Add(array(
				"CODE"              => $name,
				"NAME"              => $name,
				"IBLOCK_TYPE_ID"    => $iblockType,
				"ACTIVE"            => static::generateRandom("STRING_BOOL"),
				"SORT"              => static::generateRandom("NUMBER0-100"),
				"DESCRIPTION"       => static::generateRandom("TEXT0-200"),
				"DESCRIPTION_TYPE"  => "text",
				"RSS_ACTIVE"        => "N",
				"INDEX_ELEMENT"     => static::generateRandom("STRING_BOOL"),
				"INDEX_SECTION"     => static::generateRandom("STRING_BOOL"),
			));
			static::report("iblock:iblock №" . $i, $id ? "ok" : "fail");
			global $APPLICATION;
			if(!$id && $APPLICATION->GetException())
			{
				static::report("Exception: " . $APPLICATION->GetException()->GetString(), "warning");
			}
		}
	}

	public static function createIBlockField($count = 1)
	{
		static::startStep(__FUNCTION__);
		$iblocks = static::collectIds(IblockTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			$obIBlockField = new \CUserTypeEntity();
			$name = static::generateRandom("STRING0-10");
			$id = $obIBlockField->Add(array(
				"FIELD_NAME"        => "UF_" . $name,
				"XML_ID"            => $name,
				"ENTITY_ID"         => "IBLOCK_" . static::generateRandom("FROM_LIST", $iblocks) . "_SECTION",
				"MANDATORY"         => static::generateRandom("STRING_BOOL"),
				"ACTIVE"            => static::generateRandom("STRING_BOOL"),
				"MULTIPLE"          => "N",
				"SORT"              => static::generateRandom("NUMBER0-100"),
				"USER_TYPE_ID"      => static::generateRandom("FROM_LIST", array("enumeration", "double", "integer", "boolean", "string")),
				'EDIT_FORM_LABEL'   => array(
					'ru'    => static::generateRandom("TEXT0-10"),
					'en'    => static::generateRandom("TEXT0-10"),
				),
				'LIST_COLUMN_LABEL' => array(
					'ru'    => static::generateRandom("TEXT0-10"),
					'en'    => static::generateRandom("TEXT0-10"),
				),
				'LIST_FILTER_LABEL' => array(
					'ru'    => static::generateRandom("TEXT0-10"),
					'en'    => static::generateRandom("TEXT0-10"),
				),
				'ERROR_MESSAGE'     => array(
					'ru'    => static::generateRandom("TEXT0-10"),
					'en'    => static::generateRandom("TEXT0-10"),
				),
				'HELP_MESSAGE'      => array(
					'ru'    => static::generateRandom("TEXT0-10"),
					'en'    => static::generateRandom("TEXT0-10"),
				),
			));
			static::report("iblock:field №" . $i, $id ? "ok" : "fail");
			if(!$id)
			{
				global $APPLICATION;
				static::report("Exception: " . $APPLICATION->GetException()->GetString(),"warning");
			}
		}
	}

	public static function createIBlockFieldEnum($count = 1)
	{
		static::startStep(__FUNCTION__);

	}

	public static function createIBlockProperty($count = 1)
	{
		static::startStep(__FUNCTION__);
		$iblocks = static::collectIds(IblockTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			$obIBlockProperty = new \CIBlockProperty();
			$name = static::generateRandom("STRING0-10");
			$id = $obIBlockProperty->Add(array(
				"CODE"              => $name,
				"NAME"              => $name,
				"XML_ID"            => $name,
				"IBLOCK_ID"         => static::generateRandom("FROM_LIST", $iblocks),
				"IS_REQUIRED"       => static::generateRandom("STRING_BOOL"),
				"ACTIVE"            => static::generateRandom("STRING_BOOL"),
				"SORT"              => static::generateRandom("NUMBER0-100"),
				"PROPERTY_TYPE"     => static::generateRandom("FROM_LIST", array("S", "N")),
				"WITH_DESCRIPTION"  => static::generateRandom("STRING_BOOL"),
			));
			static::report("iblock:property №" . $i, $id ? "ok" : "fail");
		}
	}

	public static function createIBlockPropertyEnum($count = 1)
	{
		static::startStep(__FUNCTION__);
	}

	/************************************************** Highloadblock ***************************************************/

	public static function createHighLoadBlock($count = 1)
	{
		static::startStep(__FUNCTION__);
		for($i = 0; $i < $count; $i++)
		{
			$name = static::generateRandom("STRING0-10");
			$result = HighloadBlockTable::add(array(
				"NAME"          => ucfirst(strtolower($name)),
				"TABLE_NAME"    => strtolower($name),
			));

			static::report("hlblock:hlblock №" . $i, $result->isSuccess() ? "ok" : "fail");
			if (!$result->isSuccess())
			{
				static::report("Exception: " . implode(",", $result->getErrorMessages()), "warning");
			}
		}

	}

	public static function createHighLoadField($count = 10)
	{
		static::startStep(__FUNCTION__);

	}

	public static function createHighLoadFieldEnum($count = 10)
	{
		static::startStep(__FUNCTION__);

	}

	/******************************************************** Sale *****************************************************/

	public static function createSalePersonType($count = 10)
	{
		static::startStep(__FUNCTION__);
	}

	public static function createSalePropertyGroup($count = 10)
	{
		static::startStep(__FUNCTION__);

	}

	public static function createSaleProperty($count = 10)
	{
		static::startStep(__FUNCTION__);

	}

	public static function createSalePropertyVariant($count = 10)
	{
		static::startStep(__FUNCTION__);

	}

	/***************************************************** Catalog *****************************************************/

	public static function createCatalogPriceType($count = 10)
	{
		static::startStep(__FUNCTION__);
	}

	/************************************************ Class methods ****************************************************/

	private static $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	public static function generateRandom($randomType, $list = array()) {
		$count = intval(preg_replace("/.*\-/", "" ,$randomType));
		$result = "";
		if(strstr($randomType, "STRING") !== false)
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
		elseif(strstr($randomType, "STRING_BOOL") !== false)
		{
			$result = rand(0, 1) ? "Y" : "N";
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