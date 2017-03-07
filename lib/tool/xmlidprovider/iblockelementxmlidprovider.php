<? namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Bitrix\Main\Loader;

class IblockElementXmlIdProvider extends BaseXmlIdProvider
{
	public function setXmlId($id, $xmlId)
	{
		Loader::includeModule("iblock");

		$elementObject = new \CIBlockElement();
		$isUpdated = $elementObject->update($id->getValue(), array("XML_ID" => $xmlId));
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($elementObject->LAST_ERROR)));
		}
	}

	public function getXmlId($id)
	{
		Loader::includeModule("iblock");
		$elements = \CIBlockElement::getList(
			array("ID" => "ASC"),
			array("ID" => $id->getValue()),
			false,
			false,
			array("ID", "XML_ID")
		);
		if ($element = $elements->fetch())
		{
			return $element["XML_ID"];
		}
		else
		{
			return "";
		}
	}
}