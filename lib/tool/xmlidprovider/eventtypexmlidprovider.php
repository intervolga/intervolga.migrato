<? namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Bitrix\Main\Loader;
use Bitrix\Seo\Engine\Bitrix;

class EventTypeXmlIdProvider extends BaseXmlIdProvider
{
	public function setXmlId($id, $xmlId)
	{
		$fields = explode("___", $xmlId);
		$isUpdated = \CEventType::update(
			array("ID" => $id->getValue()),
			array("LID" => $fields[0], "EVENT_NAME" => $fields[1])
		);
		if (!$isUpdated)
		{
			global $APPLICATION;
			throw new \Exception(trim(strip_tags($APPLICATION->getException()->getString())));
		}
	}

	public function getXmlId($id)
	{
		$eventType = \CEventType::GetList(array("ID" => $id->getValue()));
		if ($type = $eventType->Fetch())
		{
			return $type["LID"] . "___" .  $type["EVENT_NAME"];
		}
		else
		{
			return "";
		}
	}
}