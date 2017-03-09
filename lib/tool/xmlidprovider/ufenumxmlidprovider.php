<? namespace Intervolga\Migrato\Tool\XmlIdProvider;


class UfEnumXmlIdProvider extends BaseXmlIdProvider
{
    public function setXmlId($id, $xmlId)
    {
        $userFieldEnum = null;
        $rsUserFieldEnum = \CUserFieldEnum::GetList(array(), array("ID" => $id));
        if($arFieldEnum = $rsUserFieldEnum->Fetch())
        {
            $arFieldEnum["XML_ID"] = $xmlId;
            $userFieldObject = new \CUserFieldEnum();
            $userFieldObject->SetEnumValues($arFieldEnum["USER_FIELD_ID"], $arFieldEnum);
        }
    }

    public function getXmlId($id)
    {
        $xmlId = null;
        if($id = $id->getValue())
        {
            $userFieldEnum = \CUserFieldEnum::GetList(array(), array("ID" => $id));
            if($arFieldEnum = $userFieldEnum->Fetch())
            {
                $xmlId = $arFieldEnum["XML_ID"];
            }
        }
        return $xmlId;
    }
}