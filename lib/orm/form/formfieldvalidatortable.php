<?php

namespace Intervolga\Migrato\Orm\Form;

use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\
{ DatetimeField, IntegerField, Relations\Reference, StringField};

/**
 * ORM класс таблицы валидаторов полей форм
 */
class FormFieldValidatorTable extends DataManager
{
	/**
	 * @return string
	 */
	public static function getTableName()
	{
		return 'b_form_field_validator';
	}

	/**
	 * @return array
	 * @throws \Bitrix\Main\SystemException
	 */
	public static function getMap()
	{
		return [
			'ID' => (new IntegerField(
				'ID',
				[
					'primary' => true,
					'autocomplete' => true,
				]
			)),
			'FORM_ID' => new IntegerField('FORM_ID'),
			'FIELD_ID' => new IntegerField('FIELD_ID'),
			'TIMESTAMP_X' => new DatetimeField(
				'TIMESTAMP_X',
				[
					'required' => true,
					'default_value' => static function() {
						return new DateTime();
					}
				]
			),
			'ACTIVE' => new StringField('ACTIVE'),
			'C_SORT' => new IntegerField('C_SORT'),
			'VALIDATOR_SID' => new StringField('VALIDATOR_SID'),
			'FIELD' => new Reference(
				'FIELD',
				FormFieldTable::class,
				Join::on('this.FIELD_ID', 'ref.ID'),
				['join_type' => Join::TYPE_LEFT]
			)
		];
	}
}