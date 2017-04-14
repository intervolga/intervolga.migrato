<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Bitrix\Iblock\PropertyTable;

class Form extends BaseData
{
	const SEPARATOR = "___";

	const DEFAULT_CATEGORY  = "form";

	protected function __construct()
	{
		Loader::includeModule("iblock");
	}

	public function getFilesSubdir()
	{
		return "/type/iblock/";
	}

	public function getList(array $filter = array())
	{

		$properties = $this->getIblockProperties();

		$result = array();
		$arFilter = array("CATEGORY" => self::DEFAULT_CATEGORY);
		$getList = \CUserOptions::GetList(array(), $arFilter);
		while ($form = $getList->fetch())
		{
			if(intval($form["USER_ID"]) == 0 || intval($form["USER_ID"]) == 1)
			{
				foreach($properties as $id => $xmlId)
				{
					$form["VALUE"] = str_replace($id, $xmlId, $form["VALUE"]);
				}

				$iblockId = substr($form["NAME"], strripos($form["NAME"], "_") + 1);
				$iblockXmlId = \CIBlock::GetByID($iblockId)->Fetch();
				$iblockXmlId = $iblockXmlId["XML_ID"];

				$name = preg_replace("/(\d)+$/", $iblockXmlId, $form["NAME"]);

				$record = new Record($this);
				$id = RecordId::createStringId($form["USER_ID"] . self::SEPARATOR  . $name);
				$record->setXmlId($this->getXmlId($id));
				$record->setId($id);

				$record->addFieldsRaw(array(
					"NAME" => $name,
					"CATEGORY" => $form["CATEGORY"],
					"COMMON" => $form["COMMON"],
					"VALUE" => $form["VALUE"],
				));
				$result[] = $record;
			}
		}

		return $result;
	}

	/**
	 * @return array
	*/
	private function getIblockProperties($idToXmlId = true)
	{
		$properties = array();

		$rsProperties = PropertyTable::getList(array("select" => array("ID", "XML_ID")));
		while($prop = $rsProperties->fetch())
		{
			if($idToXmlId)
				$properties["PROPERTY_" . $prop["ID"]] = "PROPERTY_" . $prop["XML_ID"];
			else
				$properties["PROPERTY_" . $prop["XML_ID"]] = "PROPERTY_" . $prop["ID"];
		}

		return $properties;
	}

	private function updateValueField($value, $properties)
	{
		foreach($properties as $from => $to)
		{
			$value = str_replace($from, $to, $value);
		}
		return $value;
	}

	private function getIblockId($iblockXmlId)
	{
		if($iblockId = Iblock::getInstance()->findRecord($iblockXmlId))
		{
			return $iblockId->getValue();
		}
		else
			throw new \Exception("Error: not found iblock with xml: " . $iblockXmlId);
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsRaw();

		$fields["VALUE"] = $this->updateValueField($fields["VALUE"], $this->getIblockProperties(false));

		$arUserName = explode(self::SEPARATOR, $record->getXmlId());
		$iblockXmlId = str_replace("form_element_", "", $arUserName[1]);

		$iblockId = $this->getIblockId($iblockXmlId);

		$options = new \CUserOptions();
		$isUpdated = $options->SetOption(
			$fields["CATEGORY"],
			str_replace($iblockXmlId, $iblockId, $fields["NAME"]),
			unserialize($fields["VALUE"]),
			$fields["COMMON"] == "Y",
			$arUserName[0]
		);

		if (!$isUpdated)
		{
			global $APPLICATION;
			throw new \Exception($APPLICATION->GetException()->GetString());
		}
	}

	public function create(Record $record)
	{
		$fields = $record->getFieldsRaw();

		$fields["VALUE"] = $this->updateValueField($fields["VALUE"], $this->getIblockProperties(false));

		$arUserName = explode(self::SEPARATOR, $record->getXmlId());
		$iblockXmlId = str_replace("form_element_", "", $arUserName[1]);

		$iblockId = $this->getIblockId($iblockXmlId);

		$options = new \CUserOptions();
		$isAdded = $options->SetOption(
			$fields["CATEGORY"],
			str_replace($iblockXmlId, $iblockId, $fields["NAME"]),
			unserialize($fields["VALUE"]),
			$fields["COMMON"] == "Y",
			$arUserName[0]
		);

		if ($isAdded)
		{
			return RecordId::createStringId($record->getXmlId());
		}
		else
		{
			global $APPLICATION;
			throw new \Exception($APPLICATION->GetException()->GetString());
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$typeObject = new \CIBlockType();
		if($id)
		{
			if (!$typeObject->delete($id->getValue()))
			{
				throw new \Exception("Unknown error");
			}
		}
		else
			throw new \Exception("Элемент с id " . $xmlId . " не существует");
	}

	public function getXmlId($id)
	{
		return $id->getValue();
	}
}