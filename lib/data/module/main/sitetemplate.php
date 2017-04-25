<?namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\SiteTemplateTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;

class SiteTemplate extends BaseData
{
	public function getFilesSubdir()
	{
		return '/site/';
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = SiteTemplateTable::getList();
		while ($siteTemplate = $getList->fetch())
		{
			$record = new Record($this);
			$record->setId($this->createId($siteTemplate['ID']));
			$record->setXmlId($this->getMd5($siteTemplate));
			$record->addFieldsRaw(array(
				'CONDITION' => $siteTemplate['CONDITION'],
				'SORT' => $siteTemplate['SORT'],
				'TEMPLATE' => $siteTemplate['TEMPLATE'],
			));

			$link = clone $this->getDependency('SITE');
			$link->setValue(
				Site::getInstance()->getXmlId(
					Site::getInstance()->createId($siteTemplate['SITE_ID'])
				)
			);
			$record->setDependency('SITE', $link);

			$result[] = $record;
		}
		return $result;
	}

	public function getDependencies()
	{
		return array(
			'SITE' => new Link(Site::getInstance()),
		);
	}

	public function getXmlId($id)
	{
		$getList = SiteTemplateTable::getList(array(
			'filter' => array(
				'=ID' => $id->getValue(),
			)
		));

		$siteTemplate = $getList->fetch();
		return $this->getMd5($siteTemplate);
	}

	/**
	 * @param array $tpl
	 *
	 * @return string
	 */
	protected function getMd5(array $tpl)
	{
		return md5($tpl['SITE_ID'] . $tpl['CONDITION'] . $tpl['TEMPLATE']);
	}
}