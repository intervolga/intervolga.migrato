<? namespace Intervolga\Migrato\Tool\Process;

use Bitrix\Main\Localization\CultureTable;
use Bitrix\Main\Localization\LanguageTable;

class GenerationData extends BaseProcess
{
	public static function run()
	{
		parent::run();
		parent::finalReport();
	}

	/********************************************************** Main ***************************************************/

	public static function createMainGroup($count = 2)
	{
		for($i = 0; $i < $count; $i++)
		{
			$group = new \CGroup();
			$name = static::generateRandom("STRING0-10");
			$group->Add(array(
				"ACTIVE"       => static::generateRandom("STRING_BOOL"),
				"C_SORT"       => static::generateRandom("NUMBER0-100"),
				"NAME"         => $name,
				"DESCRIPTION"  => static::generateRandom("TEXT0-100"),
				"STRING_ID"    => $name
			));
		}
	}

	public static function createMainCulture($count = 2) {
		for($i = 0; $i < $count; $i++)
		{
			$name = strtolower(static::generateRandom("STRING0-2"));
			CultureTable::add(array(
				"NAME"              => $name,
				"CODE"              => $name,
				"FORMAT_DATE"       => "MM/DD/YYYY",
				"FORMAT_DATETIME"   => "MM/DD/YYYY H:MI:SS T",
				"FORMAT_NAME"       => "#NAME# #LAST_NAME#",
				"WEEK_START"        => static::generateRandom("NUMBER0-6"),
				"CHARSET"           => "UTF-8",
				"DIRECTION"         => static::generateRandom("BOOL"),
			));
		}
	}

	public static function createMainLanguage($count = 2)
	{
		$cultures = static::collectIds(CultureTable::getList(array("select" => "ID")));
		for($i = 0; $i < $count; $i++)
		{
			LanguageTable::add(array(
				'SORT'      => static::generateRandom("NUMBER0-100"),
				'DEF'       => static::generateRandom("STRING_BOOL"),
				'ACTIVE'    => static::generateRandom("STRING_BOOL"),
				'NAME'      => static::generateRandom("STRING0-10"),
				'CULTURE_ID'=> $cultures[rand(0, count($cultures) - 1)],
			));
		}
	}

	public static function createMainSite($count = 2) {}

	public static function createMainSiteTemplate($count = 2) {}

	public static function createMainSiteEventType($count = 10) {}

	public static function createMainSiteEvent($count = 10) {}

	/******************************************************** Iblock ***************************************************/

	public static function createIBlockType($count = 5)
	{
		for($i = 0; $i < $count; $i++)
		{
			$obBlocktype = new \CIBlockType();
			$id = static::generateRandom("STRING0-10");
			$obBlocktype->Add(array(
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
		}
	}


	public static function createIBlockIBlock($count = 10) {}

	public static function createIBlockField($count = 10) {}

	public static function createIBlockFieldEnum($count = 10) {}

	public static function createIBlockProperty($count = 10) {}

	public static function createIBlockPropertyEnum($count = 10) {}

	/************************************************** Highloadblock ***************************************************/

	public static function createHighLoadBlock($count = 10) {}

	public static function createHighLoadField($count = 10) {}

	public static function createHighLoadFieldEnum($count = 10) {}

	/******************************************************** Sale *****************************************************/

	public static function createSalePersonType($count = 10) {}

	public static function createSalePropertyGroup($count = 10) {}

	public static function createSaleProperty($count = 10) {}

	public static function createSalePropertyVariant($count = 10) {}

	/***************************************************** Catalog *****************************************************/

	public static function createCatalogPriceType($count = 10) {}

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