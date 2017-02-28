<?namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\UfXmlIdProvider;

class HighloadBlock extends BaseData
{
	protected function __construct()
	{
		Loader::includeModule("highloadblock");
		$this->xmlIdProvider = new UfXmlIdProvider($this, "HLBLOCK");
	}

	public function getList(array $filter = array())
	{
		$hlBlocks = HighloadBlockTable::getList();
		$result = array();
		while($hlBlock = $hlBlocks->fetch())
		{
			$record = new Record($this);
			$id = RecordId::createNumericId($hlBlock["ID"]);
			$xmlId = $this->xmlIdProvider->getXmlId($id);
			$record->setXmlId($xmlId);
			$record->setId($id);
			$record->setFields(array(
				"NAME" => $hlBlock["NAME"],
				"TABLE_NAME" => $hlBlock["TABLE_NAME"],
			));

			$result[] = $record;
		}

		return $result;
	}
}