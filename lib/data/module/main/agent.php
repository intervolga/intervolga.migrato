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

			\Bitrix\Main\Diag\Debug::writeToFile(__FILE__ . ':' . __LINE__ . "\n(" . date('Y-m-d H:i:s').")\n" . print_r($record->info(), TRUE) . "\n\n", '', 'log/__debug.log');

			$result[] = $record;
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$agent = \CAgent::getById($id->getValue())->fetch();
		return $agent['NAME'];
	}
}