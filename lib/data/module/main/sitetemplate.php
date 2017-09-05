<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTemplateTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class SiteTemplate extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_SITE_TEMPLATE'));
	}

	public function getFilesSubdir()
	{
		return '/site/';
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = SiteTemplateTable::getList();
		while ($siteTemplate = $getList->fetch())
		{
			if ($siteTemplate['TEMPLATE'])
			{
				$record = new Record($this);
				$record->setId($this->createId($siteTemplate['ID']));
				$record->setXmlId($this->getMd5($siteTemplate));
				$record->addFieldsRaw(array(
					'CONDITION' => $siteTemplate['CONDITION'],
					'SORT' => $siteTemplate['SORT'],
					'TEMPLATE' => $siteTemplate['TEMPLATE'],
				));

				$link = clone $this->getDependency('SITE');
				$link->setValue(
					Site::getInstance()->getXmlId(
						Site::getInstance()->createId($siteTemplate['SITE_ID'])
					)
				);
				$record->setDependency('SITE', $link);

				$result[] = $record;
			}
		}
		return $result;
	}

	public function getDependencies()
	{
		return array(
			'SITE' => new Link(Site::getInstance()),
		);
	}

	public function getXmlId($id)
	{
		$getList = SiteTemplateTable::getList(array(
			'filter' => array(
				'=ID' => $id->getValue(),
			),
		));

		$siteTemplate = $getList->fetch();
		return $this->getMd5($siteTemplate);
	}

	/**
	 * @param array $tpl
	 *
	 * @return string
	 */
	protected function getMd5(array $tpl)
	{
		$md5 = md5($tpl['CONDITION']);

		return BaseXmlIdProvider::formatXmlId($md5, $tpl['SITE_ID'] . '-' . $tpl['TEMPLATE'] . '-');
	}

	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		$result = SiteTemplateTable::update($id, $data);
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
			'CONDITION' => $record->getFieldRaw('CONDITION'),
			'SORT' => $record->getFieldRaw('SORT'),
			'TEMPLATE' => $record->getFieldRaw('TEMPLATE'),
		);

		if ($dependency = $record->getDependency('SITE'))
		{
			$linkXmlId = $dependency->getValue();
			$idObject = Site::getInstance()->findRecord($linkXmlId);
			if ($idObject)
			{
				$array['SITE_ID'] = $idObject->getValue();
			}
		}

		return $array;
	}

	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		$result = SiteTemplateTable::add($data);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
		else
		{
			return $this->createId($result->getId());
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$result = SiteTemplateTable::delete($id->getValue());
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}
}