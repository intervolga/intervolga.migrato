<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\InheritedProperty\IblockTemplates;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Module\Main\Site;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Value;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

Loc::loadMessages(__FILE__);

class Iblock extends BaseData
{
	protected function configure()
	{
		Loader::includeModule('iblock');
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Iblock\\IblockTable");
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_IBLOCK'));
		$this->setFilesSubdir('/type/');
		$this->setDependencies(array(
			'IBLOCK_TYPE_ID' => new Link(Type::getInstance()),
			'SITE' => new Link(Site::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$order = array("ID" => "ASC");
		$iblockFilter = array();
		if ($filter)
		{
			$iblockFilter["XML_ID"] = $filter;
		}
		$getList = \CIBlock::GetList($order, $iblockFilter);
		while ($iblock = $getList->fetch())
		{
			$record = new Record($this);
			$record->setXmlId($iblock["XML_ID"]);
			$record->setId(RecordId::createNumericId($iblock["ID"]));
			$record->addFieldsRaw(array(
				"CODE" => $iblock["CODE"],
				"NAME" => $iblock["NAME"],
				"ACTIVE" => $iblock["ACTIVE"],
				"SORT" => $iblock["SORT"],
				"VERSION" => $iblock["VERSION"],
				"LIST_PAGE_URL" => $iblock["LIST_PAGE_URL"],
				"DETAIL_PAGE_URL" => $iblock["DETAIL_PAGE_URL"],
				"SECTION_PAGE_URL" => $iblock["SECTION_PAGE_URL"],
				"CANONICAL_PAGE_URL" => $iblock["CANONICAL_PAGE_URL"],
				"DESCRIPTION" => $iblock["DESCRIPTION"],
				"DESCRIPTION_TYPE" => $iblock["DESCRIPTION_TYPE"],
				"RSS_TTL" => $iblock["RSS_TTL"],
				"RSS_ACTIVE" => $iblock["RSS_ACTIVE"],
				"RSS_FILE_ACTIVE" => $iblock["RSS_FILE_ACTIVE"],
				"RSS_FILE_LIMIT" => intval($iblock["RSS_FILE_LIMIT"]),
				"RSS_FILE_DAYS" => intval($iblock["RSS_FILE_DAYS"]),
				"RSS_YANDEX_ACTIVE" => $iblock["RSS_YANDEX_ACTIVE"],
				"INDEX_ELEMENT" => $iblock["INDEX_ELEMENT"],
				"INDEX_SECTION" => $iblock["INDEX_SECTION"],
				"SECTION_CHOOSER" => $iblock["SECTION_CHOOSER"],
				"LIST_MODE" => $iblock["LIST_MODE"],
				"EDIT_FILE_BEFORE" => $iblock["EDIT_FILE_BEFORE"],
				"EDIT_FILE_AFTER" => $iblock["EDIT_FILE_AFTER"],
				"SECTION_PROPERTY" => $iblock["SECTION_PROPERTY"],
			));
			$this->addLanguageStrings($record);
			$this->addFieldsSettings($record);
			$this->addSeoSettings($record);
			$this->addDependencies($record, $iblock);

			$result[] = $record;
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected function addLanguageStrings(Record $record)
	{
		$messages = \CIBlock::getMessages($record->getId()->getValue());
		if ($messages)
		{
			$messagesValues = Value::treeToList($messages, "MESSAGES");
			$record->addFieldsRaw($messagesValues);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 */
	protected function addFieldsSettings(Record $record)
	{
		$fields = \CIBlock::getFields($record->getId()->getValue());
		if ($fields)
		{
			foreach ($fields as $k => $field)
			{
				unset($field['NAME']);
				$field = $this->exportWatermarkSettings($k, $field);
				$fields[$k] = $field;
			}
			$fieldsValues = Value::treeToList($fields, "FIELDS");
			$record->addFieldsRaw($fieldsValues);
		}
	}

	/**
	 * @param string $name
	 * @param array $field
	 *
	 * @return array
	 */
	protected function exportWatermarkSettings($name, array $field)
	{
		$pictures = array(
			'PREVIEW_PICTURE',
			'DETAIL_PICTURE',
			'SECTION_PICTURE',
			'SECTION_DETAIL_PICTURE',
		);
		if (in_array($name, $pictures))
		{
			if (!array_key_exists('USE_WATERMARK_TEXT', $field['DEFAULT_VALUE']))
			{
				$field['DEFAULT_VALUE']['USE_WATERMARK_TEXT'] = 'N';
			}
			if (!array_key_exists('USE_WATERMARK_FILE', $field['DEFAULT_VALUE']))
			{
				$field['DEFAULT_VALUE']['USE_WATERMARK_FILE'] = 'N';
			}
		}
		$dv = $field['DEFAULT_VALUE'];
		if (is_array($dv) && $dv)
		{
			if ($dv['USE_WATERMARK_TEXT'] == 'N')
			{
				unset($field['DEFAULT_VALUE']['WATERMARK_TEXT']);
				unset($field['DEFAULT_VALUE']['WATERMARK_TEXT_FONT']);
				unset($field['DEFAULT_VALUE']['WATERMARK_TEXT_COLOR']);
				unset($field['DEFAULT_VALUE']['WATERMARK_TEXT_SIZE']);
				unset($field['DEFAULT_VALUE']['WATERMARK_TEXT_POSITION']);
			}
			if ($dv['USE_WATERMARK_FILE'] == 'N')
			{
				unset($field['DEFAULT_VALUE']['WATERMARK_FILE']);
				unset($field['DEFAULT_VALUE']['WATERMARK_FILE_ALPHA']);
				unset($field['DEFAULT_VALUE']['WATERMARK_FILE_POSITION']);
				unset($field['DEFAULT_VALUE']['WATERMARK_FILE_ORDER']);
			}
		}
		return $field;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @throws \Exception
	 */
	protected function addSeoSettings(Record $record)
	{
		$seoProps = new IblockTemplates($record->getId()->getValue());
		$templates = $this->getEmptySeoProperties();
		if ($templates = array_merge($templates, $seoProps->findTemplates()))
		{
			foreach ($templates as $k => $template)
			{
				if (array_key_exists('TEMPLATE', $template))
				{
					$templates[$k] = $template["TEMPLATE"];
				}
			}
			$fieldsValues = Value::treeToList($templates, "SEO");
			$record->addFieldsRaw($fieldsValues);
		}
	}

	/**
	 * @return string[]
	 */
	protected function getEmptySeoProperties()
	{
		return array(
			"SECTION_META_TITLE" => "",
			"SECTION_META_KEYWORDS" => "",
			"SECTION_META_DESCRIPTION" => "",
			"SECTION_PAGE_TITLE" => "",
			"ELEMENT_META_TITLE" => "",
			"ELEMENT_META_KEYWORDS" => "",
			"ELEMENT_META_DESCRIPTION" => "",
			"ELEMENT_PAGE_TITLE" => "",
			"SECTION_PICTURE_FILE_ALT" => "",
			"SECTION_PICTURE_FILE_TITLE" => "",
			"SECTION_PICTURE_FILE_NAME" => "",
			"SECTION_DETAIL_PICTURE_FILE_ALT" => "",
			"SECTION_DETAIL_PICTURE_FILE_TITLE" => "",
			"SECTION_DETAIL_PICTURE_FILE_NAME" => "",
			"ELEMENT_PREVIEW_PICTURE_FILE_ALT" => "",
			"ELEMENT_PREVIEW_PICTURE_FILE_TITLE" => "",
			"ELEMENT_PREVIEW_PICTURE_FILE_NAME" => "",
			"ELEMENT_DETAIL_PICTURE_FILE_ALT" => "",
			"ELEMENT_DETAIL_PICTURE_FILE_TITLE" => "",
			"ELEMENT_DETAIL_PICTURE_FILE_NAME" => "",
		);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param array $iblock
	 */
	protected function addDependencies(Record $record, array $iblock)
	{
		$dependency = clone $this->getDependency("IBLOCK_TYPE_ID");
		$dependency->setValue(
			Type::getInstance()->getXmlId(RecordId::createStringId($iblock["IBLOCK_TYPE_ID"]))
		);
		$record->setDependency("IBLOCK_TYPE_ID", $dependency);

		$dependency = clone $this->getDependency('SITE');
		$sites = array();
		$sitesGetList = \CIBlock::GetSite($iblock['ID']);
		while ($site = $sitesGetList->fetch())
		{
			$sites[] = Site::getInstance()->getXmlId(
				Site::getInstance()->createId($site['SITE_ID'])
			);
		}
		$dependency->setValues($sites);
		$record->setDependency('SITE', $dependency);
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsRaw(array("SEO", "FIELDS", "MESSAGES"));
		$seo = $fields["SEO"];
		$fieldsSettings = $fields["FIELDS"];
		$messages = $fields["MESSAGES"];
		unset($fields["SEO"]);
		unset($fields["FIELDS"]);
		unset($fields["MESSAGES"]);
		$fields = $this->restoreDependencies($record, $fields);

		$iblockObject = new \CIBlock();
		$isUpdated = $iblockObject->update($record->getId()->getValue(), $fields);
		if ($isUpdated)
		{
			$this->importMessages($record->getId()->getValue(), $messages);
			$this->importFields($record->getId()->getValue(), $fieldsSettings);
			$this->importSeo($record->getId()->getValue(), $seo);
			$this->checkEqualVersion($record->getXmlId(), $fields);
		}
		else
		{
			throw new \Exception(ExceptionText::getLastError($iblockObject));
		}
	}

	protected function checkEqualVersion($xmlid, &$fields)
	{
		if ($fields["VERSION"])
		{
			$id = $this->findRecord($xmlid);
			$rsElem = IblockTable::getList(array(
				"filter" => array("ID" => $id->getValue()),
				"select" => array("ID", "VERSION"),
			));
			if ($arElem = $rsElem->fetch())
			{
				if ($arElem["VERSION"] != $fields["VERSION"])
				{
					throw new \Exception(Loc::GetMessage("INTERVOLGA_MIGRATO.IBLOCK_VERSION_NOT_EQUAL", array(
						"#ID#" => $id->getValue(),
						"#NAME#" => $fields["NAME"],
						"#VERSION_SITE#" => $arElem["VERSION"],
						"#VERSION_IMPORT#" => $fields["VERSION"],
					)));
				}
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param array $fields
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function restoreDependencies(Record $record, array $fields)
	{
		if ($typeXmlId = $record->getDependency('IBLOCK_TYPE_ID')->getValue())
		{
			$typeId = $record->getDependency('IBLOCK_TYPE_ID')->findId();
			if ($typeId)
			{
				$fields['IBLOCK_TYPE_ID'] = $typeId->getValue();
			}
		}
		if ($typeXmlId = $record->getDependency('SITE')->getValues())
		{
			foreach ($record->getDependency('SITE')->findIds() as $idObject)
			{
				$fields['SITE_ID'][] = $idObject->getValue();
			}
		}

		return $fields;
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw(array("SEO", "FIELDS", "MESSAGES"));
		$seo = $fields["SEO"];
		$fieldsSettings = $fields["FIELDS"];
		$messages = $fields["MESSAGES"];
		unset($fields["SEO"]);
		unset($fields["FIELDS"]);
		unset($fields["MESSAGES"]);
		$fields = $this->restoreDependencies($record, $fields);

		if ($fields["IBLOCK_TYPE_ID"])
		{
			$iblockObject = new \CIBlock();
			$iblockId = $iblockObject->add($fields);
			if ($iblockId)
			{
				$id = $this->createId($iblockId);
				$this->importMessages($iblockId, $messages);
				$this->importFields($iblockId, $fieldsSettings);
				$this->importSeo($iblockId, $seo);
				return $id;
			}
			else
			{
				throw new \Exception(ExceptionText::getLastError($iblockObject));
			}
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_TYPE_NOT_SET'));
		}
	}

	/**
	 * @param int $iblockId
	 * @param string[] $messages
	 */
	protected function importMessages($iblockId, array $messages = array())
	{
		$iblock = new \CIBlock();
		$iblock->setMessages($iblockId, $messages);
	}

	/**
	 * @param int $iblockId
	 * @param array $fields
	 */
	protected function importFields($iblockId, array $fields = array())
	{
		$iblock = new \CIBlock();
		$iblock->setFields($iblockId, $fields);
	}

	/**
	 * @param int $iblockId
	 * @param array $seo
	 */
	protected function importSeo($iblockId, array $seo = array())
	{
		$seoProps = new IblockTemplates($iblockId);
		$seoProps->set($seo);
	}

	protected function deleteInner(RecordId $id)
	{
		$iblockObject = new \CIBlock();
		if (!$iblockObject->delete($id->getValue()))
		{
			throw new \Exception(ExceptionText::getFromApplication());
		}
	}
}