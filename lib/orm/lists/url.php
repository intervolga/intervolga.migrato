<?
namespace Intervolga\Migrato\Orm\Lists;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;

Loc::loadMessages(__FILE__);

class UrlTable extends DataManager
{
	public static function getTableName()
	{
		return 'b_lists_url';
	}

	public static function getMap()
	{
		return array(
			new IntegerField('IBLOCK_ID', array(
				'primary' => true,
			)),
			new StringField('URL'),
			new IntegerField('LIVE_FEED'),
			new ReferenceField(
				'IBLOCK',
				'\Bitrix\Iblock\Iblock',
				array('=this.IBLOCK_ID' => 'ref.ID')
			),
		);
	}
}