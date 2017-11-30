<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\OperationTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

class Task extends BaseData
{
	const XML_ID_SEPARATOR = "___";

	protected function configure()
	{
		$this->setVirtualXmlId(true);
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_TASK'));
		$this->setFilesSubdir('/');
	}

	public function getList(array $filter = array())
	{
		$dbRes = \CTask::GetList(array(), array("BINDING" => "module"));

		$result = array();
		while ($task = $dbRes->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($task['ID']);

			if ($id)
			{
				$operationsId = \CTask::GetOperations($task['ID']);
				$rsOperations = OperationTable::getList(
					array(
						'select' => array(
							'NAME',
						),
						'filter' => array(
							'ID' => $operationsId,
						),
					)
				)->fetchAll();
				$operations = array();
				foreach ($rsOperations as $operation)
				{
					$operations[] = $operation["NAME"];
				}
				$record->setId($id);
				$record->setXmlId(static::createXmlId($task));
				$record->addFieldsRaw(array(
					'NAME' => $task['NAME'],
					'DESCRIPTION' => $task['DESCRIPTION'],
					'MODULE_ID' => $task['MODULE_ID'],
					'LETTER' => $task['LETTER'],
					'SYS' => $task['SYS'],
					'TITLE' => $task['TITLE'],
					'DESC' => $task['DESC'],
					'OPERATION' => $operations,
				));
				$result[] = $record;
			}
		}

		return $result;
	}

	/**
	 * @param array $fields
	 * @return string
	 */
	public static function createXmlId($fields)
	{
		$fields['MODULE_ID'] = str_replace(".", "_", $fields['MODULE_ID']);
		return strtolower($fields['MODULE_ID']) . static::XML_ID_SEPARATOR . $fields['LETTER'];
	}

	/**
	 * @param string $xmlId
	 * @return array
	 */
	public static function getFieldsFromXmlId($xmlId)
	{
		$explode = explode(static::XML_ID_SEPARATOR, $xmlId);
		$fields = array(
			'MODULE_ID' => trim(str_replace('_', '.', $explode[0])),
			'LETTER' => trim($explode[1]),
		);

		return $fields;
	}

	public function getXmlId($id)
	{
		$dbRes = \CTask::GetList(array(), array('ID' => $id));
		while ($task = $dbRes->fetch())
		{
			return static::createXmlId($task);
		}

		return '';
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$id = \CTask::Add($fields);
		$rsOperationsID = OperationTable::getList(
			array(
				'select' => array(
					'ID',
				),
				'filter' => array(
					'NAME' => $fields["OPERATION"],
				),
			)
		)->fetchAll();
		$operationsID = array();
		foreach ($rsOperationsID as $operationID)
		{
			$operationsID[] = $operationID["ID"];
		}
		\CTask::SetOperations($id, $operationsID);
		if (is_int($id))
		{
			return $this->createId($id);
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_TASK_ADD_ERROR'));
		}
	}


	protected function deleteInner(RecordId $id)
	{
		$idVal = $id->getValue();
		if (is_int($idVal))
		{
			\CTask::Delete($idVal);
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_TASK_DELETE_ERROR'));
		}
	}

	public function update(Record $record)
	{
		$xmlId = $record->getXmlId();
		$recordId = $this->findRecord($xmlId);
		if ($recordId)
		{
			$id = $recordId->getValue();
			$fields = $record->getFieldsRaw();
			$rsOperationsID = OperationTable::getList(
				array(
					'select' => array(
						'ID',
					),
					'filter' => array(
						'NAME' => $fields["OPERATION"],
					),
				)
			)->fetchAll();
			$operationsID = array();
			foreach ($rsOperationsID as $operationID)
			{
				$operationsID[] = $operationID["ID"];
			}
			\CTask::SetOperations($id, $operationsID);
			if (!\CTask::Update($fields, $id))
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_TASK_UPDATE_ERROR'));
			}
		}
	}
}