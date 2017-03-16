<? namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Bitrix\Main\Loader;

class GroupXmlIdProvider extends BaseXmlIdProvider
{
	public function setXmlId($id, $xmlId)
	{
		$groupObject = new \CGroup();
		$isUpdated = $groupObject->update($id->getValue(), array("STRING_ID" => $xmlId));
		if (!$isUpdated)
		{
			throw new \Exception(trim(strip_tags($groupObject->LAST_ERROR)));
		}
	}

	public function getXmlId($id)
	{
		$element = \CGroup::GetByID($id->getValue());
		if ($element = $element->Fetch())
		{
			return $element["STRING_ID"];
		}
		else
		{
			return "";
		}
	}
}