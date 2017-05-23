<? namespace Intervolga\Migrato\Data;

abstract class BaseUserFieldEnum extends BaseData
{
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
			$record->addFieldsRaw(array(
				"VALUE" => $enum["VALUE"],
				"DEF" => $enum["DEF"],
				"SORT" => $enum["SORT"],
			));

			$dependency = clone $this->getDependency("USER_FIELD_ID");
			$dependency->setValue(
				$dependency->getTargetData()->getXmlId(RecordId::createNumericId($enum["USER_FIELD_ID"]))
			);
			$record->setDependency("USER_FIELD_ID", $dependency);

			$result[] = $record;
		}

		return $result;
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsRaw();
		if($fieldId = $record->getDependency("USER_FIELD_ID")->getId())
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

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		if($fieldId = $record->getDependency("USER_FIELD_ID")->getId())
		{
			$fields["XML_ID"] = $record->getXmlId();
			$fields["USER_FIELD_ID"] = $fieldId->getValue();
			$enumObject = new \CUserFieldEnum();

			$isUpdated = $enumObject->SetEnumValues($fieldId->getValue(), array("n" => $fields));
			if ($isUpdated)
			{
			    return $this->createId($this->findRecord($record->getXmlId())->getValue());
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

	protected function deleteInner($xmlId)
	{
		$id = $this->findRecord($xmlId);
		$fieldenumObject = new \CUserFieldEnum();
		$fieldenumObject->DeleteFieldEnum($id);
	}

	public function setXmlId($id, $xmlId)
	{
		$enumGetList = \CUserFieldEnum::getList(array(), array("ID" => $id));
		if($enum = $enumGetList->fetch())
		{
			$enum["XML_ID"] = $xmlId;
			$userFieldObject = new \CUserFieldEnum();
			$userFieldObject->setEnumValues($enum["USER_FIELD_ID"], $enum);
		}
	}

	public function getXmlId($id)
	{
		$xmlId = "";
		if($id = $id->getValue())
		{
			$enumGetList = \CUserFieldEnum::getList(array(), array("ID" => $id));
			if($enum = $enumGetList->fetch())
			{
				$xmlId = $enum["XML_ID"];
			}
		}
		return $xmlId;
	}
}