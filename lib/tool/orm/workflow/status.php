<?php
namespace Intervolga\Migrato\Tool\Orm\WorkFlow;

use Bitrix\Main;
use Bitrix\Main\Entity\BooleanField;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;

class StatusTable extends DataManager
{
	public static function getTableName()
	{
		return 'b_workflow_status';
	}

	public static function getMap()
	{
		return array(
			new IntegerField('ID', array(
				'primary' => true,
			)),
			new DatetimeField('TIMESTAMP_X'),
			new IntegerField('C_SORT'),
			new BooleanField('ACTIVE'),
			new StringField('TITLE'),
			new TextField('DESCRIPTION'),
			new BooleanField('IS_FINAL'),
			new BooleanField('NOTIFY'),
		);
	}
}