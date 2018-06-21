<?php

namespace Intervolga\Migrato\Data\Module\BizProc;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Iblock\Iblock as IblockIblock;


class WorkflowTemplate extends BaseData
{
	protected function configure()
	{
		Loader::includeModule('bizproc');
		$this->setEntityNameLoc("Шаблон бизнес-процеса");
		// $this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.CATALOG_IBLOCK'));
		$this->setDependencies(array(
			'IBLOCK_ID' => new Link(IblockIblock::getInstance()),
		));
	}
	/**
	 * @param array $filter
	 * @return array
	 */
	public function getList(array $filter = array())
	{
		//Проверка
	}

}