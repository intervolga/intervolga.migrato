<? namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Localization\Loc,
	Intervolga\Migrato\Data\BaseData,
	Intervolga\Migrato\Data\Link,
	Intervolga\Migrato\Data\Record,
	Intervolga\Migrato\Data\Module\Iblock\Iblock as MigratoIblock,
	Bitrix\Main\Loader;


Loc::loadMessages(__FILE__);

class ListOptions extends BaseData
{
	const CATEGORY = 'list';
	const NAME_PREFIX = 'tbl_iblock_element_';
	const XML_ID_SEPARATOR = '.';
	const COLUMNS_DELIMITER = ',';

	public function __construct()
	{
		Loader::includeModule("iblock");
	}

	public function getFilesSubdir()
	{
		return '/';
	}

	public function getDependencies()
	{
		return array(
			'IBLOCK_ID' => new Link(MigratoIblock::getInstance()),
			'PROPERTY_ID' => new Link(Property::getInstance())
		);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$filter['CATEGORY'] = self::CATEGORY;
		$filterParams = array(
			0 => array('USER_ID' => 1),
			1 => array('COMMON' => 'Y'),
		);
		$recordsId = array();
		foreach ($filterParams as $filterParam)
		{
			$newFilter = array_merge($filter,$filterParam);
			$dbRes = \CUserOptions::getList(array(), $filter);
			while ($uoption = $dbRes->fetch())
			{
				if (strpos($uoption['NAME'], static::NAME_PREFIX) == 0 && !in_array($uoption['ID'], $recordsId))
				{
					if ($value = unserialize($uoption['VALUE']))
					{
						$recordsId[] = $uoption['ID'];
						$record = new Record($this);
						$record->setId($this->createId($uoption['ID']));
						$record->setXmlId($this->getXmlIdByObject($uoption));
						$record->setFieldRaw('COMMON', $uoption['COMMON']);
						$record->setFieldRaw('CATEGORY', $uoption['CATEGORY']);
						$this->setDependencies($record, $uoption);

						$this->addPropsDependencies($record, $value);
						$result[] = $record;
					}
				}
			}
		}
		return $result;
	}

	protected function addPropsDependencies(Record $record, $value)
	{
		//Get properties Id
		$propertyIds = array();
		$properties = array();
		if ($value['columns'])
			$columns = $properties = explode(static::COLUMNS_DELIMITER, $value['columns']);
		if($value['by'] && strpos($value['by'], 'PROPERTY_') == 0 && !in_array($value['by'],$properties))
			$properties[] = $value['by'];
		if ($properties)
			foreach ($properties as $property)
				if (strpos($property, 'PROPERTY_') == 0)
				{
					$propertyId = substr($property, 9); // strlen('PROPERTY_') == 9
					if ($propertyId)
						$propertyIds[$property] = $propertyId;
				}
		//Set dependencies
		if($propertyIds)
		{
			$propertyXmlIds = $this->getIblockPropertiesXmlId($propertyIds);
			if ($propertyXmlIds)
			{
				$dependency = clone $this->getDependency('PROPERTY_ID');
				$dependency->setValues($propertyXmlIds);
				$record->setDependency('PROPERTY_ID', $dependency);
			}
		}
		//Set VALUE field
		$newValue = $this->convertValueFieldToXml($value,$propertyIds,$propertyXmlIds);
		$record->setFieldRaw('VALUE',serialize($newValue));
	}

	/**
	 * @param $value -
	 * @param $propertyIds
	 * @param $propertyXmlIds
	 * @return mixed
	 */
	private function convertValueFieldToXml($value, $propertyIds, $propertyXmlIds)
	{
		$columns = explode(static::COLUMNS_DELIMITER, $value['columns']);
		//Convert field 'COULMNS'
		$newColumns = array();
		if ($columns && $propertyXmlIds)
		{
			foreach ($columns as $column)
			{
				if (strpos($column, 'PROPERTY_') == 0)
				{
					if ($propertyIds[$column])
					{
						$id = $propertyIds[$column];
						if ($propertyXmlIds[$id])
							$newColumns[] = 'PROPERTY_' . $propertyXmlIds[$id];
					}
				}
				else
					$newColumns[] = $column;
			}
		}
		//Convert field 'BY'
		if($value['by'] && strpos($value['by'], 'PROPERTY_') == 0 )
		{
			if($propertyIds[$value['by']])
			{
				$id = $propertyIds[$value['by']];
				if($propertyXmlIds[$id])
				{
					$newfieldBy = 'PROPERTY_'.$propertyXmlIds[$id];
				}
			}
		}
		$newValueField = $value;
		if($newfieldBy)
			$newValueField['by'] = $newfieldBy;
		$newValueField['columns'] = implode(static::COLUMNS_DELIMITER,$newColumns);
		return $newValueField;
	}

