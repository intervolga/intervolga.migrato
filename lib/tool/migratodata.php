<?namespace Intervolga\Migrato\Tool;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;

class MigratoDataTable extends DataManager
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
}