<?php
namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Catalog\CatalogIblockTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyIndex\Manager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ReIndexFacetCommand extends BaseCommand
{
	protected static $savedActiveFacet;

	protected function configure()
	{
		$this->setName('reindexfacet');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.REINDEX_FACET_DESCRIPTION'));
	}

	public function executeInner()
	{
		if (self::$savedActiveFacet)
		{
			$this->reIndexFacet(self::$savedActiveFacet);
		}
	}

	public static function saveActiveFacet()
	{
		self::$savedActiveFacet = static::getIblockIdsByFilter(
			array('=PROPERTY_INDEX' => 'Y')
		);
	}

	/**
	 * @param $iblockIds
	 * @return bool
	 */
	protected function reIndexFacet($iblockIds)
	{
		if (!empty($iblockIds))
		{
			foreach ($iblockIds as $key => $item)
			{
				$index = Manager::createIndexer($key);
				$index->startIndex();
				$index->continueIndex();
				$index->endIndex();

				\CIBlock::clearIblockTagCache($key);
			}

			Manager::checkAdminNotification();
			\CBitrixComponent::clearComponentCache('bitrix:catalog.smart.filter');
		}

		return true;
	}

	/**
	 * @param $iblockFilter
	 * @return array
	 */
	public static function getIblockIdsByFilter($iblockFilter = array())
	{
		$iblockDropDown = array();
		if (Loader::includeModule('catalog'))
		{
			$offerIblocks = array();
			$offersIterator = CatalogIblockTable::getList(array(
				'select' => array('IBLOCK_ID'),
				'filter' => array('!PRODUCT_IBLOCK_ID' => 0),
			));
			while ($offer = $offersIterator->fetch())
			{
				$offerIblocks[] = (int) $offer['IBLOCK_ID'];
			}
			unset($offer);
			if (!empty($offerIblocks))
			{
				$iblockFilter['!ID'] = $offerIblocks;
			}
			unset($offersIterator, $offerIblocks);
		}
		$iblockList = IblockTable::getList(array(
			'select' => array('ID', 'NAME', 'ACTIVE'),
			'filter' => $iblockFilter,
			'order' => array('ID' => 'asc', 'NAME' => 'asc'),
		));
		while ($iblockInfo = $iblockList->fetch())
		{
			$iblockDropDown[$iblockInfo['ID']] = $iblockInfo['ID'];
		}
		unset($iblockInfo, $iblockList);

		return $iblockDropDown;
	}
}