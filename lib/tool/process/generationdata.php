<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Localization\CultureTable;
use Bitrix\Main\Localization\LanguageTable;
use Bitrix\Main\Mail\Internal\EventTypeTable;

class GenerationData extends BaseProcess
{
	public static function run()
	{
		parent::run();
		static::createMainGroup();
		static::createMainCulture();
		static::createMainLanguage();
		//static::createMainSite();
		static::createMainSiteTemplate();
		static::createMainEventType();
		static::createMainEvent();

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
			$culture = $cultures[rand(0, count($cultures) - 1)];
			$id = LanguageTable::add(array(
				'LID'       => static::generateRandom("STRING0-2"),
				'SORT'      => static::generateRandom("NUMBER0-100"),
				'DEF'       => static::generateRandom("STRING_BOOL"),
				'ACTIVE'    => static::generateRandom("STRING_BOOL"),
				'NAME'      => static::generateRandom("STRING0-10"),
				'CULTURE_ID'=> $cultures[rand(0, count($cultures) - 1)],
			));
			static::report("main:culture №" . $i, $id ? "ok" : "fail");
			if(!$id)
			{
				global $APPLICATION;
				static::report("Exception: " . $APPLICATION->GetException()->GetString(),"warning");
			}
		}
	}

	public static function createMainSite($count = 2)
	{
		static::startStep(__FUNCTION__);

	}

	public static function createMainSiteTemplate($count = 2)
	{
		static::startStep(__FUNCTION__);
	}

	public static function createMainEventType($count = 2)
	{
		static::startStep(__FUNCTION__);
		$languages = static::collectIds(LanguageTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			$et = new \CEventType();
			$name = static::generateRandom("STRING0-10");
			$rand = rand(0, count($languages) - 1);
			$lang = $languages[$rand];
			$id = $et->Add(array(
				"LID"           => $lang,
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
		$eventTypes = static::collectIds(EventTypeTable::getList(array("select" => array("ID"))));
		for($i = 0; $i < $count; $i++)
		{
			$et = new \CEventMessage();
			$id = $et->Add(array(
				"EVENT_NAME"    => $eventTypes[rand(0, count($eventTypes) - 1)],
				"ACTIVE"        => static::generateRandom("STRING_BOOL"),
				"MESSAGE"       => static::generateRandom("TEXT0-200"),
				"IN_REPLY_TO"   => static::generateRandom("TEXT0-30"),
				"BODY_TYPE"     => "text",
				"SITE_ID"       => SITE_ID,
				"PRIORITY"      => static::generateRandom("NUMBER0-5"),
			));
			static::report("main:event №" . $i, $id ? "ok" : "fail");
			if(!$id)
			{
				global $APPLICATION;
				static::report("Exception: " . $APPLICATION->GetException()->GetString(),"warning");
			}
		}
	}

	/******************************************************** Iblock ***************************************************/

	public static function createIBlockType($count = 5)
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
			static::report("main:culture №" . $i, $id ? "ok" : "fail");
		}
	}


	public static function createIBlockIBlock($count = 10)
	{
		static::startStep(__FUNCTION__);
	}

	public static function createIBlockField($count = 10)
	{
		static::startStep(__FUNCTION__);
	}

	public static function createIBlockFieldEnum($count = 10)
	{
		static::startStep(__FUNCTION__);
	}

	public static function createIBlockProperty($count = 10)
	{
		static::startStep(__FUNCTION__);
	}

	public static function createIBlockPropertyEnum($count = 10)
	{
		static::startStep(__FUNCTION__);
	}

	/************************************************** Highloadblock ***************************************************/

	public static function createHighLoadBlock($count = 10)
	{
		static::startStep(__FUNCTION__);

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

	public static function generateRandom($randomType) {
		$count = intval(preg_replace("/.*\-/", "" ,$randomType));
		$result = "";
		if($count != 0)
		{
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
				$result = implode(" ", str_split($result, rand(3, 6)));
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

		}
		return $result;
	}

	/**
	 * @param $rsCollection \Bitrix\Main\DB\Result
	 */
	public static function collectIds($rsCollection)
	{
		$ids = array();
		while($arItem = $rsCollection->Fetch())
		{
			$ids[] = $arItem["ID"];
		}
		return $ids;
	}
}