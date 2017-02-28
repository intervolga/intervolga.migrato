<?namespace Intervolga\Migrato\Data;

use Intervolga\Migrato\Tool\XmlIdProvider\UfSelfXmlIdProvider;

abstract class BaseUserField extends BaseData
{
	public function __construct()
	{
		$this->xmlIdProvider = new UfSelfXmlIdProvider($this);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CUserTypeEntity::getList();
		while ($userField = $getList->fetch())
		{
			if ($this->isCurrentUserField($userField["ENTITY_ID"]))
			{
				$result[] = $this->userFieldToRecord($userField);
			}
		}
		return $result;
	}

	/**
	 * @param string $userFieldEntityId
	 * @return bool
	 */
	abstract protected function isCurrentUserField($userFieldEntityId);

	/**
	 * @param array $userField
	 * @return Record
	 */
	abstract protected function userFieldToRecord(array $userField);
}