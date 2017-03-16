<? namespace Intervolga\Migrato\Data\Module\Highloadblock;

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
		while ($hlBlock = $hlBlocks->fetch())
		{
			$record = new Record($this);
			$id = RecordId::createNumericId($hlBlock["ID"]);
			$xmlId = $this->xmlIdProvider->getXmlId($id);
			$record->setXmlId($xmlId);
			$record->setId($id);
			$record->addFieldsRaw(array(
				"NAME" => $hlBlock["NAME"],
				"TABLE_NAME" => $hlBlock["TABLE_NAME"],
			));

			$result[] = $record;
		}

		return $result;
	}

	public function update(Record $record)
	{
		$result = HighloadBlockTable::update($record->getId()->getValue(), $record->getFieldsRaw());
		if (!$result->isSuccess())
		{
			throw new \Exception(trim(strip_tags($result->getErrorMessages())));
		}
	}

	public function create(Record $record)
	{
		$result = HighloadBlockTable::add($record->getFieldsRaw());
		if ($result->isSuccess())
		{
			$id = RecordId::createNumericId($result->getId());
			$this->setXmlId($id, $record->getXmlId());

			return $id;
		} else {
			throw new \Exception(trim(strip_tags($result->getErrorMessages())));
		}
	}

	public function delete($xmlId)
	{
		$result = HighloadBlockTable::delete($this->findRecord($xmlId)->getValue());
		if (!$result->isSuccess())
		{
			throw new \Exception(trim(strip_tags($result->getErrorMessages())));
		}
	}
}