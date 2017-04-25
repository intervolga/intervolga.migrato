<?namespace Intervolga\Migrato\Data\Module\Sale;

use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\PersonTypeSiteTable;
use Bitrix\Sale\Internals\PersonTypeTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Main\Site;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Tool\XmlIdProvider\TableXmlIdProvider;

class PersonType extends BaseData
{
	public function __construct()
	{
		Loader::includeModule("sale");
		$this->xmlIdProvider = new TableXmlIdProvider($this);
	}

	public function isIdExists($id)
	{
		return !!PersonTypeTable::getById($id->getValue())->fetch();
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$personTypesSites = $this->getPersonTypesSites();
		$getList = PersonTypeTable::getList();
		while ($personType = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($personType["ID"]);
			$record->setId($id);
			$record->setXmlId(
				$this->getXmlId($id)
			);
			$record->addFieldsRaw(array(
				"NAME" => $personType["NAME"],
				"SORT" => $personType["SORT"],
				"ACTIVE" => $personType["ACTIVE"],
			));

			$link = clone $this->getDependency('SITE');
			$link->setValues($personTypesSites[$personType["ID"]]);
			$record->setDependency('SITE', $link);

			$result[] = $record;
		}

		return $result;
	}

	public function getDependencies()
	{
		return array(
			'SITE' => new Link(Site::getInstance()),
		);
	}

	/**
	 * @return array
	 */
	protected function getPersonTypesSites()
	{
		$result = array();
		$getList = PersonTypeSiteTable::getList();
		while ($personTypeSite = $getList->fetch())
		{
			$result[$personTypeSite["PERSON_TYPE_ID"]][] = Site::getInstance()->getXmlId(
				Site::getInstance()->createId($personTypeSite["SITE_ID"])
			);
		}
		return $result;
	}

	public function update(Record $record)
	{
		$id = $record->getId()->getValue();
		$update = $this->recordToArray($record);
		$object = new \CSalePersonType();
		$updateResult = $object->update($id, $update);
		if (!$updateResult)
		{
			global $APPLICATION;
			throw new \Exception($APPLICATION->getException()->getString());
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function recordToArray(Record $record)
	{
		$array = array(
			'NAME' => $record->getFieldRaw('NAME'),
			'SORT' => $record->getFieldRaw('SORT'),
			'ACTIVE' => $record->getFieldRaw('ACTIVE'),
			'LID' => $record->getFieldRaws('LID'),
		);
		$link = $record->getDependency('SITE');
		if ($link && $link->getValues())
		{
			foreach ($link->findIds() as $siteIdObject)
			{
				$array['LID'][] = $siteIdObject->getValue();
			}
		}

		return $array;
	}

	public function create(Record $record)
	{
		$add = $this->recordToArray($record);
		$object = new \CSalePersonType();
		$id = $object->add($add);
		if ($id)
		{
			return $this->createId($id);
		}
		else
		{
			global $APPLICATION;
			throw new \Exception($APPLICATION->getException()->getString());
		}
	}

	public function delete($xmlId)
	{
		$id = $this->findRecord($xmlId);
		if ($id)
		{
			$object = new \CSalePersonType();
			$result = $object->delete($id->getValue());
			if (!$result)
			{
				global $APPLICATION;
				throw new \Exception($APPLICATION->getException()->getString());
			}
		}
	}
}