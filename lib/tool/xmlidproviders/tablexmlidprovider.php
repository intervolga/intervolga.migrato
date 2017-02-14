<?namespace Intervolga\Migrato\Tool\XmlIdProviders;

use Intervolga\Migrato\Tool\DataRecordId;
use Intervolga\Migrato\Tool\MigratoDataTable;

class TableXmlIdProvider extends BaseXmlIdProvider
{
	public function setXmlId($id, $xmlId)
	{
		$tableId = $this->getTableXmlIdRecordId($id);
		if ($tableId)
		{
			MigratoDataTable::update($tableId, array("DATA_XML_ID" => $xmlId));
		}
		else
		{
			$add = array(
				"MODULE_NAME" => $this->dataClass->getModule(),
				"ENTITY_NAME" => $this->dataClass->getEntityName(),
				"DATA_XML_ID" => $xmlId,
			);
			if ($id->getType() == DataRecordId::TYPE_NUMERIC)
			{
				$add["DATA_ID_NUM"] = $id->getValue();
			}
			if ($id->getType() == DataRecordId::TYPE_STRING)
			{
				$add["DATA_ID_STR"] = $id->getValue();
			}
			if ($id->getType() == DataRecordId::TYPE_COMPLEX)
			{
				$add["DATA_ID_COMPLEX"] = $id->getValue();
			}
			MigratoDataTable::add($add);
		}
	}

	/**
	 * @param DataRecordId $id
	 */
	private function getTableXmlIdRecordId($id)
	{
		$parameters = array(
			"select" => array(
				"ID",
			),
			"filter" => $this->makeTableXmlIdFilterWithId($id),
		);
		$record = MigratoDataTable::getList($parameters)->fetch();
		return $record["ID"];
	}

	public function getXmlId($id)
	{
		$parameters = array(
			"select" => array(
				"DATA_XML_ID",
			),
			"filter" => $this->makeTableXmlIdFilterWithId($id),
			"limit" => 1,
		);
		$record = MigratoDataTable::getList($parameters)->fetch();
		if ($record)
		{
			return $record["DATA_XML_ID"];
		}
		else
		{
			return "";
		}
	}

	/**
	 * @return array
	 */
	private function makeTableXmlIdFilter()
	{
		return array(
			"=MODULE_NAME" => $this->dataClass->getModule(),
			"=ENTITY_NAME" => $this->dataClass->getEntityName(),
		);
	}

	/**
	 * @param DataRecordId $id
	 *
	 * @return array
	 */
	private function makeTableXmlIdFilterWithId($id)
	{
		$filter = $this->makeTableXmlIdFilter();
		if ($id->getType() == DataRecordId::TYPE_NUMERIC)
		{
			$filter["=DATA_ID_NUM"] = $id->getValue();
		}
		if ($id->getType() == DataRecordId::TYPE_STRING)
		{
			$filter["=DATA_ID_STR"] = $id->getValue();
		}
		if ($id->getType() == DataRecordId::TYPE_COMPLEX)
		{
			$filter["=DATA_ID_COMPLEX"] = $id->getValue();
		}

		return $filter;
	}
}