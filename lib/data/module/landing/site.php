<?php
namespace Intervolga\Migrato\Data\Module\landing;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Seo\Engine\Bitrix;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use \Bitrix\Landing\Internals\SiteTable as CLandingSite;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Site extends BaseData
{
	protected function configure()
	{
		$this->setVirtualXmlId(true);
		Loader::includeModule("landing");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.LANDING_SITE_TYPE'));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = CLandingSite::getList();
		while ($site = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($site['ID']);
			$record->setId($id);
			$record->setXmlId($site['CODE']);
			$record->addFieldsRaw(array(
				"CODE" => $site["CODE"],
				"ACTIVE" => $site["ACTIVE"],
				'XML_ID' => $site["XML_ID"],
				'DESCRIPTION' => $site["DESCRIPTION"],
				'TYPE' => $site["TYPE"],
				'TPL_ID' => $site["TPL_ID"],
				'DOMAIN_ID' => $site["DOMAIN_ID"],
				'SMN_SITE_ID' => $site["SMN_SITE_ID"],
				'LANDING_ID_INDEX' => $site["LANDING_ID_INDEX"],
				'LANDING_ID_404' => $site["LANDING_ID_404"],
			));

			$result[] = $record;
		}
		return $result;
	}

	public function getXmlId($id)
	{
//		$site = CLandingSite::getById($id->getValue())->fetch();
//		return $site;
		return $id->getValue();
	}

//	public function createId($id)
//	{
//		return RecordId::createStringId($id);
//	}


	public function update(Record $record)
	{
		$data = $this->recordToArray($record);
		$id = $record->getId()->getValue();
		global $strError;
		$strError = '';
		$result = CLandingSite::update($id,$data);
		if (!$result)
		{
			if ($strError)
			{
				throw new \Exception($strError);
			} else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.LANDING_SITE_UNKNOWN_ERROR'));
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
			'CODE' => $record->getXmlId(),
			'ACTIVE' => $record->getFieldRaw('ACTIVE'),
			'TITLE' => $record->getFieldRaw('TITLE'),
			'XML_ID' => $record->getFieldRaw('XML_ID'),
			'DESCRIPTION' => $record->getFieldRaw('DESCRIPTION'),
			'TYPE' => $record->getFieldRaw('TYPE'),
			'TPL_ID' => $record->getFieldRaw('TPL_ID'),
			'DOMAIN_ID' => $record->getFieldRaw('DOMAIN_ID'),
			'SMN_SITE_ID' => $record->getFieldRaw('SMN_SITE_ID'),
			'LANDING_ID_INDEX' => $record->getFieldRaw('LANDING_ID_INDEX'),
			'LANDING_ID_404' => $record->getFieldRaw('LANDING_ID_404'),
		);

		return $array;
	}

	protected function createInner(Record $record)
	{
		$data = $this->recordToArray($record);
		$data = $this->addRequiredFields($data);
		global $strError;
		$strError = '';
		$result = CLandingSite::add($data);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		} else
		{
			return $this->createId($result->getId());
		}
	}

	protected function deleteInner(RecordId $id)
	{
		CLandingSite::delete($id->getValue());
	}

	protected function addRequiredFields(array $data)
	{
		$data['CREATED_BY_ID'] = \Intervolga\Migrato\Data\Module\Main\Agent::ADMIN_USER_ID;
		$data['MODIFIED_BY_ID'] = \Intervolga\Migrato\Data\Module\Main\Agent::ADMIN_USER_ID;
		$data['DATE_CREATE'] = new \Bitrix\Main\Type\DateTime();
		$data['DATE_MODIFY'] = new \Bitrix\Main\Type\DateTime();
		return $data;
	}
}