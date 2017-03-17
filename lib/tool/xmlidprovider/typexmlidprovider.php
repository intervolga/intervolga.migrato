<?namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Orm\XmlIdTable;

class TypeXmlIdProvider extends BaseXmlIdProvider
{
	public function setXmlId($id, $xmlId)
	{
		$rsType = \CIBlockType::GetByID($id->getValue());
		if($arType = $rsType->Fetch())
		{
			$arFields = array(
				"ID" => $xmlId,
				"SECTIONS" => $arType["SECTIONS"],
				"IN_RSS" => $arType["IN_RSS"]
			);
			$type = new \CIBlockType();
			$isUpdated = $type->Update($id, $arFields);
			if(!$isUpdated)
			{
				throw new \Exception("Ошибка обновления xmlId элемента iblocktype " . $id->getValue());
			}
		}
	}

	public function getXmlId($id)
	{
		return $id->getValue();
	}
}