	protected function getIblockPropertiesXmlId($propsId)
	{
		$properties = array();
		foreach ($propsId as $id)
		{
			$idObject = Property::getInstance()->createId($id);
			$xmlId = Property::getInstance()->getXmlId($idObject);
			if ($xmlId)
				$properties[$id] = $xmlId;
		}
		return $properties;
	}

	private function convertValueFieldFromXml($value)
	{
		if($value['columns'])
		{
			$columns = explode(static::COLUMNS_DELIMITER, $value['columns']);
			$newColumns = array();
			foreach ($columns as $column)
			{
				if(strpos($column,'PROPERTY_') === 0)
				{
					$propXmlId = substr($column, 9);
					if($propXmlId)
					{
						$propId = Property::getInstance()->findRecord($propXmlId);
						if ($propId)
						{
							$newColumns[] = 'PROPERTY_'.$propId->getValue();
						}
					}
				}
				else
					$newColumns[] = $column;
			}
			$value['columns'] = implode(static::COLUMNS_DELIMITER, $newColumns);
		}
		if($value['by'])
		{
			if(strpos($value['by'], 'PROPERTY_') === 0)
			{
				$propXmlId = substr($value['by'], 9);
				if($propXmlId)
				{
					$propId = Property::getInstance()->findRecord($propXmlId);
					if ($propId)
						$value['by'] = 'PROPERTY_'.$propId->getValue();
				}
			}
		}
		return $value;
	}

	protected function getXmlIdByObject(array $uoption)
	{
		$iblockId = $this->getIblockXmlIdByName($uoption['NAME']);
		if($iblockId)
		{
			$iblockXmlId = MigratoIblock::getInstance()->getXmlId(MigratoIblock::getInstance()->createId($iblockId));
			if ($iblockXmlId)
			{
				return (
					($uoption["USER_ID"] == 1 ? 'Y' : 'N') . static::XML_ID_SEPARATOR .
					$uoption["COMMON"] . static::XML_ID_SEPARATOR .
					$iblockXmlId);
			}
		}
		return '';
	}

	protected function xmlIdToArray($xmlId)
	{
		$fields = explode(static::XML_ID_SEPARATOR, $xmlId);
		if(count($fields) == 3)
			return array (
				'IS_ADMIN' => $fields[0],
				'COMMON' => $fields[1],
				'IBLOCK_XML_ID' => $fields[2]
			);
		return array();
	}

	private function getIblockXmlIdByName($name)
	{
		if(Loader::includeModule('iblock'))
		{
			$hash = substr($name, strlen(static::NAME_PREFIX));
			$res = \CIBlock::GetList();
			while ($iblock = $res->Fetch())
			{
				if (md5($iblock['IBLOCK_TYPE_ID'] . '.' . $iblock['ID']) == $hash)
					return $iblock['ID'];
			}
		}
		return '';
	}

	public function setDependencies(Record $record, array $uoption)
	{
		if($uoption['NAME'])
		{
			$iblockId = $this->getIblockXmlIdByName($uoption['NAME']);
			if ($iblockId)
			{
				$dependency = clone $this->getDependency('IBLOCK_ID');
				$dependency->setValue(MigratoIblock::getInstance()->getXmlId(MigratoIblock::getInstance()->createId($iblockId)));
				$record->setDependency('IBLOCK_ID', $dependency);
			}
		}
	}

	public function getXmlId($id)
	{
		$dbRes = \CUserOptions::GetList(
			array(),
			array(
				'ID' => $id
			)
		);
		if ($uoption = $dbRes->Fetch())
		{
			return $this->getXmlIdByObject($uoption);
		}
		return "";
	}

