<?php
namespace Intervolga\Migrato\Data\Module\landing;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;
use \Bitrix\Landing\Landing as CLanding;
use Intervolga\Migrato\Data\Link;

Loc::loadMessages(__FILE__);

class Landing extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		Loader::includeModule("landing");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.LANDING_LANDING_TYPE'));
		$this->setDependencies(array(
			'SITE' => new Link(Site::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = CLanding::getList();
		while ($landing = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($landing['ID']);
			$record->setId($id);
			$record->setXmlId($landing['CODE']);
			$record->addFieldsRaw(array(
				"CODE" => $landing["CODE"],
				"RULE" => $landing["RULE"],
				"ACTIVE" => $landing["ACTIVE"],
				"PUBLIC" => $landing["PUBLIC"],
				"TITLE" => $landing["TITLE"],
				"DESCRIPTION" => $landing["DESCRIPTION"],
				"SITEMAP" => $landing["SITEMAP"],
				"FOLDER" => $landing["FOLDER"],
				"FOLDER_ID" => $landing["FOLDER_ID"],
				"XML_ID" => $landing["XML_ID"],
				//"SITE_ID" => $landing["SITE_ID"],
			));
			$dependency = clone $this->getDependency("SITE");
			$dependency->setValue(
				Site::getInstance()->getXmlId(Site::getInstance()->createId($landing['SITE_ID']))
			);
			$record->setDependency("SITE", $dependency);
			$result[] = $record;
		}
		return $result;
	}

	public function getXmlId($id)
	{
		$getlist = CLanding::getList(array(
			"filter" => array(
				"=ID" => $id->getValue(),
			),
			"select" => array(
				"CODE",
			)
		))->fetch();
		if ($getlist)
		{
			return $getlist["CODE"];
		}
		else
		{
			return "";
		}
	}


	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		global $strError;
		$strError = '';
		$result = CLanding::update($id,$data);
		if (!$result)
		{
			if ($strError)
			{
				throw new \Exception($strError);
			} else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.LANDING_UNKNOWN_ERROR'));
			}
		}
	}

	/**
	 * @param Record $record
	 * @return array
	 */
	protected function recordToArray(Record $record)
	{
		$array = array(
			'CODE' => $record->getFieldRaw('CODE'),
			'RULE' => $record->getFieldRaw('RULE'),
			'ACTIVE' => $record->getFieldRaw('ACTIVE'),
			'PUBLIC' => $record->getFieldRaw('PUBLIC'),
			'TITLE' => $record->getFieldRaw('TITLE'),
			'DESCRIPTION' => $record->getFieldRaw('DESCRIPTION'),
			'SITEMAP' => $record->getFieldRaw('SITEMAP'),
			'FOLDER' => $record->getFieldRaw('FOLDER'),
			"FOLDER_ID" => $record->getFieldRaw("FOLDER_ID"),
			'XML_ID' => $record->getFieldRaw('XML_ID'),
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
		global $strError;
		$strError = '';
		$result = CLanding::add($data);
		if ($result)
		{
			if (!$result->isSuccess())
			{
				throw new \Exception(ExceptionText::getFromResult($result));
			}
			else
			{
				return $this->createId($result->getId());
			}
		} else
		{
			if ($strError)
			{
				throw new \Exception($strError);
			} else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.LANDING_UNKNOWN_ERROR'));
			}
		}
	}

	protected function deleteInner(RecordId $id)
	{
		CLanding::delete($id->getValue());
	}
}