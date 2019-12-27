<?php namespace Intervolga\Migrato\Data;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Data\Module\Iblock\Field;

Loc::loadMessages(__FILE__);

abstract class BaseUserFieldEnum extends BaseData
{
    const XML_ID_SEPARATOR = '.';

    /**
     * @var Link user field
     */
    private $dependency;

	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.USER_FIELD_ENUM'));
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
		while ($enum = $rsEnum->Fetch())
		{
			$record = new Record($this);
            $id = $this->createId($enum['ID']);

            $this->dependency = clone $this->getDependency('USER_FIELD_ID');
            $this->dependency->setValue(
                $this->dependency->getTargetData()->getXmlId(RecordId::createNumericId($enum['USER_FIELD_ID']))
            );
            $record->setDependency('USER_FIELD_ID', $this->dependency);

			$record->setXmlId($this->getXmlId($id));
			$record->setId($id);
			$record->addFieldsRaw(array(
				'VALUE' => $enum['VALUE'],
				'DEF' => $enum['DEF'],
				'SORT' => $enum['SORT'],
			));

			$result[] = $record;
		}

		return $result;
	}

	public function update(Record $record)
	{
		$fields = $record->getFieldsRaw();
        $fieldId = $record->getDependency('USER_FIELD_ID')->getId();
		if ($fieldId)
		{
			$fields['XML_ID'] = $this->getSiteXmlId($record->getXmlId());

			$enumObject = new \CUserFieldEnum();
			$isUpdated = $enumObject->setEnumValues(
			    $fieldId->getValue(),
                array($record->getId()->getValue() => $fields)
            );
			if (!$isUpdated)
			{
				throw new \Exception(ExceptionText::getFromApplication());
			}
		}
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
        $fieldId = $record->getDependency('USER_FIELD_ID')->getId();
        if (!$fieldId)
        {
            throw new \Exception(Loc::getMessage(
                'INTERVOLGA_MIGRATO.CREATE_NOT_USER_FIELD',
                array('#XML_ID#' => $record->getXmlId())
            ));
        }

        $fields['XML_ID'] = $this->getSiteXmlId($record->getXmlId());
        $fields['USER_FIELD_ID'] = $fieldId->getValue();
        $enumObject = new \CUserFieldEnum();
        $isUpdated = $enumObject->setEnumValues($fieldId->getValue(), array('n' => $fields));
        if (!$isUpdated)
        {
            throw new \Exception(ExceptionText::getFromApplication());
        }

        $recordId = $this->findRecordForField($fieldId->getValue(), $fields['XML_ID']);
        if (!$recordId)
        {
            throw new \Exception(ExceptionText::getUnknown());
        }

        return $this->createId($recordId->getValue());
	}

	protected function deleteInner(RecordId $id)
	{
		$fieldEnumObject = new \CUserFieldEnum();
		$fieldEnumObject->deleteFieldEnum($id->getValue());
	}

    public function findRecord($xmlId)
    {
        $id = null;
        $fields = explode(static::XML_ID_SEPARATOR, $xmlId);
        if (count($fields) === 2 && $fields[0] && $fields[1])
        {
            $fieldEnumObject = new \CUserFieldEnum();
            $enum = $fieldEnumObject->GetList(
                array(),
                array(
                    'USER_FIELD_ID' => Field::getPublicId($fields[0]),
                    'XML_ID' => $fields[1]
                )
            )->Fetch();
            if ($enum)
            {
                $id = $this->createId($enum['ID']);
            }
        }

        return $id;
    }

    public function setXmlId($id, $xmlId)
	{
	    $fields = explode(static::XML_ID_SEPARATOR, $xmlId);

		$obEnum = new \CUserFieldEnum();
        $arEnum = $obEnum->getList(array(), array('ID' => $id->getValue()))->Fetch();
		if ($arEnum)
		{
			$userFieldObject = new \CUserFieldEnum();
			$userFieldObject->setEnumValues(
				$arEnum['USER_FIELD_ID'],
				array(
					$arEnum['ID'] => array(
						'XML_ID' => $fields[1],
						'VALUE' => $arEnum['VALUE'],
					),
				)
			);
		}
	}

	public function getXmlId($id)
	{
        $xmlId = '';
        $id = $id->getValue();
		if ($id && $this->dependency)
		{
			$obEnum = new \CUserFieldEnum();
            $arEnum = $obEnum->getList(array(), array('ID' => $id))->Fetch();
			if ($arEnum)
			{
				$xmlId = $this->dependency->getValue() . static::XML_ID_SEPARATOR . $arEnum['XML_ID'];
			}
		}

		return $xmlId;
	}

	protected function getSiteXmlId($migratoXmlId)
    {
        $fields = explode(static::XML_ID_SEPARATOR, $migratoXmlId);
        return $fields[1] ?: $migratoXmlId;
    }

	/**
	 * @param int $fieldId
	 * @param string $xmlId
	 * @return \Intervolga\Migrato\Data\RecordId|null
	 */
	public function findRecordForField($fieldId, $xmlId)
	{
		$enum = new \CUserFieldEnum();
		$result = $enum->getList(
			array(),
			array(
				'USER_FIELD_ID' => $fieldId,
				'XML_ID' => $xmlId,
			)
		)->fetch();

		if ($result['ID'])
		{
			return $this->createId($result['ID']);
		}

        return null;
    }

    public function validateXmlIdCustom($xmlId)
    {
        $fields = explode(static::XML_ID_SEPARATOR, $xmlId);
        $isValid = (count($fields) === 2 && $fields[0] && $fields[1]);
        if (!$isValid)
        {
            throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.INVALID_XML_ID'));
        }
    }

    public function getValidationXmlId($xmlId)
    {
        $fields = explode(static::XML_ID_SEPARATOR, $xmlId);
        return $fields[1];
    }
}