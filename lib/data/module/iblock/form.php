<? namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Bitrix\Iblock\PropertyTable;

class Form extends BaseData
{
	const SEPARATOR = '___';

	const CATEGORY = 'form';
	const TYPE_ELEMENT = 'E';
	const TYPE_SECTION = 'S';

	const USER_ADMIN = 1;

	protected function __construct()
	{
		Loader::includeModule('iblock');
	}

	public function getFilesSubdir()
	{
		return '/type/iblock/';
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$arFilter = array('CATEGORY' => self::CATEGORY);
		$getList = \CUserOptions::getList(array(), $arFilter);
		while ($form = $getList->fetch())
		{
			if ($record = $this->arrayToRecord($form))
			{
				$result[] = $record;
			}
		}

		return $result;
	}

	/**
	 * @param array $form
	 *
	 * @return \Intervolga\Migrato\Data\Record|null
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	protected function arrayToRecord(array $form)
	{
		if ($formXmlId = $this->getFormXmlId($form))
		{
			$record = new Record($this);
			$id = RecordId::createStringId($form['ID']);
			$record->setXmlId($formXmlId);
			$record->setId($id);
			$record->addFieldsRaw(array(
				'IS_ADMIN' => ($form['USER_ID'] == static::USER_ADMIN ? 'Y' : 'N'),
				'TYPE' => $this->getType($form['NAME']),
				'COMMON' => $form['COMMON'],
			));
			$this->addIblockDependency($record, $form['NAME']);
			$this->addPropsDependencies($record, $form['VALUE']);

			return $record;
		}

		return null;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $name
	 *
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	protected function addIblockDependency(Record $record, $name)
	{
		$iblockId = substr($name, strripos($name, '_') + 1);
		if ($iblockId)
		{
			$dependency = clone $this->getDependency('IBLOCK_ID');
			$dependency->setValue(
				Iblock::getInstance()->getXmlId(Iblock::getInstance()->createId($iblockId))
			);
			$record->setDependency('IBLOCK_ID', $dependency);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $value
	 */
	protected function addPropsDependencies(Record $record, $value)
	{
		$propertyXmlIds = array();
		foreach ($this->getIblockProperties() as $id => $xmlId)
		{
			$find = '--PROPERTY_' . $id . '--';
			$replace = '--PROPERTY_' . $xmlId . '--';
			if (is_int(strpos($value, $find)))
			{
				$value = str_replace($find, $replace, $value);
				$propertyXmlIds[] = $xmlId;
			}
		}
		if ($propertyXmlIds)
		{
			$record->setFieldRaw('VALUE', $value);

			$dependency = clone $this->getDependency('PROPERTY_ID');
			$dependency->setValues($propertyXmlIds);
			$record->setDependency('PROPERTY_ID', $dependency);
		}
	}

	/**
	 * @param bool $idToXmlId
	 *
	 * @return array
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function getIblockProperties($idToXmlId = true)
	{
		static $properties = array();

		if (!$properties[$idToXmlId])
		{
			$propertiesGetList = PropertyTable::getList(
				array(
					'select' => array(
						'ID',
						'XML_ID',
					)
				)
			);
			while ($property = $propertiesGetList->fetch())
			{
				if ($idToXmlId)
				{
					$properties[$idToXmlId][$property['ID']] = $property['XML_ID'];
				}
				else
				{
					$properties[$idToXmlId][$property['XML_ID']] = $property['ID'];
				}
			}
		}

		return $properties[$idToXmlId];
	}

	/**
	 * @param array $form
	 *
	 * @return string
	 */
	protected function getFormXmlId(array $form)
	{
		$xmlIdParts = array();
		if (!$form['USER_ID'] || $form['USER_ID'] == static::USER_ADMIN)
		{
			if ($form['CATEGORY'] == 'form')
			{
				if ($type = $this->getType($form['NAME']))
				{
					if ($type == static::TYPE_ELEMENT)
					{
						$xmlIdParts[] = 'el';
					}
					elseif ($type == static::TYPE_SECTION)
					{
						$xmlIdParts[] = 'sec';
					}

					if ($form['COMMON'] == 'Y')
					{
						$xmlIdParts[] = 'all';
					}
					else
					{
						$xmlIdParts[] = 'admin';
					}

					$iblockId = substr($form['NAME'], strripos($form['NAME'], '_') + 1);
					$iblockXmlId = \CIBlock::getByID($iblockId)->fetch();
					$xmlIdParts[] = $iblockXmlId['XML_ID'];
				}
			}
		}

		return strtolower(implode('-', $xmlIdParts));
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	protected function getType($name)
	{
		if (substr_count($name, 'form_element'))
		{
			return static::TYPE_ELEMENT;
		}
		elseif (substr_count($name, 'form_section'))
		{
			return static::TYPE_SECTION;
		}

		return '';
	}

	public function getDependencies()
	{
		return array(
			'IBLOCK_ID' => new Link(Iblock::getInstance()),
			'PROPERTY_ID' => new Link(Property::getInstance()),
		);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function recordToArray(Record $record)
	{
		$result = array(
			'CATEGORY' => 'form',
		);
		$fields = $record->getFieldsRaw();
		$nameParts = array(
			'prefix' => 'form',
			'type' => '',
			'iblock' => 0,
		);
		if ($fields['TYPE'] == static::TYPE_ELEMENT)
		{
			$nameParts['type'] = 'element';
		}
		elseif ($fields['TYPE'] == static::TYPE_SECTION)
		{
			$nameParts['type'] = 'section';
		}
		$iblockDependency = $record->getDependency('IBLOCK_ID');
		if ($iblockDependency)
		{
			if ($iblockId = $iblockDependency->findId())
			{
				$nameParts['iblock'] = $iblockId->getValue();
			}
		}
		if ($nameParts['type'] && $nameParts['iblock'])
		{
			$result['NAME'] = implode('_', $nameParts);
			if ($fields['VALUE'])
			{
				$result['VALUE'] = $this->updateValueField($fields['VALUE'], $this->getIblockProperties(false));
			}

			if ($fields['COMMON'])
			{
				$result['COMMON'] = $fields['COMMON'];
			}
			if ($fields['IS_ADMIN'] == 'Y')
			{
				$result['USER_ID'] = static::USER_ADMIN;
			}
			elseif ($fields['IS_ADMIN'] == 'N')
			{
				$result['USER_ID'] = 0;
			}
		}
		else
		{
			throw new \Exception('Cannot restore form option id');
		}

		return $result;
	}

	protected function updateValueField($value, $properties)
	{
		foreach ($properties as $from => $to)
		{
			$value = str_replace($from, $to, $value);
		}

		return $value;
	}

	public function getXmlId($id)
	{
		$arFilter = array('ID' => $id);
		$getList = \CUserOptions::getList(array(), $arFilter);
		if ($form = $getList->fetch())
		{
			return $this->getFormXmlId($form);
		}

		return '';
	}
}