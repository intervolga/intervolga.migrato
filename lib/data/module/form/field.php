<?php
namespace Intervolga\Migrato\Data\Module\form;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;

Loc::loadMessages(__FILE__);

class Field extends BaseData
{
    protected function configure()
    {
        Loader::includeModule("form");
        $this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_FIELD_TYPE'));
        $this->setDependencies(array(
            'FORM' => new Link(Form::getInstance()),
        ));
    }

    public function getList(array $filter = array())
    {
        $result = array();
        $by = 'ID';
        $order = 'ASC';
        $isFiltered = false;
        $getList = \CForm::GetList($by, $order, array(), $isFiltered);
        while ($form = $getList->Fetch()) {
            $rsFields = \CFormField::GetList(
                $form['ID'],
                "ALL",
                $by,
                $order,
                array(),
                $isFiltered
            );
            while ($field = $rsFields->Fetch()) {
                $record = new Record($this);
                $id = $this->createId($field['ID']);
                $record->setId($id);
                $record->setXmlId($field['SID']);
                $record->addFieldsRaw(array(
                    "ACTIVE" => $field["ACTIVE"],
                    "TITLE" => $field["TITLE"],
                    "TITLE_TYPE" => $field["TITLE_TYPE"],
                    "C_SORT" => $field["C_SORT"],
                    "ADDITIONAL" => $field["ADDITIONAL"],
                    "REQUIRED" => $field["REQUIRED"],
                    "IN_FILTER" => $field["IN_FILTER"],
                    "IN_RESULTS_TABLE" => $field["IN_RESULTS_TABLE"],
                    "IN_EXCEL_TABLE" => $field["IN_EXCEL_TABLE"],
                    "FIELD_TYPE" => $field["FIELD_TYPE"],
                    "COMMENTS" => $field["COMMENTS"],
                    "FILTER_TITLE" => $field["FILTER_TITLE"],
                    "RESULTS_TABLE_TITLE" => $field["RESULTS_TABLE_TITLE"],
                    "VARNAME" => $field["VARNAME"],
                ));
                $dependency = clone $this->getDependency("FORM");
                $dependency->setValue(
                    Form::getInstance()->getXmlId(RecordId::createNumericId($form['ID']))
                );
                $record->setDependency("FORM", $dependency);
                $result[] = $record;
            }
        }
        return $result;
    }

    public function getXmlId($id)
    {
        $field = \CFormField::GetByID($id->getValue())->Fetch();
        return $field['SID'];
    }

    public function setXmlId($id, $xmlId)
    {
        \CFormField::Set(array('SID' => $xmlId), $id->getValue());
    }

    public function update(Record $record)
    {
        $data = $this->recordToArray($record);
        $id = $record->getId()->getValue();
        $result = \CFormField::Set($data, $id);
        global $strError;
        if (!$result) {
            if ($strError) {
                throw new \Exception($strError);
            } else {
                throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_FIELD_UNKNOWN_ERROR'));
            }
        }
    }

    /**
     * @param Record $record
     * @return array
     */
    protected function recordToArray(Record $record)
    {
        $array = array(
            'SID' => $record->getXmlId(),
            'ACTIVE' => $record->getFieldRaw('ACTIVE'),
            'TITLE' => $record->getFieldRaw('TITLE'),
            'TITLE_TYPE' => $record->getFieldRaw('TITLE_TYPE'),
            'C_SORT' => $record->getFieldRaw('C_SORT'),
            'ADDITIONAL' => $record->getFieldRaw('ADDITIONAL'),
            'REQUIRED' => $record->getFieldRaw('REQUIRED'),
            'IN_FILTER' => $record->getFieldRaw('IN_FILTER'),
            'IN_RESULTS_TABLE' => $record->getFieldRaw('IN_RESULTS_TABLE'),
            'IN_EXCEL_TABLE' => $record->getFieldRaw('IN_EXCEL_TABLE'),
            'FIELD_TYPE' => $record->getFieldRaw('FIELD_TYPE'),
            'COMMENTS' => $record->getFieldRaw('COMMENTS'),
            'FILTER_TITLE' => $record->getFieldRaw('FILTER_TITLE'),
            'RESULTS_TABLE_TITLE' => $record->getFieldRaw('RESULTS_TABLE_TITLE'),
            'VARNAME' => $record->getFieldRaw('VARNAME'),
        );

        if ($form = $record->getDependency("FORM")) {
            if ($form->getId()) {
                $array["FORM_ID"] = $form->getId()->getValue();
            }
        }


        return $array;
    }

    protected function createInner(Record $record)
    {
        $data = $this->recordToArray($record);
        $result = \CFormField::Set($data, "");
        global $strError;
        if ($result) {
            return $this->createId($result);
        } else {
            if ($strError) {
                throw new \Exception($strError);
            } else {
                throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_FIELD_UNKNOWN_ERROR'));
            }
        }
    }

    protected function deleteInner(RecordId $id)
    {
        \CFormField::Delete($id->getValue());
    }
}

?>