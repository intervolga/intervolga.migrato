<?namespace Intervolga\Migrato\Tool\Orm;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;

class XmlIdTable extends DataManager
{
	public static function getTableName()
	{
		return "intervolga_migrato_data";
	}

	public static function getMap()
	{
		return array(
			new IntegerField("ID", array(
				"primary" => true,
			)),
			new StringField("MODULE_NAME"),
			new StringField("ENTITY_NAME"),
			new StringField("DATA_XML_ID"),
			new IntegerField("DATA_ID_NUM"),
			new StringField("DATA_ID_STR"),
			new StringField("DATA_ID_COMPLEX", array(
				"serialized" => true,
			)),
		);
	}

	public static function getList(array $parameters = array())
	{
		if ($parameters["filter"]["DATA_ID_COMPLEX"])
		{
			$parameters["filter"]["DATA_ID_COMPLEX"] = serialize($parameters["filter"]["DATA_ID_COMPLEX"]);
		}
		if ($parameters["filter"]["=DATA_ID_COMPLEX"])
		{
			$parameters["filter"]["=DATA_ID_COMPLEX"] = serialize($parameters["filter"]["=DATA_ID_COMPLEX"]);
		}
		return parent::getList($parameters);
	}

	public static function getCount(array $filter = array())
	{
		if ($filter["DATA_ID_COMPLEX"])
		{
			$filter["DATA_ID_COMPLEX"] = serialize($filter["DATA_ID_COMPLEX"]);
		}
		if ($filter["=DATA_ID_COMPLEX"])
		{
			$filter["=DATA_ID_COMPLEX"] = serialize($filter["=DATA_ID_COMPLEX"]);
		}
		return parent::getCount($filter);
	}
}