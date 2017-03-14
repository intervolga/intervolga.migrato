<? namespace Intervolga\Migrato\Tool\XmlIdProvider;


class EnumXmlIdProvider extends BaseXmlIdProvider
{
	public function setXmlId($id, $xmlId)
	{
		$userFieldEnum = \CIBlockPropertyEnum::GetByID($id->getValue());
		$arFields = array(
			"XML_ID" => $xmlId,
			"PROPERTY_ID" => $userFieldEnum["PROPERTY_ID"]
		);
		\CIBlockPropertyEnum::Update($id, $arFields);
	}

	public function getXmlId($id)
	{
		$xmlId = null;
		if($id = $id->getValue())
		{
			$userFieldEnum = \CIBlockPropertyEnum::GetByID($id);
			$xmlId = $userFieldEnum["XML_ID"];
		}
		return $xmlId;
	}
}