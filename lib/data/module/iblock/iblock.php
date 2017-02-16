<?namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Tool\DataRecord;
use Intervolga\Migrato\Tool\DataRecordId;
use Intervolga\Migrato\Tool\DataLink;
use Intervolga\Migrato\Tool\XmlIdProviders\OrmXmlIdProvider;

class Iblock extends BaseData
{
	protected function __construct()
	{
		Loader::includeModule("iblock");
		$this->xmlIdProvider = new OrmXmlIdProvider($this, "\\Bitrix\\Iblock\\IblockTable");
	}

	/**
	 * @return array|DataRecord[]
	 */
	public function getFromDatabase()
	{
		$result = array();
		$getList = \CIBlock::GetList();
		while ($iblock = $getList->fetch())
		{
			$record = new DataRecord();
			$record->setXmlId($iblock["XML_ID"]);
			$record->setId(DataRecordId::createNumericId($iblock["ID"]));
			$record->setFields(array(
				"SITE_ID" => $iblock["SITE_ID"],
				"CODE" => $iblock["CODE"],
				"NAME" => $iblock["NAME"],
				"ACTIVE" => $iblock["ACTIVE"],
			));
			$record->addDependency(
				"IBLOCK_TYPE_ID",
				new DataLink(
					Type::getInstance(),
					Type::getInstance()->getXmlIdProvider()->getXmlId(DataRecordId::createStringId($iblock["IBLOCK_TYPE_ID"]))
				)
			);
			$result[] = $record;
		}

		return $result;
	}



	/**
	 * @param DataRecord $record
	 */
	public function update(DataRecord $record)
	{
		// TODO: Implement update() method.
	}

	/**
	 * @param DataRecord $record
	 */
	public function create(DataRecord $record)
	{
		// TODO: Implement create() method.
	}

	/**
	 * @param $xmlId
	 */
	public function delete($xmlId)
	{
		// TODO: Implement delete() method.
	}
}