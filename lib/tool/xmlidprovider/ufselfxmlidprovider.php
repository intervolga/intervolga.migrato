<?namespace Intervolga\Migrato\Tool\XmlIdProvider;

class UfSelfXmlIdProvider extends BaseXmlIdProvider
{
	public function setXmlId($id, $xmlId)
	{
		$userFieldObject = new \CUserTypeEntity();
		$userFieldObject->update($id->getValue(), array("XML_ID" => $xmlId));
	}

	public function getXmlId($id)
	{
		$userField = \CUserTypeEntity::getById($id->getValue());
		return $userField["XML_ID"];
	}
}