<?namespace Intervolga\Migrato\Tool\Orm\WorkFlow;

use Bitrix\Main;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;

class StatusGroupTable extends DataManager
{
	public static function getTableName()
	{
		return 'b_workflow_status2group';
	}

	public static function getMap()
	{
		return array(
			new IntegerField('ID', array(
				'primary' => true,
			)),
			new IntegerField('NAME'),
			new IntegerField('STATUS_ID'),
			new IntegerField('GROUP_ID'),
			new IntegerField('PERMISSION_TYPE'),
		);
	}
}