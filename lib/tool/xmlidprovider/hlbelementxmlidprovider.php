<? namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Intervolga\Migrato\Data\RecordId;

class HlbElementXmlIdProvider extends BaseXmlIdProvider
{
	public function isXmlIdFieldExists()
	{
		return count($this->getNoXmlIdHlblocks()) == 0;
	}

	/**
	 * @return int[]
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\LoaderException
	 */
	protected function getNoXmlIdHlblocks()
	{
		$result = array();
		if (Loader::includeModule("highloadblock"))
		{
			$getList = HighloadBlockTable::getList(array(
				"select" => array(
					"ID",
				),
			));
			while ($hlblock = $getList->fetch())
			{
				$filter = array(
					"ENTITY_ID" => "HLBLOCK_" . $hlblock["ID"],
					"FIELD_NAME" => "UF_XML_ID",
				);
				$fieldsGetList = \CUserTypeEntity::getList(array(), $filter);

				if (!$fieldsGetList->selectedRowsCount())
				{
					$result[] = $hlblock["ID"];
				}
			}
		}

		return $result;
	}

	public function createXmlIdField()
	{
		foreach ($this->getNoXmlIdHlblocks() as $hlblockId)
		{
			$field = array(
				"ENTITY_ID" => "HLBLOCK_" . $hlblockId,
				"FIELD_NAME" => "UF_XML_ID",
				"USER_TYPE_ID" => "string",
				"XML_ID" => "HLBLOCK_" . $hlblockId . ".UF_MIGRATO_XML_ID",
				"SORT" => "100",
				"IS_SEARCHABLE" => "N",
			);
			$userTypeEntity = new \CUserTypeEntity();
			if (!$userTypeEntity->add($field))
			{
				throw new \Exception("UF_XML_ID userfield for hlblock $hlblockId elements was not created");
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 * @param string $xmlId
	 */
	public function setXmlId($id, $xmlId)
	{
		Loader::includeModule("highloadblock");
		$idArray = $id->getValue();

		$hlbFields = HighloadBlockTable::getById($idArray["HLBLOCK_ID"])->fetch();
		$hlbEntity = HighloadBlockTable::compileEntity($hlbFields);
		$hlbClass = $hlbEntity->getDataClass();
		$hlbClass::update($idArray["ID"], array("UF_XML_ID" => $xmlId));
	}

	/**
	 * @param RecordId $id
	 *
	 * @return string
	 */
	public function getXmlId($id)
	{
		Loader::includeModule("highloadblock");
		$idArray = $id->getValue();

		$hlbFields = HighloadBlockTable::getById($idArray["HLBLOCK_ID"])->fetch();
		$hlbEntity = HighloadBlockTable::compileEntity($hlbFields);
		$hlbClass = $hlbEntity->getDataClass();
		$hlblockElement = $hlbClass::getList(array(
			"select" => array(
				"UF_XML_ID",
			),
			"filter" => array(
				"=ID" => $idArray["ID"],
			),
		))->fetch();

		return $hlblockElement["UF_XML_ID"];
	}
}