	protected function createInner(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$xmlId = $record->getXmlId();
		$xmlFields = $this->xmlIdToArray($xmlId);

		if($xmlFields['IS_ADMIN'] == 'Y')
			$fields['USER_ID'] = 1;
		else
			$fields['USER_ID'] = false;

		//создаем NAME записи
		$iblockXmlId = $xmlFields['IBLOCK_XML_ID'];
		$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
		if(Loader::includeModule('iblock'))
		{
			$dbres = \CIBlock::GetById($iblockId);
			if($iblockInfo = $dbres->GetNext())
			{
				$fields['NAME'] = static::NAME_PREFIX . md5( $iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId );
				if($value = unserialize($fields['VALUE']))
					$fields['VALUE'] = $this->convertValueFieldFromXml($value);
				If(\CUserOptions::SetOption($fields['CATEGORY'],$fields['NAME'],$fields['VALUE'],$fields['COMMON'] === 'Y',$fields['USER_ID']))
				{
					$filter=array(
						'NAME' => $fields['NAME'],
						'CATEGORY' => $fields['CATEGORY'],
						'COMMON' => $fields['COMMON'],
						'VALUE'=>$fields['VALUE']
					);
					$dbres = \CUserOptions::GetList(array(),$filter);
					if($newOption = $dbres->fetch())
						return $this->createId($newOption['ID']);
				}
			}
		}
		throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_LIST_OPTIONS_ADD_ERROR'));
	}

	protected function deleteInner($xmlId)
	{
		$RecordId = $this->findRecord($xmlId);
		if($RecordId)
		{
			$id = $RecordId->getValue();
			$dbres = \CUserOptions::GetList(array(),array('ID'=> $id));
			if($uoption = $dbres->fetch())
			{
				$res = \CUserOptions::DeleteOptionsByName($uoption['CATEGORY'],$uoption['NAME']);
				if (!$res)
				{
					throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_LIST_OPTIONS_DELETE_ERROR'));
				}
			}
		}
	}

	public function update(Record $record)
	{
		$fields = $this->recordToArray($record);

		$options = new \CUserOptions();
		$isUpdated = false;

		if ($fields['CATEGORY'] && $fields['NAME'])
		{
			$isUpdated = $options->setOption(
				$fields['CATEGORY'],
				$fields['NAME'],
				$fields['VALUE'],
				$fields['COMMON'] == 'Y',
				$fields['USER_ID']
			);
		}

		if (!$isUpdated)
		{
			throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_LIST_OPTIONS_NOT_UPDATED');
		}
	}

	protected function recordToArray(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$xmlId = $record->getXmlId();
		$xmlFields = $this->xmlIdToArray($xmlId);

		//создаем NAME записи
		$iblockXmlId = $xmlFields['IBLOCK_XML_ID'];
		$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
		if(Loader::includeModule('iblock'))
		{
			$dbres = \CIBlock::GetById($iblockId);
			if($iblockInfo = $dbres->GetNext())
			{
				$fields['NAME'] = static::NAME_PREFIX . md5( $iblockInfo['IBLOCK_TYPE_ID'] . '.' . $iblockId );
			}
		}
		return $fields;
	}

	public function findRecord($xmlId)
	{
		$id = null;
		$fields = $this->xmlIdToArray($xmlId);

		$arFilter = array('COMMON' => $fields['COMMON']);
		if($fields['IS_ADMIN'] === 'Y')
			$arFilter['USER_ID'] = 1;
		$iblockXmlId = $fields['IBLOCK_XML_ID'];

		$iblockId = MigratoIblock::getInstance()->findRecord($iblockXmlId)->getValue();
		if(Loader::includeModule('iblock') && $iblockId)
		{
			$dbres = \CIBlock::GetById($iblockId);
			if($iblockInfo = $dbres->GetNext())
			{
				$dbres = \CAdminFilter::getList([],$arFilter);
				while ($uoption = $dbres->Fetch())
				{
					if (strpos($uoption['NAME'], static::NAME_PREFIX) === 0)
					{
						$hash = substr($uoption['NAME'], strlen(static::NAME_PREFIX));
						if(md5($iblockInfo['IBLOCK_TYPE_ID'].'.'.$iblockId) === $hash)
							return $this->createId($uoption['ID']);
					}
				}
			}
		}
		return null;
	}

	public function createId($id)
	{
		return \Intervolga\Migrato\Data\RecordId::createNumericId($id);
	}

	public function setXmlId($id, $xmlId)
	{
		// XML ID is autogenerated, cannot be modified
	}
}