<?php
namespace Intervolga\Migrato\Data\Module\Sale;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\LanguageTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Internals\StatusLangTable;
use Bitrix\Sale\Internals\StatusTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Main\Language;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\ExceptionText;

class Status extends BaseData
{
    public function getList(array $filter = array())
    {
        $result = array();
        $statuses = StatusTable::getList();

        while($status = $statuses->fetch())
        {
            $result[] = $this->arrayToRecord($status);
        }

        return $result;
    }

    public function getXmlId($id)
    {
        return $id->getValue();
    }

    public function update(Record $record)
    {
        $recordId = $record->getId()->getValue();
        $fields = $record->getFieldsRaw();
        $updateFields = $this->getFieldsForCreateOrUpdate($fields);

        $updateResult = StatusTable::update($recordId, $updateFields);
        if($updateResult->isSuccess())
        {
            $this->updateMessages($recordId, $fields);
        }
        else
        {
            throw new \Exception(ExceptionText::getFromResult($updateResult));
        }
    }
    
    protected function createInner(Record $record)
    {
        $fields = $record->getFieldsRaw();
        $addFields = $this->getFieldsForCreateOrUpdate($fields);

        $addResult = StatusTable::add($addFields);
        if($addResult->isSuccess())
        {
            $id = RecordId::createStringId($fields['ID']);
            $this->updateMessages($id->getValue(), $fields);
            return $id;
        }
        else
        {
            throw new \Exception(ExceptionText::getFromResult($addResult));
        }
    }

    protected function deleteInner(RecordId $id)
    {
        $result = StatusTable::delete($id->getValue());
        if($result->isSuccess())
        {
            $this->deleteMessages($id->getValue());
        }
        else
        {
            throw new \Exception(ExceptionText::getFromResult($result));
        }
    }

    protected function configure()
    {
        Loader::includeModule('sale');
        $this->setEntityNameLoc(Loc::getMessage('INTERVOLGA.MIGRATO.SALE_STATUS'));
        $this->setVirtualXmlId(true);
        $this->setDependencies(array(
            'LANGUAGE' => new Link(Language::getInstance()),
        ));
    }

    private function arrayToRecord($status)
    {
        $recordId = RecordId::createStringId($status['ID']);
        $xmlId = $this->getXmlId($recordId);

        $record = new Record($this);
        $record->setId($recordId);
        $record->setXmlId($xmlId);
        $record->addFieldsRaw(array(
            'ID' => $status['ID'],
            'TYPE' => $status['TYPE'],
            'SORT' => $status['SORT'],
            'NOTIFY' => $status['NOTIFY']
        ));
        $this->addLanguageStrings($record);

        return $record;
    }

    private function addLanguageStrings(Record $record)
    {
        $strings = array();
        $langXmlIds = array();

        // Build array with status language strings
        foreach ($this->getLanguages() as $language)
        {
            if($statusLang = $this->getStatusLang($record->getId()->getValue(), $language))
            {
                foreach ($this->getLanguageFields() as $languageField)
                {
                    $langId = Language::getInstance()->createId($language);
                    $langXmlIds[$language] = Language::getInstance()->getXmlId($langId);

                    $strings[$languageField][$language] = $statusLang[$languageField];
                }
            }
        }

        // Add status language strings to record
        foreach ($strings as $field => $langFields)
        {
            $statusLangs = Value::treeToList($langFields, $field);
            $record->addFieldsRaw($statusLangs);
        }
    }

    private function getStatusLang($statusId, $langId)
    {
        return StatusLangTable::getList(array(
            'filter' => array(
                '=STATUS_ID' => $statusId,
                '=LID' =>  $langId
            )
        ))->fetch();
    }

    private function getLanguages()
    {
        $result = array();
        $getList = LanguageTable::getList(array(
            "select" => array(
                "LID",
            ),
        ));

        while ($language = $getList->fetch())
        {
            $result[] = $language["LID"];
        }

        return $result;
    }

    private function getLanguageFields()
    {
        return array(
            "NAME",
            "DESCRIPTION",
        );
    }

    public function getFieldsForCreateOrUpdate($arFields)
    {
        $resFields = array();

        foreach ($arFields as $fieldName => $field)
        {
            if(!$this->isLangField($fieldName))
            {
                $resFields[$fieldName] = $field;
            }
        }

        return $resFields;
    }

    private function isLangField($fieldName)
    {
        $languages = $this->getLanguages();
        foreach ($languages as $language)
        {
            $needle = '.' . $language;
            if(mb_strpos($fieldName, $needle) !== false)
            {
                return true;
            }
        }

        return false;
    }

    private function updateMessages($statusId, $fields)
    {
        if(!empty($statusId))
        {
            $this->deleteMessages($statusId);
            foreach ($fields as $fieldFullName => $field)
            {
                $this->updateMessage($statusId, $fieldFullName, $field);
            }
        }
    }

    private function updateMessage($statusId, $fieldName, $fieldValue)
    {
        if($this->isLangField($fieldName))
        {
            $fieldData = explode('.', $fieldName);
            $fieldName = $fieldData[0];
            $lid = $fieldData[1];

            $dbResult = StatusLangTable::getByPrimary(array(
                'STATUS_ID' => $statusId,
                'LID' => $lid
            ))->fetch();

            if(!empty($dbResult))
            {
                StatusLangTable::update(
                    array(
                        'STATUS_ID' => $statusId,
                        'LID' => $lid
                    ),
                    array(
                        $fieldName => $fieldValue
                    )
                );
            }
            else
            {
                StatusLangTable::add(array(
                    'STATUS_ID' => $statusId,
                    'LID' => $lid,
                    $fieldName => $fieldValue
                ));
            }
        }
    }

    private function deleteMessages($statusId)
    {
        StatusLangTable::deleteByStatus($statusId);
    }
}