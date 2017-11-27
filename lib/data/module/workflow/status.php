<?php
namespace Intervolga\Migrato\Data\Module\WorkFlow;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class Status extends BaseData
{
	protected function configure()
	{
		Loader::includeModule('workflow');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.WORKFLOW_STATUS'));
	}

	public function getList(array $filter = array())
	{
		$result = array();

		if ($arStatuses = $this->getWorkFlowStatus())
		{
			foreach ($arStatuses as $idStatus => $status)
			{
				$result[] = $this->makeRecord($status);
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	protected function getWorkFlowStatus()
	{
		$arStatus = array();

		$rsStatus = \CWorkflowStatus::GetList(
			$by = 's_id',
			$order = 'desc',
			array(),
			$is_filtered = false,
			array()
		);

		while ($arResStatus = $rsStatus->fetch())
		{
			$arStatus[] = $arResStatus;
		}

		return $arStatus;
	}

	/**
	 * @param array $status
	 *
	 * @return \Intervolga\Migrato\Data\Record
	 */
	protected function makeRecord($status)
	{
		$record = array();

		if ($status)
		{
			$record = new Record($this);
			$id = $this->createId($status["TITLE"]);
			$record->setId($id);
			$record->setXmlId($this->getXmlId($id));

			$record->addFieldsRaw(array(
				'C_SORT' => $status['C_SORT'],
				'ACTIVE' => $status['ACTIVE'],
				'TITLE' => $status['TITLE'],
				'DESCRIPTION' => $status['DESCRIPTION'] ? : '',
				'IS_FINAL' => $status['IS_FINAL'],
				'TIMESTAMP_X_TEMP' => $status['TIMESTAMP_X_TEMP'],
				'TIMESTAMP_X' => $status['TIMESTAMP_X'],
				'NOTIFY' => $status['NOTIFY'],
			));
		}

		return $record;
	}

	public function createId($id)
	{
		return RecordId::createStringId(strval($id));
	}

	public function getXmlId($id)
	{
		$md5 = md5(serialize(array($id)));
		return BaseXmlIdProvider::formatXmlId($md5);
	}

	protected function deleteInner(RecordId $record)
	{
		$name = $record->getValue();
		global $DB;
		$DB->Query("DELETE FROM b_workflow_status WHERE TITLE='" . $name . "'");
	}

	public function createInner(Record $record)
	{
		$result = $record->getFieldsRaw();

		$obWorkflowStatus = new \CWorkflowStatus;

		$obWorkflowStatus->Add(
			array(
				'C_SORT' => $result['C_SORT'],
				'ACTIVE' => $result['ACTIVE'],
				'TITLE' => $result['TITLE'],
				'DESCRIPTION' => $result['DESCRIPTION'] ? : '',
				'IS_FINAL' => $result['IS_FINAL'],
				'TIMESTAMP_X_TEMP' => $result['TIMESTAMP_X_TEMP'],
				'TIMESTAMP_X' => $result['TIMESTAMP_X'],
				'NOTIFY' => $result['NOTIFY'],
			)
		);

		return $this->createId($result['TITLE']);
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsRaw();

		$obWorkflowStatus = new \CWorkflowStatus;
		$rsStatus = $obWorkflowStatus::GetList(
			$by = 's_id',
			$order = 'desc',
			array(
				'TITLE' => $fields['TITLE'],
			),
			$is_filtered = false,
			array()
		);

		while ($status = $rsStatus->fetch())
		{
			$obWorkflowStatus->Update($status['ID'], $fields);
		}
	}
}