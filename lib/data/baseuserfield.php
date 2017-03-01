<?namespace Intervolga\Migrato\Data;

use Intervolga\Migrato\Data\Module\Highloadblock\Field;
use Intervolga\Migrato\Data\Module\Highloadblock\HighloadBlock;
use Intervolga\Migrato\Data\Module\Iblock\Iblock;
use Intervolga\Migrato\Tool\XmlIdProvider\UfSelfXmlIdProvider;

abstract class BaseUserField extends BaseData
{
	public function __construct()
	{
		$this->xmlIdProvider = new UfSelfXmlIdProvider($this);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CUserTypeEntity::getList();
		while ($userField = $getList->fetch())
		{
			if ($this->isCurrentUserField($userField["ENTITY_ID"]))
			{
				$result[] = $this->userFieldToRecord($userField);
			}
		}
		return $result;
	}

	/**
	 * @param string $userFieldEntityId
	 * @return bool
	 */
	abstract public function isCurrentUserField($userFieldEntityId);

	/**
	 * @param array $userField
	 * @return Record
	 */
	protected function userFieldToRecord(array $userField)
	{
		$record = new Record($this);
		$id = RecordId::createNumericId($userField["ID"]);
		$record->setId($id);
		$record->setXmlId($userField["XML_ID"]);
		$fields = array(
			"FIELD_NAME" => $userField["FIELD_NAME"],
			"USER_TYPE_ID" => $userField["USER_TYPE_ID"],
			"SORT" => $userField["SORT"],
			"MULTIPLE" => $userField["MULTIPLE"],
			"MANDATORY" => $userField["MANDATORY"],
			"SHOW_FILTER" => $userField["SHOW_FILTER"],
			"SHOW_IN_LIST" => $userField["SHOW_IN_LIST"],
			"EDIT_IN_LIST" => $userField["EDIT_IN_LIST"],
			"IS_SEARCHABLE" => $userField["IS_SEARCHABLE"],
		);
		$fields = array_merge($fields, $this->getSettingsFields($userField["SETTINGS"]));
		$record->setFields($fields);
		foreach ($this->getSettingsLinks($userField["SETTINGS"]) as $name => $link)
		{
			$record->addReference($name, $link);
		}

		return $record;
	}

	/**
	 * @param array $settings
	 *
	 * @return array
	 */
	protected function getSettingsFields(array $settings)
	{
		$fields = array();
		foreach ($settings as $name => $setting)
		{
			if (!in_array($name, array_keys($this->getSettingsReferences())))
			{
				$fields["SETTINGS." . $name] = $setting;
			}
		}

		return $fields;
	}

	/**
	 * @param array $settings
	 *
	 * @return Link[]
	 */
	protected function getSettingsLinks(array $settings)
	{
		$links = array();
		foreach ($settings as $name => $setting)
		{
			if ($name == "IBLOCK_ID")
			{
				$iblockIdObject = RecordId::createNumericId($setting);
				$xmlId = Iblock::getInstance()->getXmlIdProvider()->getXmlId($iblockIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
			if ($name == "HLBLOCK_ID")
			{
				$hlBlockIdObject = RecordId::createNumericId($setting);
				$xmlId = HighloadBlock::getInstance()->getXmlIdProvider()->getXmlId($hlBlockIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
			if ($name == "HLFIELD_ID")
			{
				$userFieldIdObject = RecordId::createNumericId($setting);
				$xmlId = Field::getInstance()->getXmlIdProvider()->getXmlId($userFieldIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
		}

		return $links;
	}

	public function getReferences()
	{
		$references = array();
		foreach ($this->getSettingsReferences() as $name => $link)
		{
			$references["SETTINGS." . $name] = $link;
		}

		return $references;
	}

	/**
	 * @return Link[]
	 */
	public function getSettingsReferences()
	{
		return array(
			"IBLOCK_ID" => new Link(Iblock::getInstance()),
			"HLBLOCK_ID" => new Link(HighloadBlock::getInstance()),
			"HLFIELD_ID" => new Link(Field::getInstance()),
		);
	}
}