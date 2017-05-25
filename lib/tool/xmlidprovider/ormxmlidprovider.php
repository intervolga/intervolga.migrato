<?namespace Intervolga\Migrato\Tool\XmlIdProvider;

use Bitrix\Main\Entity\DataManager;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\RecordId;

class OrmXmlIdProvider extends BaseXmlIdProvider
{
	/**
	 * @var string|DataManager
	 */
	protected $dataManager = "";

	/**
	 * @param BaseData $dataClass
	 * @param string|DataManager $dataManager
	 */
	public function __construct(BaseData $dataClass, $dataManager)
	{
		parent::__construct($dataClass);
		$this->dataManager = $dataManager;
	}

	public function setXmlId($id, $xmlId)
	{
		$dataManager = $this->dataManager;
		$updateResult = $dataManager::update($id->getValue(), array("XML_ID" => $xmlId));
		if (!$updateResult->isSuccess())
		{
			throw new \Exception(implode(";", $updateResult->getErrorMessages()));
		}
	}

	public function getXmlId($id)
	{
		$dataManager = $this->dataManager;
		$iblock = $dataManager::getList(array(
			"filter" => array(
				"=ID" => $id->getValue(),
			),
			"select" => array(
				"XML_ID",
			)
		))->fetch();
		if ($iblock)
		{
			return $iblock["XML_ID"];
		}
		else
		{
			return "";
		}
	}

	public function findRecord($xmlId)
	{
		$parameters = array(
			"select" => array(
				"ID",
				"XML_ID",
			),
			"filter" => array(
				"=XML_ID" => $xmlId,
			),
		);
		$dataManager = $this->dataManager;
		$getList = $dataManager::getList($parameters);
		while ($record = $getList->fetch())
		{
			return RecordId::createNumericId($record["ID"]);
		}
		return null;
	}
}