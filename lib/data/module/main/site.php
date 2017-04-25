<?namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\SiteTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

class Site extends BaseData
{
	/**
	 * @param string[] $filter
	 *
	 * @return \Intervolga\Migrato\Data\Record[]
	 */
	public function getList(array $filter = array())
	{
		$result = array();

		$sitesGetList = SiteTable::getList();
		while ($site = $sitesGetList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($site['LID']);
			$record->setId($id);
			$record->setXmlId($site['LID']);
			$record->addFieldsRaw(array(
				'SORT' => $site['SORT'],
				'DEF' => $site['DEF'],
				'ACTIVE' => $site['ACTIVE'],
				'NAME' => $site['NAME'],
				'DIR' => $site['DIR'],
				'DOMAIN_LIMITED' => $site['DOMAIN_LIMITED'],
				'SITE_NAME' => $site['SITE_NAME'],
			));

			$link = clone $this->getDependency('CULTURE');
			$link->setValue(
				Culture::getInstance()->getXmlId(
					Culture::getInstance()->createId($site['CULTURE_ID'])
				)
			);
			$record->setDependency('CULTURE', $link);

			$link = clone $this->getDependency('LANGUAGE');
			$link->setValue(
				Language::getInstance()->getXmlId(
					Language::getInstance()->createId($site['LANGUAGE_ID'])
				)
			);
			$record->setDependency('LANGUAGE', $link);

			$result[] = $record;
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			'LANGUAGE' => new Link(Language::getInstance()),
			'CULTURE' => new Link(Culture::getInstance()),
		);
	}

	public function createId($id)
	{
		return RecordId::createStringId($id);
	}

	public function getXmlId($id)
	{
		return $id->getValue();
	}
}