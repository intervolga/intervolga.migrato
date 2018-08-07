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
	const PREFIX_WORKFLOW_TEMPLATE = 'bzp-wft-';
	const PREFIX_IBLOCK = 'ibl-ibl-';
	const PREFIX_USER_GROUP_NUMERIC = 'USER_GROUP_N_';
	const PREFIX_USER_GROUP_LITERAL = 'USER_GROUP_G_';
	const CRM_MODULE = 'crm';

	protected function configure()
	{
		Loader::includeModule('bizproc');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.BIZPROC_WORKFLOWTEMPLATE'));
		$this->setDependencies(array(
			'IBLOCK_ID' => new Link(IblockIblock::getInstance()),
			'GROUP_ID' => new Link(MainGroup::getInstance()),
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
			// массив зависимостей
			$arDependency = array(
				"IBLOCK" => array(),
				"GROUP" => array(),
			);
			// зависимость от iblock в DOCUMENT_TYPE
			if ($documentType2XmlId = $this->stringToXmlId($arTemplate["DOCUMENT_TYPE"][2])) {
				$arDependency["IBLOCK"][] = $documentType2XmlId;
			}else {
				// если шаблон не привязан к инфоблоку или к модулю CRM, то пропускаем его
				if ($arTemplate["DOCUMENT_TYPE"][2] !== self::CRM_MODULE) {
					continue;
				}
			}
			// просматриваем содержимое шаблона в поисках зависимостей
			$arTemplate["TEMPLATE"] = $this->convertNode($arTemplate["TEMPLATE"], $arDependency, $record);
			$arTemplate["PARAMETERS"] = $this->convertVariables($arTemplate["PARAMETERS"], $arDependency, $record);
			$arTemplate["VARIABLES"] = $this->convertVariables($arTemplate["VARIABLES"], $arDependency, $record);
			$arTemplate["CONSTANTS"] = $this->convertVariables($arTemplate["CONSTANTS"], $arDependency, $record);
			// зависимости от групп пользователей
			if (!empty($arDependency['GROUP'])) {
				$dependency = clone $this->getDependency('GROUP_ID');
				$dependency->setValues(array_unique($arDependency['GROUP']));
				$record->setDependency('GROUP_IDS', $dependency);
			}
			// зависимости от инфоблоков
			if (!empty($arDependency['IBLOCK'])) {
				$dependency = clone $this->getDependency('IBLOCK_ID');
				$dependency->setValues(array_unique($arDependency['IBLOCK']));
				$record->setDependency('IBLOCK_ID', $dependency);
			}
			$record->addFieldsRaw(array(
				"MODULE_ID" => $arTemplate["MODULE_ID"],
				"ENTITY" => $arTemplate["ENTITY"],
				"DOCUMENT_TYPE_0" => $arTemplate["DOCUMENT_TYPE"][0],
				"DOCUMENT_TYPE_1" => $arTemplate["DOCUMENT_TYPE"][1],
				"DOCUMENT_TYPE_2" => self::PREFIX_IBLOCK . $documentType2XmlId,
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

	/**
	 * @param RecordId $id
	 * @return string
	 */
	public function getXmlId($id)
	{
		$dbTemplatesList = \CBPWorkflowTemplateLoader::GetList(array(), array("ID" => $id->getValue()));
		if ($arTemplate = $dbTemplatesList->Fetch()) {
			return $this->calculateXmlId($arTemplate);
		}else {
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.BIZPROC_WORKFLOWTEMPLATE.GXI.EX')."($id)");
		}
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
			throw new \Exception(ExceptionText::getUnknown());
		}
		return $this->createId($returnId);
	}

	/**
	 * @param RecordId $id
	 * @throws \Exception
	 */
	protected function deleteInner(RecordId $id)
	{
		$dbWorkflowList = \CBPAllTaskService::GetList(
			array("ID" => "DESC"),
			Array("WORKFLOW_TEMPLATE_TEMPLATE_ID" => $id->getValue()),
			false,
			false,
			array("ID", "WORKFLOW_ID", "WORKFLOW_TEMPLATE_TEMPLATE_ID")
		);
		while($arWorkflow = $dbWorkflowList->Fetch()) {
			$terminate = true;
			$err = \CBPDocument::killWorkflow($arWorkflow["WORKFLOW_ID"], $terminate);
			if ($err) {
				throw new \Exception($err["message"]);
			}
		}
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
			throw new \Exception(ExceptionText::getUnknown());
		}
	}

	protected function recordToArray(Record $record)
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
		$arTemplate["PARAMETERS"] = $this->convertVariables($arTemplate["PARAMETERS"]);
		$arTemplate["VARIABLES"] = $this->convertVariables($arTemplate["VARIABLES"]);
		$arTemplate["CONSTANTS"] = $this->convertVariables($arTemplate["CONSTANTS"]);
		return $arTemplate;
	}

	/**
	 * @param mixed[] $arTemplate
	 * @return string
	 */
	protected function calculateXmlId($arTemplate)
	{
		$md5 = md5(serialize(array(
			$arTemplate["MODULE_ID"],
			$arTemplate["ENTITY"],
			$arTemplate["NAME"],
			$this->stringToXmlId($arTemplate["DOCUMENT_TYPE"][2]),
		)));
		return self::PREFIX_WORKFLOW_TEMPLATE . BaseXmlIdProvider::formatXmlId($md5);
	}

	/*
	 * @param mixed[] $arNode
	 * @param string[][] &$arDependency
	 * @param Record $record
	 * @return mixed[]
	*/
	protected function convertNode($arNode, &$arDependency=array(), $record=NULL)
	{
		$arResult = array();
		foreach ($arNode as $key => $value) {
			if ($key === 'Permission') {
				$arResult[$key] = $this->convertPermissionNode($value, $arDependency, $record);
			} elseif ($key === 'DocumentType') {
				$arResult[$key] = $this->convertDocumentTypeNode($value, $arDependency, $record);
			} elseif (
				in_array ($key, array(
				'Users', 'UserParameter', 'ReserveUserParameter', 'MessageUserFrom', 'MessageUserTo',
				'MailUserFromArray', 'MailUserToArray', 'CalendarUser', 'OwnerId', 'UsersTo', 'AbsenceUser',
				'CreatedBy','DeletedBy','Operator','EntityId'
				), true)
				&& is_array($value)
			) {
				$arResult[$key] = $this->convertUsersNode($value, $arDependency, $record);
			} elseif ($key === 'RESPONSIBLE_ID' && !is_array($value)) {
				$arResult[$key] = $this->convertRole($value, $arDependency, $record);
			} elseif (is_array($value)) {
				$arResult[$key] = $this->convertNode($value, $arDependency, $record);
			} else {
				$arResult[$key] = $value;
			}
		}
		return $arResult;
	}
	
	/*
	 * @param mixed[] $arNode
	 * @param string[][] &$arDependency
	 * @param Record $record
	 * @return mixed[]
	*/
	protected function convertVariables($arVariables, &$arDependency = array(), $record = NULL)
	{
		$arResult = array();
		foreach ($arVariables as $key => $arVariable) {
			$arResult[$key] = $this->convertVariableNode($arVariable, $arDependency, $record);
		}
		return $arResult;
	}

	/*
	 * @param mixed[] $arNode
	 * @param string[][] &$arDependency
	 * @param Record $record
	 * @return mixed[]
	*/
	protected function convertPermissionNode($arNode, &$arDependency, $record)
	{
		if (array_key_exists(0, $arNode) && !is_array($arNode[0])) {
			// это однорувневый узел Permission
			return $this->convertUsersNode($arNode, $arDependency, $record);
		} else {
			// это узел Permission у которого на первом уровне находятся права доступа
			$arResult = array();
			foreach ($arNode as $permission => $arRoles) {
				$arResult[$permission] = $this->convertUsersNode($arRoles, $arDependency, $record);
			}
			return $arResult;
		}
	}

	/*
	 * @param mixed[] $arNode
	 * @param string[][] &$arDependency
	 * @param Record $record
	 * @return mixed[]
	*/
	protected function convertUsersNode($arNode, &$arDependency, $record)
	{
		$arResult = array();
		foreach ($arNode as $role) {
			$arResult[] = $this->convertRole($role, $arDependency, $record);
		}
		return $arResult;
	}
	
	/*
	 * @param mixed[] $arNode
	 * @param string[][] &$arDependency
	 * @param Record $record
	 * @return mixed[]
	*/
	protected function convertVariableNode($arNode, &$arDependency, $record)
	{
		if (is_array($arNode) && array_key_exists('Type', $arNode) && $arNode['Type'] === 'user') {
			$arNode['Default'] = $this->convertRole($arNode['Default'], $arDependency, $record);
		}
		return $arNode;
	}
	
	/*
	 * @param string $role
	 * @param string[][] &$arDependency
	 * @param Record $record
	 * @return string
	*/
	protected function convertRole($role, &$arDependency, $record) {
		if ($id = $this->xmlIdToString($role)) {
			$role = (string)$id;
		} elseif ($xmlId = $this->stringToXmlId($role)) {
			if (!$this->xmlIdToString(self::PREFIX_USER_GROUP_LITERAL . $xmlId)) {
				$record->registerValidateError(
					Loc::getMessage('INTERVOLGA_MIGRATO.BIZPROC_WORKFLOWTEMPLATE.CPN.EX') ."($role)($xmlId)"
				);
			}
			$role = self::PREFIX_USER_GROUP_LITERAL . $xmlId;
			$arDependency['GROUP'][] = $xmlId;
		} elseif (is_numeric($role)) {
			$groupIdObject = RecordId::createNumericId(intval($role));
			$xmlId = MainGroup::getInstance()->getXmlId($groupIdObject);
			if (!strlen($xmlId)) {
				$record->registerValidateError(
					Loc::getMessage('INTERVOLGA_MIGRATO.BIZPROC_WORKFLOWTEMPLATE.CPN.EX3')."($role)"
				);
			}
			elseif (!$this->xmlIdToString(self::PREFIX_USER_GROUP_NUMERIC . $xmlId)) {
				$record->registerValidateError(
					Loc::getMessage('INTERVOLGA_MIGRATO.BIZPROC_WORKFLOWTEMPLATE.CPN.EX2')."($role)($xmlId)"
				);
			}
			$role = self::PREFIX_USER_GROUP_NUMERIC . $xmlId;
			$arDependency['GROUP'][] = $xmlId;
		}
		return $role;
	}
	
	/*
	 * @param mixed[] $arNode
	 * @param string[][] &$arDependency
	 * @param Record $record
	 * @return mixed[]
	*/
	protected function convertDocumentTypeNode($arNode, &$arDependency, $record)
	{
		$arResult = array();
		foreach ($arNode as $value) {
			if ($id = $this->xmlIdToString($value)) {
				$arResult[] = (string)$id;
			}elseif ($xmlId = $this->stringToXmlId($value)) {
				if (!$this->xmlIdToString(self::PREFIX_IBLOCK . $xmlId)) {
					$record->registerValidateError(
						Loc::getMessage('INTERVOLGA_MIGRATO.BIZPROC_WORKFLOWTEMPLATE.CPTN.EX')."($value)($xmlId)"
					);
				}
				$arResult[] = self::PREFIX_IBLOCK . $xmlId;
				$arDependency['IBLOCK'][] = $xmlId;
			}else {
				$arResult[] = $value;
			}
		}
		return $arResult;
	}

	/**
	 * @param string $field
	 * @return string
	 */
	protected function stringToXmlId($field)
	{
		if (preg_match("/^iblock_(?'id'\d+)$/", $field, $matches)) {
			$iblockIdObject = RecordId::createNumericId($matches['id']);
			return IblockIblock::getInstance()->getXmlId($iblockIdObject);
		}
		if (preg_match("/^group_g(?'id'\d+)$/", $field, $matches)) {
			$groupIdObject = RecordId::createNumericId($matches['id']);
			return MainGroup::getInstance()->getXmlId($groupIdObject);
		}
	}

	/**
	 * @param string $xmlId
	 * @return string
	 */
	protected function xmlIdToString($xmlId)
	{
		if (preg_match("/^" . self::PREFIX_IBLOCK . "(?'xmlId'[_a-zA-Z0-9\-]+)$/", $xmlId, $matches)) {
			$iblockLinkId = IblockIblock::getInstance()->findRecord($matches['xmlId']);
			return 'iblock_' . $iblockLinkId->getValue();
		}
		if (preg_match("/^" . self::PREFIX_USER_GROUP_NUMERIC . "(?'xmlId'[_a-zA-Z0-9\-]+)$/", $xmlId, $matches)) {
			$groupLinkId = MainGroup::getInstance()->findRecord($matches['xmlId']);
			return $groupLinkId->getValue();
		}
		if (preg_match("/^" . self::PREFIX_USER_GROUP_LITERAL . "(?'xmlId'[_a-zA-Z0-9\-]+)$/", $xmlId, $matches)) {
			$groupLinkId = MainGroup::getInstance()->findRecord($matches['xmlId']);
			return 'group_g' . $groupLinkId->getValue();
		}
	}
}