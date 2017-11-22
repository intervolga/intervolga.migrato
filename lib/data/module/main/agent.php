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
				try
				{
					$this->verifyAgentParams($agent);
				}
				catch (\Exception $exception)
				{
					$record->setXmlId('');
				}
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
		$this->verifyAgentParams($agent);
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

	/**
	 * @param array $agent
	 * @throws \Exception
	 */
	protected function verifyAgentParams(array $agent)
	{
		//Check module
		if($agent['MODULE_ID'])
		{
			$moduleInstalled = \IsModuleInstalled($agent['MODULE_ID']);
			if ($moduleInstalled)
				\Bitrix\Main\Loader::includeModule($agent['MODULE_ID']);
			else
				throw new \Exception(
					Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_AGENT_MODULE_NOT_INSTALLED',
						array(
							'#MODULE#' => $agent['MODULE_ID'],
						)
					)
				);
		}
		$colonPos = strpos($agent['NAME'],'::');
		$isMethod = ($colonPos !== false);
		if($isMethod)
		{
			//Check class
			$className = substr($agent['NAME'] ,0 ,$colonPos); //text before colons
			$className = $className ? trim($className) : $className;
			if (!$className || !class_exists($className))
				throw new \Exception(
					Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_AGENT_CLASS_NOT_EXISTS',
						array(
							'#CLASS#' => $className,
						)
					)
				);
			//Check method
			$method = substr($agent['NAME'],$colonPos+2); //text after colons
			$method = $method ? trim($method) : $method;
			if($method)
			{
				$bracketPos = strpos($method, '('); //text before bracket
				if($bracketPos !== false)
				{
					$method = substr($method, 0, $bracketPos);
					$method = $method ? trim($method) : $method;
				}
			}
			if(!$method || !method_exists($className, $method))
				throw new \Exception(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.MAIN_AGENT_METHOD_NOT_EXISTS',
						array(
							'#CLASS#' => $className,
							'#METHOD#' => $method,
						)
					)
				);
		}
		else
		{
			//Check function
			$bracketPos = strpos($agent['NAME'],'(');
			if($bracketPos !== false)
			{
				$function = substr($agent['NAME'], 0, $bracketPos);
				$function = $function ? trim($function) : $function;
			}
			if(!$function || !function_exists($function))
				throw new \Exception(
					Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_AGENT_FUNCTION_NOT_EXISTS',
						array(
							'#FUNCTION#' => $function,
						)
					)
				);
		}
	}

	public function update(Record $record)
	{
		$id = $this->findRecord($record->getXmlId());
		if ($id)
		{
			$agent = $this->recordToArray($record);
			$this->verifyAgentParams($agent);
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