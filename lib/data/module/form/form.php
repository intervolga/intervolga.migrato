<?php
namespace Intervolga\Migrato\Data\Module\form;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

Loc::loadMessages(__FILE__);

class Form extends BaseData
{
    protected function configure()
    {
        Loader::includeModule("form");
        $this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_FORM_TYPE'));
    }

    public function getList(array $filter = array())
    {
        $result = array();
        $by = 'ID';
        $order = 'ASC';
        $isFiltered = false;
        $getList = \CForm::GetList($by, $order, array(), $isFiltered);
        while ($form = $getList->Fetch()) {
            $record = new Record($this);
            $id = $this->createId($form['ID']);
            $record->setId($id);
            $record->setXmlId($form['SID']);
            $record->addFieldsRaw(array(
                "NAME" => $form["NAME"],
                "BUTTON" => $form["BUTTON"],
                "C_SORT" => $form["C_SORT"],
                "USE_CAPTCHA" => $form["USE_CAPTCHA"],
                "DESCRIPTION" => $form["DESCRIPTION"],
                "DESCRIPTION_TYPE" => $form["DESCRIPTION_TYPE"],
                "MAIL_EVENT_TYPE" => $form["MAIL_EVENT_TYPE"],
                "FILTER_RESULT_TEMPLATE" => $form["FILTER_RESULT_TEMPLATE"],
                "TABLE_RESULT_TEMPLATE" => $form["TABLE_RESULT_TEMPLATE"],
                "STAT_EVENT1" => $form["STAT_EVENT1"],
                "STAT_EVENT2" => $form["STAT_EVENT2"],
                "STAT_EVENT3" => $form["STAT_EVENT3"],
                "VARNAME" => $form["VARNAME"],
                "LID" => $form["LID"],
                "RESTRICT_USER" => $form["RESTRICT_USER"],
                "RESTRICT_TIME" => $form["RESTRICT_TIME"],
                "RESTRICT_STATUS" => $form["RESTRICT_STATUS"],
                "USE_RESTRICTIONS" => $form["USE_RESTRICTIONS"],
            ));
            $result[] = $record;
        }
        return $result;
    }

    public function getXmlId($id)
    {
        $form = \CForm::GetByID($id->getValue())->Fetch();
        return $form['SID'];
    }

    public function setXmlId($id, $xmlId)
    {
        \CForm::Set(array('SID' => $xmlId), $id->getValue());
    }

    public function update(Record $record)
    {
        $data = $this->recordToArray($record);
        $id = $record->getId()->getValue();
        global $strError;
        $strError='';
        $result = \CForm::Set($data, $id);
        if (!$result) {
            if ($strError) {
                throw new \Exception($strError);
            } else {
                throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_UNKNOWN_ERROR'));
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
            'NAME' => $record->getFieldRaw('NAME'),
            'BUTTON' => $record->getFieldRaw('BUTTON'),
            'C_SORT' => $record->getFieldRaw('C_SORT'),
            'USE_CAPTCHA' => $record->getFieldRaw('USE_CAPTCHA'),
            'DESCRIPTION' => $record->getFieldRaw('DESCRIPTION'),
            'DESCRIPTION_TYPE' => $record->getFieldRaw('DESCRIPTION_TYPE'),
            'MAIL_EVENT_TYPE' => $record->getFieldRaw('MAIL_EVENT_TYPE'),
            'FILTER_RESULT_TEMPLATE' => $record->getFieldRaw('FILTER_RESULT_TEMPLATE'),
            'TABLE_RESULT_TEMPLATE' => $record->getFieldRaw('TABLE_RESULT_TEMPLATE'),
            'STAT_EVENT1' => $record->getFieldRaw('STAT_EVENT1'),
            'STAT_EVENT2' => $record->getFieldRaw('STAT_EVENT2'),
            'STAT_EVENT3' => $record->getFieldRaw('STAT_EVENT3'),
            'VARNAME' => $record->getFieldRaw('VARNAME'),
            'LID' => $record->getFieldRaw('LID'),
            'RESTRICT_USER' => $record->getFieldRaw('RESTRICT_USER'),
            'RESTRICT_TIME' => $record->getFieldRaw('RESTRICT_TIME'),
            'RESTRICT_STATUS' => $record->getFieldRaw('RESTRICT_STATUS'),
            'USE_RESTRICTIONS' => $record->getFieldRaw('USE_RESTRICTIONS'),
        );
        return $array;
    }

    protected function createInner(Record $record)
    {
        $data = $this->recordToArray($record);
        global $strError;
        $strError='';
        $result = \CForm::Set($data, "");
        if ($result) {
            return $this->createId($result);
        } else {
            if ($strError) {
                throw new \Exception($strError);
            } else {
                throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_UNKNOWN_ERROR'));
            }
        }
    }

    protected function deleteInner(RecordId $id)
    {
        \CForm::Delete($id->getValue());
    }
}