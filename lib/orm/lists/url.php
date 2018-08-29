<?
namespace Intervolga\Migrato\Orm\Lists;

use Bitrix\Main,
	Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * Class UrlTable
 *
 * Fields:
 * <ul>
 * <li> IBLOCK_ID int mandatory
 * <li> URL string(500) optional
 * <li> LIVE_FEED int optional
 * <li> IBLOCK reference to {@link \Bitrix\Iblock\IblockTable}
 * </ul>
 *
 * @package Intervolga\Migrato\Orm\Lists
 **/

class UrlTable extends Main\Entity\DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'b_lists_url';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return array(
			'IBLOCK_ID' => array(
				'data_type' => 'integer',
				'primary' => true,
				'title' => Loc::getMessage('URL_ENTITY_IBLOCK_ID_FIELD'),
			),
			'URL' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateUrl'),
				'title' => Loc::getMessage('URL_ENTITY_URL_FIELD'),
			),
			'LIVE_FEED' => array(
				'data_type' => 'integer',
				'title' => Loc::getMessage('URL_ENTITY_LIVE_FEED_FIELD'),
			),
			'IBLOCK' => array(
				'data_type' => 'Bitrix\Iblock\Iblock',
				'reference' => array('=this.IBLOCK_ID' => 'ref.ID'),
			),
		);
	}
	/**
	 * Returns validators for URL field.
	 *
	 * @return array
	 */
	public static function validateUrl()
	{
		return array(
			new Main\Entity\Validator\Length(null, 500),
		);
	}
}