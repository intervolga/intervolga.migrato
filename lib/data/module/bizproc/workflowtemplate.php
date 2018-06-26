<?php
namespace Intervolga\Migrato\Data\Module\BizProc;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Iblock\Iblock as IblockIblock;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class WorkflowTemplate extends BaseData
{

    protected function configure()
    {

        Loader::includeModule('bizproc');
        $this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.BIZPROC_WORKFLOWTEMPLATE'));
        $this->setDependencies(array(
            'IBLOCK_ID' => new Link(IblockIblock::getInstance()),
        ));
        $this->setVirtualXmlId(true);
    }

    /**
     * @param array $filter
     * @return array
     */
    public function getList(array $filter = array())
    {
        $result = array();
        $dbTemplatesList = \CBPWorkflowTemplateLoader::GetList(array(), array());
        while ($arTemplate = $dbTemplatesList->Fetch()) {
            $record = new \Intervolga\Migrato\Data\Record($this);
            $id = $this->createId($arTemplate["ID"]);
            $record->setXmlId($this->calculateXmlId($arTemplate));
            $record->setId($id);
            $record->addFieldsRaw(array(
                "MODULE_ID" => $arTemplate["MODULE_ID"],
                "ENTITY" => $arTemplate["ENTITY"],
                "DOCUMENT_TYPE_0" => $arTemplate["DOCUMENT_TYPE"][0],
                "DOCUMENT_TYPE_1" => $arTemplate["DOCUMENT_TYPE"][1],
                "DOCUMENT_TYPE_2" => $arTemplate["DOCUMENT_TYPE"][2],
                "AUTO_EXECUTE" => $arTemplate["AUTO_EXECUTE"],
                "NAME" => $arTemplate["NAME"],
                "DESCRIPTION" => $arTemplate["DESCRIPTION"],
                "TEMPLATE" => serialize($arTemplate["TEMPLATE"]),
                "PARAMETERS" => serialize($arTemplate["PARAMETERS"]),
                "VARIABLES" => serialize($arTemplate["VARIABLES"]),
                "CONSTANTS" => serialize($arTemplate["CONSTANTS"]),
                "MODIFIED" => $arTemplate["MODIFIED"],
                //"USER_ID" => $arTemplate["USER_ID"],
                "ACTIVE" => $arTemplate["ACTIVE"],
                "IS_MODIFIED" => $arTemplate["IS_MODIFIED"]
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
        $dbTemplatesList = \CBPWorkflowTemplateLoader::GetList(array(), array("ID" => $id->getValue()));
        if ($arTemplate = $dbTemplatesList->Fetch())
            return $this->calculateXmlId($arTemplate);
        else
            throw new \Exception("Не могу получить шаблон-бизнес процесса с ID: $id");
    }

    /**
     * @param mixed[] $arTemplate
     * @return string
     */
    private function calculateXmlId($arTemplate)
    {
        $md5 = md5(serialize(array(
            $arTemplate["MODULE_ID"],
            $arTemplate["ENTITY"],
            $arTemplate["NAME"],
            $arTemplate["DOCUMENT_TYPE"][2],
        )));
        return BaseXmlIdProvider::formatXmlId($md5);
    }


    /**
     * @param Record $record
     * @return RecordId
     * @throws \Exception
     */
    protected function createInner(Record $record)
    {

        $arTemplate = $this->recordToArray($record);
        $arTemplate['DOCUMENT_TYPE'] = array(
            $arTemplate['DOCUMENT_TYPE_0'],
            $arTemplate['DOCUMENT_TYPE_1'],
            $arTemplate['DOCUMENT_TYPE_2']
        );
        unset($arTemplate['DOCUMENT_TYPE_0']);
        unset($arTemplate['DOCUMENT_TYPE_1']);
        unset($arTemplate['DOCUMENT_TYPE_2']);
        $loader = \CBPWorkflowTemplateLoader::GetLoader();
        $returnId = $loader->AddTemplate($arTemplate, true);
        if (!$returnId) {
            throw new \Exception(ExceptionText::getLastError());
        }

        return $this->createId($returnId);
    }

    /**
     * @param RecordId $id
     * @throws \Exception
     */
    protected function deleteInner(RecordId $id)
    {
        $loader = \CBPWorkflowTemplateLoader::GetLoader();
        $loader->DeleteTemplate($id->getValue());
    }

    /**
     * @param Record $record
     * @throws \Exception
     */
    public function update(Record $record)
    {
        $id = $record->getId()->getValue();
        $arTemplate = $this->recordToArray($record);
        $loader = \CBPWorkflowTemplateLoader::GetLoader();
        $returnId = $loader->UpdateTemplate($id, $arTemplate, true);
        if (!$returnId) {
            throw new \Exception(ExceptionText::getLastError());
        }
    }

    protected function recordToArray(Record $record)
    {
        $arTemplate = $record->getFieldsRaw();
        $arTemplate["TEMPLATE"] = unserialize($arTemplate["TEMPLATE"]);
        $arTemplate["PARAMETERS"] = unserialize($arTemplate["PARAMETERS"]);
        $arTemplate["VARIABLES"] = unserialize($arTemplate["VARIABLES"]);
        $arTemplate["CONSTANTS"] = unserialize($arTemplate["CONSTANTS"]);
        return $arTemplate;
    }
}