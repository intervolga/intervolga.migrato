<?namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Localization\CultureTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;

class Culture extends BaseData
{
	public function getFilesSubdir()
	{
		return '/loc/';
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = CultureTable::getList();
		while ($culture = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($culture['ID']);
			$record->setId($id);
			$record->setXmlId($culture['NAME']);
			$record->addFieldsRaw(array(
				'CODE' => $culture['CODE'],
				'FORMAT_DATE' => $culture['FORMAT_DATE'],
				'FORMAT_DATETIME' => $culture['FORMAT_DATETIME'],
				'FORMAT_NAME' => $culture['FORMAT_NAME'],
				'WEEK_START' => $culture['WEEK_START'],
				'CHARSET' => $culture['CHARSET'],
				'DIRECTION' => $culture['DIRECTION'],
			));
			$result[] = $record;
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$culture = CultureTable::getById($id->getValue())->fetch();
		return $culture['NAME'];
	}
}