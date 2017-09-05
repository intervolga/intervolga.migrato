<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\LanguageTable;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Language extends BaseData
{
	public function getEntityNameLoc()
	{
		return Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_LANGUAGE');
	}

	public function getFilesSubdir()
	{
		return '/loc/';
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = LanguageTable::getList();
		while ($language = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($language['LID']);
			$record->setId($id);
			$record->setXmlId($language['LID']);
			$record->addFieldsRaw(array(
				'SORT' => $language['SORT'],
				'DEF' => $language['DEF'],
				'ACTIVE' => $language['ACTIVE'],
				'NAME' => $language['NAME'],
			));

			$link = clone $this->getDependency('CULTURE');
			$link->setValue(
				Culture::getInstance()->getXmlId(
					Culture::getInstance()->createId($language['CULTURE_ID'])
				)
			);
			$record->setDependency('CULTURE', $link);
			$result[] = $record;
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			'CULTURE' => new Link(Culture::getInstance()),
		);
	}

	public function createId($id)
	{
		return RecordId::createStringId($id);
	}

	public function getXmlId($id)
	{
		$language = LanguageTable::getById($id->getValue())->fetch();
		return $language['LID'];
	}

	public function setXmlId($id, $xmlId)
	{
		LanguageTable::update($id->getValue(), array('LID' => $xmlId));
	}

	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		$result = LanguageTable::update($id, $data);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
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

		return $array;
	}

	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		$result = LanguageTable::add($data);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
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
			$result = LanguageTable::delete($id->getValue());
			if (!$result->isSuccess())
			{
				throw new \Exception(ExceptionText::getFromResult($result));
			}
		}
	}
}