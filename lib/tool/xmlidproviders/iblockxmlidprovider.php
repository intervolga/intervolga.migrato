<?namespace Intervolga\Migrato\Tool\XmlIdProviders;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Tool\DataRecordId;

class IblockXmlIdProvider extends BaseXmlIdProvider
{
	public function __construct(\Intervolga\Migrato\Data\BaseData $dataClass)
	{
		parent::__construct($dataClass);
		Loader::includeModule("iblock");
	}

	/**
	 * @param DataRecordId $id
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	public function setXmlId($id, $xmlId)
	{
		IblockTable::update($id->getValue(), array("XML_ID" => $xmlId));
	}

	public function getXmlId($id)
	{
		$iblock = IblockTable::getList(array(
			"filter" => array(
				"=ID" => $id->getValue(),
			),
			"select" => array(
				"XML_ID",
			)
		))->fetch();
		if ($iblock)
		{
			return $iblock["XML_ID"];
		}
		else
		{
			return "";
		}
	}
}