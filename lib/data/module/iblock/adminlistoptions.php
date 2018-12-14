<?php
namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Iblock\Iblock as MigratoIblock;

/**
 * Class AdminListOptions - настройки отображения админ. страниц списка элементов и разделов инфоблока.
 *
 * @package Intervolga\Migrato\Data\Module\Iblock
 */
class AdminListOptions extends BaseData
{
	/**
	 * Категории настроек.
	 */
	const OPTION_CATEGORIES = array(
		'main.interface.grid',
		'main.interface.grid.common',
	);

	/**
	 * Префиксы названия настроек. После префиксов в названиях настроек идет <hash>:
	 * option_name=<prefix><hash>
	 *
	 * IB_MIXED_LIST - список элементов и разделов ИБ 				( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 * PRODUCTS_MIXED_LIST - список товаров и разделов каталога 	( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 *
	 * IB_SECTION_LIST - список разделов ИБ 						( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 * PRODUCTS_SECTION_LIST - список разделов каталога 			( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 *
	 * IB_ELEMENT_LIST - список элементов ИБ 						( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 * PRODUCTS_LIST - список товаров 								( <hash>=md5(IBLOCK_TYPE_CODE.IBLOCK_ID) )
	 *
	 * IB_LIST - список ИБ конкретного типа 						( <hash> = md5(IBLOCK_TYPE_CODE) )
	 * IB_LIST_ADMIN - список ИБ конкретного типа (admin=Y) 		( <hash> = md5(IBLOCK_TYPE_CODE) )
	 *
	 * IB_PROPERTIES_LIST - список свойств ИБ 						( <hash>=IBLOCK_ID )
	 */
	const OPTION_NAME_PREFIXES = array(
		'IB_MIXED_LIST' => 'tbl_iblock_list_',
		'PRODUCTS_MIXED_LIST' => 'tbl_product_list_',

		'IB_SECTION_LIST' => 'tbl_iblock_section_',
		'PRODUCTS_SECTION_LIST' => 'tbl_catalog_section_',

		'IB_ELEMENT_LIST' => 'tbl_iblock_element_',
		'PRODUCTS_LIST' => 'tbl_product_admin_',

		'IB_LIST' => 'tbl_iblock_',
		'IB_LIST_ADMIN' => 'tbl_iblock_admin_',

		'IB_PROPERTIES_LIST' => 'tbl_iblock_property_admin_',
	);

	protected function configure()
	{
		Loader::includeModule('iblock');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.IBLOCK_ADMIN_LIST_OPTIONS.ENTITY_NAME'));
		$this->setVirtualXmlId(true);
		$this->setFilesSubdir('/type/iblock/admin/');
		$this->setDependencies(array(
			'IBLOCK' => new Link(MigratoIblock::getInstance()),
			'PROPERTY' => new Link(Property::getInstance()),
			'FIELD' => new Link(Field::getInstance()),
		));
	}

	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		return parent::getList($filter);
	}
}