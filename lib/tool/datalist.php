<?php

namespace Intervolga\Migrato\Tool;

use Bitrix\Main\Event;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;

class DataList
{
	/**
	 * @return \Intervolga\Migrato\Data\BaseData[]
	 */
	public static function getAll()
	{
		$entities = array();
		$moduleDirPath = Loader::getLocal('modules/intervolga.migrato/lib/data/module/');
		$dir = new Directory($moduleDirPath);
		foreach ($dir->getChildren() as $module)
		{
			if ($module instanceof Directory)
			{
				foreach ($module->getChildren() as $classFile)
				{
					$entityName = str_replace(".php", "", $classFile->getName());
					$dataObject = static::getLocal($module->getName(), $entityName);
					if ($dataObject && Loader::includeModule($dataObject->getModule()))
					{
						$entities[] = $dataObject;
					}
				}
			}
		}

		$entities = array_merge($entities, DataList::getEventAdded());

		return $entities;
	}

	/**
	 * @param string $module
	 * @param string $entity
	 *
	 * @return \Intervolga\Migrato\Data\BaseData|null
	 */
	public static function get($module, $entity)
	{
		$extEntities = DataList::getEventAdded();
		$dataObject = static::getLocal($module, $entity);
		if ($dataObject)
		{
			return $dataObject;
		}
		elseif ($extEntities)
		{
			foreach ($extEntities as $extEntity)
			{
				$isSameModule = $extEntity->getModule() == $module;
				$isSameEntity = $extEntity->getEntityName() == $entity;
				if ($isSameModule && $isSameEntity)
				{
					return $extEntity;
				}
			}
		}

		return null;
	}

	/**
	 * @param string $module
	 * @param string $entity
	 * @return \Intervolga\Migrato\Data\BaseData|null
	 */
	protected static function getLocal($module, $entity)
	{
		$name = "\\Intervolga\\Migrato\\Data\\Module\\" . $module . "\\" . $entity;
		if (class_exists($name))
		{
			/**
			 * @var \Intervolga\Migrato\Data\BaseData $name
			 */
			$dataObject = $name::getInstance();

			return $dataObject;
		}

		return null;
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData[]
	 */
	protected static function getEventAdded()
	{
		$entities = array();
		$event = new Event('intervolga.migrato', 'OnMigratoDataBuildList');
		$event->send();
		$parameters = static::getEventResultParameters($event);
		foreach ($parameters as $parameter)
		{
			if ($parameter instanceof BaseData)
			{
				$entities[] = $parameter;
			}
		}

		return $entities;
	}

	/**
	 * @param \Bitrix\Main\Event $event
	 * @return array
	 */
	protected static function getEventResultParameters(Event $event)
	{
		$result = array();
		$eventResults = $event->getResults();
		foreach ($eventResults as $eventResult)
		{
			if (is_array($eventResult->getParameters()))
			{
				foreach ($eventResult->getParameters() as $parameter)
				{
					$result[] = $parameter;
				}
			}
		}

		return $result;
	}
}