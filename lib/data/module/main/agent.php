<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Agent extends BaseData
{
	const ADMIN_USER_ID = 1;
	const DELAY_NEW_AGENT = 300;

	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_AGENT'));
		$this->setVirtualXmlId(true);
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$result = array();
		$agentsGetList = \CAgent::getList();
		while ($agent = $agentsGetList->fetch())
		{
			if ($record = $this->arrayToRecord($agent))
			{
				$result[] = $record;
			}
		}

		return $result;
	}

	/**
	 * @param array $agent
	 * @return Record|null
	 */
	protected function arrayToRecord(array $agent)
	{
		if (!$agent['USER_ID'] || $agent['USER_ID'] == static::ADMIN_USER_ID)
		{
			$record = new Record($this);
			$id = static::createId($agent['ID']);
			$record->setId($id);
			$record->setXmlId($this->getXmlId($id));
			$admin = 'N';
			if ($agent['USER_ID'] == static::ADMIN_USER_ID)
			{
				$admin = 'Y';
			}
			$record->addFieldsRaw(array(
				'MODULE_ID' => $agent['MODULE_ID'],
				'NAME' => $agent['NAME'],
				'ACTIVE' => $agent['ACTIVE'],
				'AGENT_INTERVAL' => $agent['AGENT_INTERVAL'],
				'IS_PERIOD' => $agent['IS_PERIOD'],
				'ADMIN' => $admin,
			));
			$record->setId(static::createId($agent['ID']));

			return $record;
		}
		else
		{
			return null;
		}
	}

	public function getXmlId($id)
	{
		$agent = \CAgent::getById($id->getValue())->fetch();
		$xmlId = $agent['NAME'];
		$xmlId = strtolower($xmlId);
		$xmlId = str_replace('();', '', $xmlId);
		$xmlId = str_replace(');', '', $xmlId);
		$xmlId = str_replace('(', '-', $xmlId);
		$xmlId = str_replace('::', '-', $xmlId);
		$xmlId = preg_replace('/[^a-z0-9-_]/', '_', $xmlId);
		$xmlId = ltrim($xmlId, '_');

		if ($agent['USER_ID'] == static::ADMIN_USER_ID)
		{
			$xmlId = '__admin_' . $xmlId;
		}
		return $xmlId;
	}

	protected function createInner(Record $record)
	{
		$agent = $this->recordToArray($record);
		$agent['NEXT_EXEC'] = convertTimeStamp(time() + static::DELAY_NEW_AGENT, 'FULL');
		$addRes = \CAgent::add($agent);
		if ($addRes)
		{
			return $this->createId($addRes);
		}
		else
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}

	public function update(Record $record)
	{
		$id = $this->findRecord($record->getXmlId());
		if ($id)
		{
			$agent = $this->recordToArray($record);
			$updateRes = \CAgent::update($id->getValue(), $agent);
			if (!$updateRes)
			{
				throw new \Exception(ExceptionText::getFromApplication());
			}
			return $id;
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.AGENT_NOT_FOUND'));
		}
	}

	/**
	 * @param Record $record
	 * @return \string[]
	 */
	protected function recordToArray(Record $record)
	{
		$agent = $record->getFieldsRaw();
		if ($agent['ADMIN'] == 'Y')
		{
			$agent['USER_ID'] = static::ADMIN_USER_ID;
		}
		unset($agent['ADMIN']);

		return $agent;
	}

	protected function deleteInner(RecordId $id)
	{
		$deleteRes = \CAgent::delete($id->getValue());
		if (!$deleteRes)
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}
}