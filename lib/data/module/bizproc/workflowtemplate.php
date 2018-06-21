<?php
namespace Intervolga\Migrato\Data\Module\BizProc;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Iblock\Iblock as IblockIblock;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Console\Logger;

Loc::loadMessages(__FILE__);

class WorkflowTemplate extends BaseData
{

    protected function configure()
    {
        Loader::includeModule('bizproc');
        $this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.BIZPROC_WORKFLOWTEMPLATE'));
        //$this->setDependencies(array(
        //    'IBLOCK_ID' => new Link(IblockIblock::getInstance()),
        //));
        $this->setVirtualXmlId(true);
    }

    /**
     * @param array $filter
     * @return array
     */
    public function getList(array $filter = array())
    {
        $result = array();
        $dbTemplatesList = \CBPWorkflowTemplateLoader::GetList(Array(), Array());
        while($arTemplate = $dbTemplatesList->Fetch()) {
            $record = new \Intervolga\Migrato\Data\Record($this);
            $id = $this->createId($arTemplate["ID"]);
            $record->setId($id);
            $record->setXmlId("WT_" . $id);
            //$record->setXmlId($this->getXmlId($id));
            $record->addFieldsRaw(array(
                "TEMPLATE" => serialize($arTemplate)
            ));
            $result[] = $record;
        }
        return $result;
    }

    /**
     * @param RecordId $id
     * @return string
     */
    public function getXmlId($id)
    {
//        return IblockIblock::getInstance()->getXmlId($id);
    }

    public function setXmlId($id, $xmlId)
    {
//        \CFormField::Set(array('SID' => $xmlId), $id->getValue());
    }

    /**
     * @param Record $record
     * @return RecordId
     * @throws \Exception
     */
    protected function createInner(Record $record)
    {
//        $fields = $this->recordToArray($record);
//        $result = CatalogIblockTable::add($fields);
//        if ($result->isSuccess())
//        {
//            $id = $this->createId($result->getData()['IBLOCK_ID']);
//            return $id;
//        }
//        else
//        {
//            throw new \Exception(ExceptionText::getFromApplication());
//        }
    }

    /**
     * @param RecordId $id
     * @throws \Exception
     */
    protected function deleteInner(RecordId $id)
    {
//        $result = CatalogIblockTable::delete($id->getValue());
//        if (!$result->isSuccess())
//        {
//            throw new \Exception(ExceptionText::getFromApplication());
//        }
    }

    /**
     * @param Record $record
     * @throws \Exception
     */
    public function update(Record $record)
    {
//        $fields = $this->recordToArray($record);
//        $result = CatalogIblockTable::update($record->getId()->getValue(), $fields);
//        if (!$result->isSuccess())
//        {
//            throw new \Exception(ExceptionText::getFromApplication());
//        }
    }

}