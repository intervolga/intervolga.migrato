<?php

namespace Intervolga\Migrato\Data\Module\BizProc;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Iblock\Iblock as IblockIblock;
use Intervolga\Migrato\Data\Module\Main\Group as MainGroup;
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
            'IBLOCK_LINK' => new Link(IblockIblock::getInstance()),
            'IBLOCK_IDS' => new Link(IblockIblock::getInstance()),
            'GROUP_IDS' => new Link(MainGroup::getInstance()),
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

            // зависимость от iblock
            if ($documentType2XmlId = $this->stringToXmlId($arTemplate["DOCUMENT_TYPE"][2])) {
                $dependency = clone $this->getDependency("IBLOCK_LINK");
                $dependency->setValue($documentType2XmlId);
                $record->setDependency("IBLOCK_LINK", $dependency);
            } else
                throw new \Exception("{$arTemplate["DOCUMENT_TYPE"][2]} не конвертируется в XML_ID");

            // зависимости от групп пользователей
            $arDependency = array(
                "IBLOCK" => array(),
                "GROUP" => array(),
            );
            $arTemplate["TEMPLATE"] = $this->convertNode($arTemplate["TEMPLATE"], $arDependency);
            if (!empty($arDependency['GROUP'])) {
                $dependency = clone $this->getDependency('GROUP_IDS');
                $dependency->setValues(array_unique($arDependency['GROUP']));
                $record->setDependency('GROUP_IDS', $dependency);
            }

            $record->addFieldsRaw(array(
                "MODULE_ID" => $arTemplate["MODULE_ID"],
                "ENTITY" => $arTemplate["ENTITY"],
                "DOCUMENT_TYPE_0" => $arTemplate["DOCUMENT_TYPE"][0],
                "DOCUMENT_TYPE_1" => $arTemplate["DOCUMENT_TYPE"][1],
                "DOCUMENT_TYPE_2" => $documentType2XmlId,
                "AUTO_EXECUTE" => $arTemplate["AUTO_EXECUTE"],
                "NAME" => $arTemplate["NAME"],
                "DESCRIPTION" => $arTemplate["DESCRIPTION"],
                "TEMPLATE" => serialize($arTemplate["TEMPLATE"]),
                "PARAMETERS" => serialize($arTemplate["PARAMETERS"]),
                "VARIABLES" => serialize($arTemplate["VARIABLES"]),
                "CONSTANTS" => serialize($arTemplate["CONSTANTS"]),
                "ACTIVE" => $arTemplate["ACTIVE"]
            ));
            $result[] = $record;
        }
        return $result;
    }
    /*
     * @param mixed[] $arNode
     * @param string[][] &$arDependency
     * @return mixed[]
    */
    private function convertNode($arNode, &$arDependency = array()) {
        $arResult = array();
        foreach ($arNode as $key => $value) {
            if ($key === 'Permission')
                $arResult[$key] =  $this->convertPermissionNode($value, $arDependency);
            elseif (is_array($value))
                $arResult[$key] = $this->convertNode($value, $arDependency);
            else
                $arResult[$key] = $value;
        }
        return $arResult;
    }

    /*
     * @param mixed[] $arNode
     * @param string[][] &$arDependency
     * @return mixed[]
    */
    private function convertPermissionNode($arNode, &$arDependency) {
        $arResult = array();
        foreach ($arNode as $permission => $arRoles) {
            $arResult[$permission] = array();
            foreach($arRoles as $role)
                if ($id = $this->xmlIdToString($role)) {
                    $arResult[$permission][] = (string) $id;
                } elseif ($xmlId = $this->stringToXmlId($role)) {
                    if (!$this->xmlIdToString('USERS_GROUP_G_' . $xmlId))
                        throw new \Exception("Некорректные символы в  XML_ID групп пользователей: $xmlId");
                    $arResult[$permission][] = 'USERS_GROUP_G_' . $xmlId;
                    $arDependency['GROUP'][] = $xmlId;
                } elseif (is_numeric($role)) {
                    $groupIdObject = RecordId::createNumericId(intval($role));
                    $xmlId = MainGroup::getInstance()->getXmlId($groupIdObject);
                    if (!$this->xmlIdToString('USERS_GROUP_' . $xmlId))
                        throw new \Exception("Некорректные символы в  XML_ID групп пользователей: $xmlId");
                    $arResult[$permission][] = 'USERS_GROUP_' . $xmlId;
                    $arDependency['GROUP'][] = $xmlId;
                } else
                    $arResult[$permission][] = $role;
        }
        return $arResult;
    }

    /**
     * @param string $field
     * @return string
     */
    private function stringToXmlId($field) {
        if (preg_match('/^iblock_(\d+)$/', $field, $matches)) {
            $iblockIdObject = RecordId::createNumericId($matches[1]);
            return IblockIblock::getInstance()->getXmlId($iblockIdObject);
        }
        if (preg_match('/^group_g(\d+)$/', $field, $matches)) {
            $groupIdObject = RecordId::createNumericId($matches[1]);
            return MainGroup::getInstance()->getXmlId($groupIdObject);
        }
    }

    /**
     * @param string $xmlId
     * @return string
     */
    private function xmlIdToString($xmlId) {
        if (preg_match('/^ibl-ibl-[-\da-f]+$/', $xmlId)) {
            $iblockLinkId = IblockIblock::getInstance()->findRecord($xmlId);
            return 'iblock_' . $iblockLinkId->getValue();
        }
        if (preg_match('/^USERS_GROUP_([a-zA-Z0-9\-]+)$/', $xmlId, $matches)) {
            $groupLinkId = MainGroup::getInstance()->findRecord($matches[1]);
            return $groupLinkId->getValue();
        }
        if (preg_match('/^USERS_GROUP_G_([a-zA-Z0-9\-]+)$/', $xmlId, $matches)) {
            $groupLinkId = MainGroup::getInstance()->findRecord($matches[1]);
            return 'group_g' . $groupLinkId->getValue();
        }
    }


    /**
     * @param RecordId $id
     * @return string
     */
    public function getXmlId($id)
    {
        $dbTemplatesList = \CBPWorkflowTemplateLoader::GetList(array(), array("ID" => $id->getValue()));
        if ($arTemplate = $dbTemplatesList->Fetch()) {
            return $this->calculateXmlId($arTemplate);
        } else
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
            $this->stringToXmlId($arTemplate["DOCUMENT_TYPE"][2]),
        )));
        return 'bzp-wft-' . BaseXmlIdProvider::formatXmlId($md5);
    }

    /**
     * @param Record $record
     * @return RecordId
     * @throws \Exception
     */
    protected function createInner(Record $record)
    {
        $arTemplate = $this->recordToArray($record);
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

    private function recordToArray(Record $record)
    {
        $arTemplate = $record->getFieldsRaw();
        $arTemplate["TEMPLATE"] = unserialize($arTemplate["TEMPLATE"]);
        $arTemplate["PARAMETERS"] = unserialize($arTemplate["PARAMETERS"]);
        $arTemplate["VARIABLES"] = unserialize($arTemplate["VARIABLES"]);
        $arTemplate["CONSTANTS"] = unserialize($arTemplate["CONSTANTS"]);
        $arTemplate['DOCUMENT_TYPE'] = array(
            $arTemplate['DOCUMENT_TYPE_0'],
            $arTemplate['DOCUMENT_TYPE_1'],
            $this->xmlIdToString($arTemplate['DOCUMENT_TYPE_2'])
        );
        unset($arTemplate['DOCUMENT_TYPE_0']);
        unset($arTemplate['DOCUMENT_TYPE_1']);
        unset($arTemplate['DOCUMENT_TYPE_2']);
        $arTemplate["TEMPLATE"] = $this->convertNode($arTemplate["TEMPLATE"]);
        return $arTemplate;
    }
}