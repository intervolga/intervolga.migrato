<?namespace Intervolga\Migrato\Data\Module\advertising;

use Bitrix\advertising\banner;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Bitrix\Main\Localization\LanguageTable;

class BannerType extends BaseData{

    protected function __construct()
    {
        Loader::includeModule("advertising");
    }

    /**
     * @param array $filter
     * @return array
     */
    public function getList(array $filter = array())//+
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

    /**
     * @param mixed $id
     * @return static
     */
    public function createId($id)
    {
        return RecordId::createStringId($id);
    }

    /**
     * @param RecordId $id
     * @return mixed
     * @throws \Exception
     */
    public function getXmlId($id)
    {
        $bannertype = \CAdvType::getById($id->getValue())->fetch();
        return $bannertype['SID'];
    }

    /**
     * @param RecordId $id
     * @param string $xmlId
     * @throws \Exception
     */
    public function setXmlId($id, $xmlId)
    {
        \CAdvType::Set(array('SID' => $xmlId), $id->getValue());
    }

    /**
     * @param Record $record
     * @throws \Exception
     */
    public function update(Record $record)
    {
        $data = $this->recordToArray($record);
        $id = $record->getId()->getValue();
        \CAdvType::Set($data, $id);
        global $strError;
        if (!strlen($strError)<=0)
        {
            throw new \Exception(implode(', ', $strError));
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

    /**
     * @param Record $record
     * @return RecordId|static
     * @throws \Exception
     */
    protected function createInner(Record $record)
    {
        $data = $this->recordToArray($record);
        $result = \CAdvType::Set($data);
        global $strError;
        if (strlen($strError)<=0)
        {
            return $this->createId($result);
        }
        else
        {
            throw new \Exception(implode(', ', $strError));
        }
    }

    protected function deleteInner(RecordId $id)
    {
        \CAdvType::delete($id->getValue());
    }
}