<?php
declare(strict_types=1);

namespace Intervolga\Migrato\Data\Module\Form;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Orm\Form\FormFieldTable;
use Intervolga\Migrato\Orm\Form\FormFieldValidatorTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Validator extends BaseData
{
	const XML_ID_SEPARATOR = '.';
	protected $enabled = false;

	/**
	 * @return void
	 * @throws \Bitrix\Main\LoaderException
	 */
	protected function configure()
	{
		if (!Loader::includeModule('form')) {
			$this->enabled = false;
			return;
		}

		$this->enabled = true;
		$this->setVirtualXmlId(true);
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_VALIDATOR_TYPE'));
		$this->setDependencies(array(
			'FORM' => new Link(Form::getInstance()),
			'FIELD' => new Link(Field::getInstance()),
		));
	}

	/**
	 * @param array $filter
	 * @return array|Record[]
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function getList(array $filter = array())
	{
		if (!$this->enabled) {
			return array();
		}

		$result = array();
		$formSidById = array();
		$by = 'ID';
		$order = 'ASC';
		$isFiltered = false;
		$getList = \CForm::GetList($by, $order, array(), $isFiltered);

		while ($form = $getList->Fetch()) {
			$formSidById[(int) $form['ID']] = (string) $form['SID'];
		}

		$fieldMap = array();
		$fieldRows = FormFieldTable::query()
			->setSelect(['ID', 'SID', 'FORM_ID'])
			->exec();
		while ($fieldRow = $fieldRows->fetch()) {
			$fieldId = (int) $fieldRow['ID'];
			$fieldMap[$fieldId] = array(
				'SID' => (string) $fieldRow['SID'],
				'FORM_ID' => (int) $fieldRow['FORM_ID'],
			);
		}

		$validatorRows = FormFieldValidatorTable::query()
			->setSelect(['ID', 'FORM_ID', 'FIELD_ID', 'VALIDATOR_SID', 'ACTIVE', 'C_SORT'])
			->exec();
		while ($validatorRow = $validatorRows->fetch()) {
			$id = (int) $validatorRow['ID'];
			$fieldId = (int) $validatorRow['FIELD_ID'];
			$formId = (int) $validatorRow['FORM_ID'];
			$validatorSid = (string) $validatorRow['VALIDATOR_SID'];

			$field = $fieldMap[$fieldId] ?? null;
			$formSid = $formSidById[$formId] ?? null;
			if (!$id || !$field || !$formSid || $validatorSid === '') {
				continue;
			}

			$record = new Record($this);
			$recordId = $this->createId($id);
			$record->setId($recordId);

			$fieldSid = $field['SID'];
			$record->setXmlId($formSid . static::XML_ID_SEPARATOR . $fieldSid . static::XML_ID_SEPARATOR . $validatorSid);
			$record->addFieldsRaw(array(
				'VALIDATOR_SID' => $validatorSid,
				'ACTIVE' => $validatorRow['ACTIVE'],
				'C_SORT' => $validatorRow['C_SORT'],
			));

			$formDependency = clone $this->getDependency('FORM');
			$formDependency->setValue($formSid);
			$record->setDependency('FORM', $formDependency);

			$fieldDependency = clone $this->getDependency('FIELD');
			$fieldDependency->setValue($formSid . Field::XML_ID_SEPARATOR . $fieldSid);
			$record->setDependency('FIELD', $fieldDependency);

			$result[] = $record;
		}

		return $result;
	}

	/**
	 * @param $id
	 * @return string|null
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function getXmlId($id)
	{
		if (!$this->enabled) {
			return null;
		}

		$validator = FormFieldValidatorTable::getByPrimary($id->getValue())->fetch();
		if (!$validator) {
			return null;
		}

		$field = FormFieldTable::getByPrimary($validator['FIELD_ID'])->fetch();
		if (!$field) {
			return null;
		}

		$form = \CForm::GetByID($field['FORM_ID'])->Fetch();
		if (!$form) {
			return null;
		}

		return $form['SID'] . static::XML_ID_SEPARATOR . $field['SID'] . static::XML_ID_SEPARATOR . $validator['VALIDATOR_SID'];
	}

	/**
	 * @param Record $record
	 * @return void
	 * @throws \Exception
	 */
	public function update(Record $record)
	{
		if (!$this->enabled) {
			return;
		}
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		$updateResult = FormFieldValidatorTable::update($id, $data);
		if (!$updateResult->isSuccess()) {
			throw new \Exception(ExceptionText::getFromResult($updateResult));
		}
	}

	/**
	 * @param Record $record
	 * @return array
	 * @throws \Exception
	 */
	protected function recordToArray(Record $record)
	{
		if (!$this->enabled) {
			return array();
		}
		$array = array(
			'VALIDATOR_SID' => $record->getFieldRaw('VALIDATOR_SID'),
			'ACTIVE' => $record->getFieldRaw('ACTIVE'),
			'C_SORT' => $record->getFieldRaw('C_SORT'),
		);

		if ($form = $record->getDependency('FORM')) {
			if ($form->getId()) {
				$array['FORM_ID'] = $form->getId()->getValue();
			}
		}

		if ($field = $record->getDependency('FIELD')) {
			if ($field->getId()) {
				$array['FIELD_ID'] = $field->getId()->getValue();
			}
		}

		return $array;
	}

	/**
	 * @param Record $record
	 * @return RecordId
	 * @throws \Exception
	 */
	protected function createInner(Record $record)
	{
		if (!$this->enabled) {
			return $this->createId(0);
		}
		$data = $this->recordToArray($record);
		$addResult = FormFieldValidatorTable::add($data);
		if ($addResult->isSuccess()) {
			return $this->createId($addResult->getId());
		} else {
			throw new \Exception(ExceptionText::getFromResult($addResult));
		}
	}

	/**
	 * @param RecordId $id
	 * @return void
	 * @throws \Exception
	 */
	protected function deleteInner(RecordId $id)
	{
		if (!$this->enabled) {
			return;
		}
		$deleteResult = FormFieldValidatorTable::delete($id->getValue());
		if (!$deleteResult->isSuccess()) {
			throw new \Exception(ExceptionText::getFromResult($deleteResult));
		}
	}
}
