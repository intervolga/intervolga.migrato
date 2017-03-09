<? namespace Intervolga\Migrato\Data;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Tool\XmlIdProvider\UfEnumXmlIdProvider;

abstract class BaseUserFieldEnum extends BaseData
{
	public function __construct()
	{
        $this->xmlIdProvider = new UfEnumXmlIdProvider($this);
    }

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$result = array();
		$enumFieldObject = new \CUserFieldEnum();
		$rsEnum = $enumFieldObject->GetList(array(), $filter);
		while($enum = $rsEnum->Fetch())
		{
			$record = new Record($this);
			$record->setXmlId($enum["XML_ID"]);
			$record->setId(RecordId::createNumericId($enum["ID"]));
			$record->setFields(array(
				"VALUE" => $enum["VALUE"],
				"DEF" => $enum["DEF"],
				"SORT" => $enum["SORT"],
			));

			$dependency = clone $this->getDependency("USER_FIELD_ID");
			$dependency->setValue(
				$dependency->getTargetData()->getXmlIdProvider()->getXmlId(RecordId::createNumericId($enum["USER_FIELD_ID"]))
			);
			$record->addDependency("USER_FIELD_ID", $dependency);

			$result[] = $record;
		}

		return $result;
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsStrings();
		$dependency = $record->getDependency("USER_FIELD_ID");
		if($fieldId = $dependency->getTargetData()->findRecord($dependency->getValue()))
		{
			$fields["XML_ID"] = $record->getXmlId();
			$enumObject = new \CUserFieldEnum();

			$isUpdated = $enumObject->SetEnumValues($fieldId->getValue(), array($record->getId()->getValue() => $fields));
			if (!$isUpdated)
			{
				throw new \Exception("Unknown error");
			}
		}
	}

	public function create(Record $record)
	{
		$fields = $record->getFieldsStrings();
		$dependency = $record->getDependency("USER_FIELD_ID");
		if($fieldId = $dependency->getTargetData()->findRecord($dependency->getValue()))
		{
			$fields["XML_ID"] = $record->getXmlId();
			$fields["USER_FIELD_ID"] = $fieldId->getValue();
			$enumObject = new \CUserFieldEnum();

			$isUpdated = $enumObject->SetEnumValues($fieldId->getValue(), array("n" => $fields));
			if ($isUpdated)
			{
				$id = RecordId::createNumericId($this->findRecord($record->getXmlId())->getValue());
				$this->getXmlIdProvider()->setXmlId($id, $record->getXmlId());

				return $id;
			}
			else
			{
				throw new \Exception("Unknown error");
			}
		}
		else
		{
			throw new \Exception("iblock/fieldenum не указана зависимость uf поля");
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$fieldenumObject = new \CUserFieldEnum();
		$fieldenumObject->DeleteFieldEnum($id->getValue());
	}
}