<?php
namespace Intervolga\Migrato\Data\Module\form;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;

Loc::loadMessages(__FILE__);

class Status extends BaseData
{
	protected function configure()
	{
		Loader::includeModule("form");
		$this->setVirtualXmlId(true);
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_STATUS_TYPE'));
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
		while ($form = $getList->Fetch())
		{
			$rsStatuses = \CFormStatus::GetList(
				$form["ID"],
				$by = "s_id",
				$order = "desc",
				array(),
				$isFiltered
			);
			while ($status = $rsStatuses->Fetch())
			{
				$record = new Record($this);
				$id = $this->createId($status['ID']);
				$record->setId($id);
				$record->setXmlId(md5($status["DEFAULT_VALUE"] . $status['TITLE'] . $status['DESCRIPTION']));
				\CFormStatus::GetPermissionList($status["ID"], $status["arPERMISSION_VIEW"], $status["arPERMISSION_MOVE"], $status["arPERMISSION_EDIT"], $status["arPERMISSION_DELETE"]);
				$record->addFieldsRaw(array(
					"CSS" => $status["CSS"],
					"FORM_ID" => $status["FORM_ID"],
					"C_SORT" => $status["C_SORT"],
					"ACTIVE" => $status["ACTIVE"],
					"TITLE" => $status["TITLE"],
					"DESCRIPTION" => $status["DESCRIPTION"],
					"DEFAULT_VALUE" => $status["DEFAULT_VALUE"],
					"HANDLER_OUT" => $status["HANDLER_OUT"],
					"HANDLER_IN" => $status["HANDLER_IN"],
					"arPERMISSION_VIEW" => $status["arPERMISSION_VIEW"],
					"arPERMISSION_MOVE" => $status["arPERMISSION_MOVE"],
					"arPERMISSION_EDIT" => $status["arPERMISSION_EDIT"],
					"arPERMISSION_DELETE" => $status["arPERMISSION_DELETE"],
				));
				$getTemplateList = \CFormStatus::GetTemplateList($record->getId()->getValue());
				if ($getTemplateList['reference_id'])
				{
					$record->addFieldsRaw(array(
						"SEND_EMAIL" => "Y",
					));
				}
				$dependency = clone $this->getDependency("FORM");
				$dependency->setValue(
					Form::getInstance()->getXmlId(RecordId::createNumericId($status['FORM_ID']))
				);
				$record->setDependency("FORM", $dependency);
				$result[] = $record;
			}
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$status = \CFormStatus::GetByID($id->getValue())->Fetch();
		return md5($status['DEFAULT_VALUE'] . $status['TITLE'] . $status['DESCRIPTION']);
	}

	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		global $strError;
		$strError = '';
		$result = \CFormStatus::Set($data, $id);
		if ($data["SEND_EMAIL"])
		{
			\CFormStatus::SetMailTemplate($data["FORM_ID"], $result, "Y", '', true);
		}
		else
		{
			$arr = \CFormStatus::GetTemplateList($id);
			while (list($num, $tmp_id) = each($arr['reference_id']))
			{
				\CEventMessage::Delete($tmp_id);
			}
		}
		if (!$result)
		{
			if ($strError)
			{
				throw new \Exception($strError);
			}
			else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_STATUS_UNKNOWN_ERROR'));
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
			'CSS' => $record->getFieldRaw('CSS'),
			'FORM_ID' => $record->getFieldRaw('FORM_ID'),
			'C_SORT' => $record->getFieldRaw('C_SORT'),
			'ACTIVE' => $record->getFieldRaw('ACTIVE'),
			'TITLE' => $record->getFieldRaw('TITLE'),
			'DESCRIPTION' => $record->getFieldRaw('DESCRIPTION'),
			'DEFAULT_VALUE' => $record->getFieldRaw('DEFAULT_VALUE'),
			'HANDLER_OUT' => $record->getFieldRaw('HANDLER_OUT'),
			'HANDLER_IN' => $record->getFieldRaw('HANDLER_IN'),
			'arPERMISSION_VIEW' => $record->getFieldsRaw('arPERMISSION_VIEW'),
			'arPERMISSION_MOVE' => $record->getFieldsRaw('arPERMISSION_MOVE'),
			'arPERMISSION_EDIT' => $record->getFieldsRaw('arPERMISSION_EDIT'),
			'arPERMISSION_DELETE' => $record->getFieldsRaw('arPERMISSION_DELETE'),
			'SEND_EMAIL' => $record->getFieldRaw('SEND_EMAIL')
		);

		if ($form = $record->getDependency("FORM"))
		{
			if ($form->getId())
			{
				$array["FORM_ID"] = $form->getId()->getValue();
			}
		}

		return $array;
	}

	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		global $strError;
		$strError = '';
		$result = \CFormStatus::Set($data);
		if ($result)
		{
			if ($data["SEND_EMAIL"])
			{
				\CFormStatus::SetMailTemplate($data["FORM_ID"], $result, "Y", '', true);
			}
			return $this->createId($result);
		}
		else
		{
			if ($strError)
			{
				throw new \Exception($strError);
			}
			else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.FORM_STATUS_UNKNOWN_ERROR'));
			}
		}
	}

	protected function deleteInner(RecordId $id)
	{
		\CFormStatus::Delete($id->getValue());
	}
}