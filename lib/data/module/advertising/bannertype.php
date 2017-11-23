<?namespace Intervolga\Migrato\Data\Module\advertising;


use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

Loc::loadMessages(__FILE__);

class BannerType extends BaseData{

    protected function configure()
    {
        Loader::includeModule("advertising");
        $this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.ADVERTISING_BANNER_TYPE'));
    }

    public function getList(array $filter = array())
    {
        $result = array();
        $getList = \CAdvType::getList();
        while ($bannertype = $getList->fetch())
        {
            $record = new Record($this);
            $id = $this->createId($bannertype['SID']);
            $record->setId($id);
            $record->setXmlId($bannertype['SID']);
            $record->addFieldsRaw(array(
                    "SID" => $bannertype["SID"],
                    "ACTIVE" => $bannertype["ACTIVE"],
                    "SORT" => $bannertype["SORT"],
                    "NAME" => $bannertype["NAME"],
                    "DESCRIPTION" => $bannertype["DESCRIPTION"],
            ));
            $result[] = $record;
        }
        return $result;
    }

    public function createId($id)
    {
        return RecordId::createStringId($id);
    }

    public function getXmlId($id)
    {
        $bannertype = \CAdvType::getById($id->getValue())->fetch();
        return $bannertype['SID'];
    }

    public function setXmlId($id, $xmlId)
    {
        \CAdvType::Set(array('SID' => $xmlId), $id->getValue());
    }

    public function update(Record $record)
    {
        $data = $this->recordToArray($record);
        $id = $record->getId()->getValue();
        $result = \CAdvType::Set($data, $id);
        global $strError;
        if (!$result){
            throw new \Exception($strError);
        }
        else if(!$result && !$strError){
            throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.ADVERTISING_UNKNOWN_ERROR'));
        }
    }

    /**
     * @param Record $record
     * @return array
     */
    protected function recordToArray(Record $record)
    {
        $array = array(
            'SID' => $record->getXmlId(),
            'ACTIVE' => $record->getFieldRaw('ACTIVE'),
            'SORT' => $record->getFieldRaw('SORT'),
            'NAME' => $record->getFieldRaw('NAME'),
            'DESCRIPTION' => $record->getFieldRaw('DESCRIPTION'),
        );
        return $array;
    }

    protected function createInner(Record $record)
    {
        $data = $this->recordToArray($record);
        $result = \CAdvType::Set($data,"");
        global $strError;
        if ($result){
            return $this->createId($result);
        }
        else {
            if ($strError){
                throw new \Exception($strError);
            }
            else{
                throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.ADVERTISING_UNKNOWN_ERROR'));
            }
        }
    }

    protected function deleteInner(RecordId $id)
    {
        \CAdvType::delete($id->getValue());
    }
}