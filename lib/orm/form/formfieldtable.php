<?php

namespace Intervolga\Migrato\Orm\Form;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\
{BooleanField, DatetimeField, IntegerField, StringField};

/**
 * ORM класс таблицы значений полей форм
 */
class FormFieldTable extends DataManager
{

	/**
	 * @return string
	 */
	public static function getTableName()
	{
		return 'b_form_field';
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
			'TITLE' => new StringField('TITLE'),
			'TITLE_TYPE' => new StringField('TITLE_TYPE'),
			'SID' => new StringField('SID'),
			'C_SORT' => new IntegerField('C_SORT'),
			'ADDITIONAL' => (new BooleanField('ADDITIONAL'))
				->configureValues('N', 'Y'),
			'REQUIRED' => (new BooleanField('REQUIRED'))
				->configureValues('N', 'Y'),
			'IN_FILTER' => (new BooleanField('IN_FILTER'))
				->configureValues('N', 'Y'),
			'IN_RESULTS_TABLE' => (new BooleanField('IN_RESULTS_TABLE'))
				->configureValues('N', 'Y'),
			'IN_EXCEL_TABLE' => (new BooleanField('IN_EXCEL_TABLE'))
				->configureValues('N', 'Y'),
			'FIELD_TYPE' => new StringField('FIELD_TYPE'),
			'COMMENTS' => new StringField('COMMENTS'),
			'FILTER_TITLE' => new StringField('FILTER_TITLE'),
			'RESULTS_TABLE_TITLE' => new StringField('RESULTS_TABLE_TITLE'),
		];
	}
}