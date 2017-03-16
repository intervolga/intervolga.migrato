<?namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Orm\XmlIdTable;

class TypeXmlIdProvider extends TableXmlIdProvider
{
	public function setXmlId($id, $xmlId)
	{
		$tableId = $this->getTableXmlIdRecordId($id);
		if ($tableId)
		{
			XmlIdTable::update($tableId, array("DATA_XML_ID" => $id->getValue()));
		}
		else
		{
			$add = array(
				"MODULE_NAME" => $this->dataClass->getModule(),
				"ENTITY_NAME" => $this->dataClass->getEntityName(),
				"DATA_XML_ID" => $id->getValue(),
				"DATA_ID_STR" => $id->getValue()
			);

			XmlIdTable::add($add);
		}
	}
}