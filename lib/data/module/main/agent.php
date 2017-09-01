<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;

Loc::loadMessages(__FILE__);

class Agent extends BaseData
{
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
			$record = new Record($this);
			$id = static::createId($agent['ID']);
			$record->setId($id);
			$record->setXmlId($this->getXmlId($id));
			$record->addFieldsRaw(array(
				'MODULE_ID' => $agent['MODULE_ID'],
				'NAME' => $agent['NAME'],
				'ACTIVE' => $agent['ACTIVE'],
				'AGENT_INTERVAL' => $agent['AGENT_INTERVAL'],
				'IS_PERIOD' => $agent['IS_PERIOD'],
			));
			$record->setId(static::createId($agent['ID']));

			$result[] = $record;
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$agent = \CAgent::getById($id->getValue())->fetch();
		$agent['NAME'] = strtolower($agent['NAME']);
		$agent['NAME'] = str_replace('();', '', $agent['NAME']);
		$agent['NAME'] = str_replace(');', '', $agent['NAME']);
		$agent['NAME'] = str_replace('(', '_', $agent['NAME']);
		$agent['NAME'] = str_replace('::', '-', $agent['NAME']);
		$agent['NAME'] = preg_replace('/[^a-z0-9-_]/', '_', $agent['NAME']);
		$agent['NAME'] = ltrim($agent['NAME'], '_');
		return $agent['NAME'];
	}

	public function update(Record $record)
	{
		$id = $this->findRecord($record->getXmlId());
		if ($id)
		{
			$agent = $record->getFieldsRaw();
			$updateRes = \CAgent::update($id->getValue(), $agent);
			if (!$updateRes)
			{
				global $APPLICATION;
				if ($exception = $APPLICATION->getException())
				{
					throw new \Exception($exception->getString());
				}
				else
				{
					throw new \Exception('Unknown update error');
				}
			}
			return $id;
		}
		else
		{
			throw new \Exception('Agent not found');
		}
	}
}