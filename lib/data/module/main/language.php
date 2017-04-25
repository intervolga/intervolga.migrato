<?namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\LanguageTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

class Language extends BaseData
{
	public function getList(array $filter = array())
	{
		$result = array();
		$getList = LanguageTable::getList();
		while ($language = $getList->fetch())
		{
			$record = new Record();
			$id = $this->createId($language['LID']);
			$record->setId($id);
			$record->setXmlId($language['LID']);
			$record->addFieldsRaw(array(
				'SORT' => $language['SORT'],
				'DEF' => $language['DEF'],
				'ACTIVE' => $language['ACTIVE'],
				'NAME' => $language['NAME'],
				'DIRECTION' => $language['DIRECTION'],
			));

			$link = clone $this->getDependency('CULTURE');
			$link->setValue(
				Culture::getInstance()->getXmlId(
					Culture::getInstance()->createId($language['CULTURE_ID'])
				)
			);
			$record->setDependency('CULTURE', $link);
			$result[] = $record;
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			'CULTURE' => new Link(Culture::getInstance()),
		);
	}

	public function createId($id)
	{
		return RecordId::createStringId($id);
	}
}