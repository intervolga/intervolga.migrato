<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

Loc::loadMessages(__FILE__);

class Site extends BaseData
{
	public function getEntityNameLoc()
	{
		return Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_SITE');
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$result = array();

		$sitesGetList = SiteTable::getList();
		while ($site = $sitesGetList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($site['LID']);
			$record->setId($id);
			$record->setXmlId($site['LID']);
			$record->addFieldsRaw(array(
				'SORT' => $site['SORT'],
				'DEF' => $site['DEF'],
				'ACTIVE' => $site['ACTIVE'],
				'NAME' => $site['NAME'],
				'DIR' => $site['DIR'],
				'DOMAIN_LIMITED' => $site['DOMAIN_LIMITED'],
				'SITE_NAME' => $site['SITE_NAME'],
			));

			$link = clone $this->getDependency('CULTURE');
			$link->setValue(
				Culture::getInstance()->getXmlId(
					Culture::getInstance()->createId($site['CULTURE_ID'])
				)
			);
			$record->setDependency('CULTURE', $link);

			$link = clone $this->getDependency('LANGUAGE');
			$link->setValue(
				Language::getInstance()->getXmlId(
					Language::getInstance()->createId($site['LANGUAGE_ID'])
				)
			);
			$record->setDependency('LANGUAGE', $link);

			$result[] = $record;
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			'LANGUAGE' => new Link(Language::getInstance()),
			'CULTURE' => new Link(Culture::getInstance()),
		);
	}

	public function createId($id)
	{
		return RecordId::createStringId($id);
	}

	public function getXmlId($id)
	{
		return $id->getValue();
	}

	public function setXmlId($id, $xmlId)
	{
		SiteTable::update($id->getValue(), array('LID' => $xmlId));
	}

	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		$result = SiteTable::update($id, $data);
		if ($result->getErrorMessages())
		{
			throw new \Exception(implode(', ', $result->getErrorMessages()));
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function recordToArray(Record $record)
	{
		$array = array(
			'LID' => $record->getXmlId(),
			'SORT' => $record->getFieldRaw('SORT'),
			'DEF' => $record->getFieldRaw('DEF'),
			'ACTIVE' => $record->getFieldRaw('ACTIVE'),
			'NAME' => $record->getFieldRaw('NAME'),
			'DIR' => $record->getFieldRaw('DIR'),
			'DOMAIN_LIMITED' => $record->getFieldRaw('DOMAIN_LIMITED'),
			'SITE_NAME' => $record->getFieldRaw('SITE_NAME'),
		);

		if ($dependency = $record->getDependency('CULTURE'))
		{
			$linkXmlId = $dependency->getValue();
			$idObject = Culture::getInstance()->findRecord($linkXmlId);
			if ($idObject)
			{
				$array['CULTURE_ID'] = $idObject->getValue();
			}
		}
		if ($dependency = $record->getDependency('LANGUAGE'))
		{
			$linkXmlId = $dependency->getValue();
			$idObject = Language::getInstance()->findRecord($linkXmlId);
			if ($idObject)
			{
				$array['LANGUAGE_ID'] = $idObject->getValue();
			}
		}

		return $array;
	}

	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		$result = SiteTable::add($data);
		if ($result->getErrorMessages())
		{
			throw new \Exception(implode(', ', $result->getErrorMessages()));
		}
		else
		{
			return $this->createId($result->getId());
		}
	}

	protected function deleteInner($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if ($id)
		{
			SiteTable::delete($id->getValue());
		}
	}
}