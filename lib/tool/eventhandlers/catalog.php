<?namespace Intervolga\Migrato\Tool\EventHandlers;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class Store
{
    public static function OnBeforeCatalogStoreAdd(&$arFields)
    {
        $storesXMLIDs= getStoresPropertyValue("XML_ID");

        if (in_array($arFields['XML_ID'], $storesXMLIDs)){
            global $APPLICATION;
            $APPLICATION->throwException(Loc::getMessage('INTERVOLGA_MIGRATO.SAME_STORE_XML_ID'));
            return false;
        }
    }


    /**
     * @param string $id
     *
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    protected static function getStoresPropertyValue($property)
    {
        if (Loader::includeModule('catalog'))
        {
            $arSelectFields = array($property);
            $store = CCatalogStore::GetList(
                array(),
                array(),
                false,
                false,
                $arSelectFields
            );
            while ($res = $store->GetNext()){
                $storesXMLs[]=$res[$property];
            }
        }
        return $storesXMLs;
    }
}