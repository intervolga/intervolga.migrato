<?namespace Intervolga\Migrato\Tool\Orm;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\StringField;

class OptionTable extends DataManager
{
	public static function getTableName()
	{
		return 'b_option';
	}

	public static function getMap()
	{
		return array(
			new StringField('MODULE_ID', array(
				'primary' => true,
			)),
			new StringField('NAME', array(
				'primary' => true,
			)),
			new StringField('SITE_ID', array(
				'primary' => true,
			)),
			new StringField('VALUE'),
			new StringField('DESCRIPTION'),
		);
	}
}