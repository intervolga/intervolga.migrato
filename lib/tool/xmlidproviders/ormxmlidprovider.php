<?namespace Intervolga\Migrato\Tool\XmlIdProviders;

use Bitrix\Main\Entity\DataManager;
use Intervolga\Migrato\Data\BaseData;

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
		$dataManager::update($id->getValue(), array("XML_ID" => $xmlId));
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
}