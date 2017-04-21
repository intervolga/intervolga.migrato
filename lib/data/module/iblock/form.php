<? namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Bitrix\Iblock\PropertyTable;

Loc::loadMessages(__FILE__);

class Form extends BaseData
{
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

	public function getDependencies()
	{
		return array(
			'IBLOCK_ID' => new Link(Iblock::getInstance()),
			'PROPERTY_ID' => new Link(Property::getInstance()),
		);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$filter = array('CATEGORY' => self::CATEGORY);
		$getList = \CUserOptions::getList(array(), $filter);
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
			$record->setXmlId($formXmlId);
			$record->setId($this->createId($form['ID']));
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

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function recordToArray(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$result = array(
			'CATEGORY' => static::CATEGORY,
		);
		if (array_key_exists('COMMON', $fields))
		{
			$result['COMMON'] = $fields['COMMON'];
		}
		if (array_key_exists('IS_ADMIN', $fields))
		{
			$result['USER_ID'] = ($fields['IS_ADMIN'] == 'Y' ? static::USER_ADMIN : 0);
		}
		if ($name = $this->restoreName($fields, $record->getDependency('IBLOCK_ID')))
		{
			$result['NAME'] = $name;
		}
		if ($fields['VALUE'])
		{
			$result['VALUE'] = $this->updateValueField($fields['VALUE'], $this->getIblockProperties(false));
		}

		return $result;
	}

	/**
	 * @param array $form
	 * @param \Intervolga\Migrato\Data\Link|null $iblockLink
	 *
	 * @return string
	 */
	protected function restoreName($form, Link $iblockLink = null)
	{
		$name = '';
		if ($iblockLink && ($iblockId = $iblockLink->findId()))
		{
			$nameParts = array(
				'prefix' => 'form',
				'type' => '',
				'iblock' => $iblockId->getValue(),
			);
			if ($form['TYPE'] == static::TYPE_ELEMENT)
			{
				$nameParts['type'] = 'element';
			}
			elseif ($form['TYPE'] == static::TYPE_SECTION)
			{
				$nameParts['type'] = 'section';
			}
			if ($nameParts['type'])
			{
				$name = implode('_', $nameParts);
			}
		}

		return $name;
	}

	/**
	 * @param string $value
	 * @param array $properties
	 *
	 * @return mixed
	 */
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

	public function update(Record $record)
	{
		$array = $this->recordToArray($record);

		$options = new \CUserOptions();
		$isUpdated = $options->setOption(
			$array['CATEGORY'],
			$array['NAME'],
			unserialize($array['VALUE']),
			$array['COMMON'] == 'Y',
			$array['USER_ID']
		);

		if (!$isUpdated)
		{
			throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_FORM_NOT_UPDATED');
		}
	}

	public function create(Record $record)
	{
		$array = $this->recordToArray($record);

		$options = new \CUserOptions();
		$isAdded = $options->setOption(
			$array['CATEGORY'],
			$array['NAME'],
			unserialize($array['VALUE']),
			$array['COMMON'] == 'Y',
			$array['USER_ID']
		);

		if ($isAdded)
		{
			$filter = array(
				'CATEGORY' => $array['CATEGORY'],
				'NAME' => $array['NAME'],
				'COMMON' => $array['COMMON'],
				'USER_ID' => $array['USER_ID'],
			);
			$userOption = \CUserOptions::getList(array(), $filter)->fetch();
			if ($userOption)
			{
				return $this->createId($userOption['ID']);
			}
			else
			{
				throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_FORM_NOT_CREATED');
			}
		}
		else
		{
			throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_FORM_NOT_CREATED');
		}
	}
}