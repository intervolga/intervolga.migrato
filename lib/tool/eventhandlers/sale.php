<?namespace Intervolga\Migrato\Tool\EventHandlers;

use Bitrix\Main\Entity\Event;
use Bitrix\Main\Entity\EventResult;
use Bitrix\Main\Entity\FieldError;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Internals\OrderPropsTable;
use Bitrix\Sale\Internals\PersonTypeSiteTable;
use Bitrix\Sale\Internals\PersonTypeTable;

Loc::loadMessages(__FILE__);

class Sale
{
	public static function onBeforePersonTypeUpdate($id, &$fields)
	{
		if (!defined('INTERVOLGA_MIGRATO_DISABLE_XML_ID_CONTROL'))
		{
			if (Loader::includeModule('sale'))
			{
				if (array_key_exists('NAME', $fields))
				{
					if ($fields['NAME'] != static::getPersonTypeName($id))
					{
						global $APPLICATION;
						$APPLICATION->throwException(Loc::getMessage('INTERVOLGA_MIGRATO.CHANGING_NAME_WARNING'));
						return false;
					}
				}
				if (array_key_exists('LID', $fields))
				{
					$oldSites = static::getPersonTypeSites($id);
					if (array_diff($oldSites, $fields['LID']) || array_diff($fields['LID'], $oldSites))
					{
						global $APPLICATION;
						$APPLICATION->throwException(Loc::getMessage('INTERVOLGA_MIGRATO.CHANGING_SITES_WARNING'));
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * @param int $id
	 *
	 * @return string
	 * @throws \Bitrix\Main\LoaderException
	 */
	protected static function getPersonTypeName($id)
	{
		if (Loader::includeModule('sale'))
		{
			$oldPersonType = PersonTypeTable::getById($id)->fetch();
			return $oldPersonType['NAME'];
		}
		return '';
	}

	/**
	 * @param $id
	 *
	 * @return string[]
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected static function getPersonTypeSites($id)
	{
		$result = array();
		$getList = PersonTypeSiteTable::getList(array(
			'filter' => array(
				'PERSON_TYPE_ID' => $id,
			),
		));

		while ($personTypeSite = $getList->fetch())
		{
			$result[] = $personTypeSite['SITE_ID'];
		}

		return $result;
	}

	public static function onBeforeUpdateOrderPropsTable(Event $e)
	{
		$result = new EventResult();
		if (!defined('INTERVOLGA_MIGRATO_DISABLE_XML_ID_CONTROL'))
		{
			if (Loader::includeModule('sale'))
			{
				$fields = $e->getParameter('fields');
				if (array_key_exists('CODE', $fields))
				{
					$id = $e->getParameter('id');
					if (is_array($id))
					{
						$id = $id['ID'];
					}
					if ($fields['CODE'] != static::getOrderPropCode($id))
					{
						$result->addError(new FieldError(
							$e->getEntity()->getField('CODE'),
							Loc::getMessage('INTERVOLGA_MIGRATO.CHANGING_CODE_WARNING')
						));
					}
				}
			}
		}
		return $result;
	}

	/**
	 * @param int $id
	 *
	 * @return string
	 * @throws \Bitrix\Main\LoaderException
	 */
	protected static function getOrderPropCode($id)
	{
		if (Loader::includeModule('sale'))
		{
			$prop = OrderPropsTable::getById($id)->fetch();
			return $prop['CODE'];
		}

		return '';
	}
}