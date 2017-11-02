<?namespace Intervolga\Migrato\Data;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class Link extends Value
{
	protected $targetData = null;
	protected $id = null;
	protected $toCustomField = "";

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $target
	 * @param string $xmlId
	 * @param string $toCustomField
	 */
	public function __construct($target, $xmlId = "", $toCustomField = "")
	{
		parent::__construct($xmlId);
		$this->targetData = $target;
		$this->toCustomField = $toCustomField;
	}

	/**
	 * @param string[] $values
	 *
	 * @return static
	 */
	public static function createMultiple(array $values)
	{
		$object = new static(null);
		$object->setValues($values);

		return $object;
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $tragetData
	 */
	public function setTargetData($tragetData)
	{
		$this->targetData = $tragetData;
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData
	 */
	public function getTargetData()
	{
		return $this->targetData;
	}

	/**
	 * @param string $toCustomField
	 */
	public function setToCustomField($toCustomField)
	{
		$this->toCustomField = $toCustomField;
	}

	/**
	 * @return string
	 */
	public function getToCustomField()
	{
		return $this->toCustomField;
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId $id
	 */
	public function setId(RecordId $id)
	{
		$this->id = $id;
	}

	/**
	 * @param \Intervolga\Migrato\Data\RecordId[] $ids
	 */
	public function setIds(array $ids)
	{
		$this->id = $ids;
	}

	/**
	 * @return \Intervolga\Migrato\Data\RecordId
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function getIds()
	{
		if($this->isMultiple())
		{
			$ids = array();

			/**
			 * @var \Intervolga\Migrato\Data\RecordId $id
			 */
			foreach($this->id as $id)
			{
				$ids[] = $id->getValue();
			}
			return $ids;
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.USE_GET_ID'));
		}
	}

	/**
	 * @return \Intervolga\Migrato\Data\RecordId[]
	 * @throws \Exception
	 */
	public function findIds()
	{
		if ($this->targetData)
		{
			if ($this->isMultiple())
			{
				$ids = array();
				foreach ($this->getValues() as $xmlId)
				{
					$ids[] = $this->targetData->findRecord($xmlId);
				}
				return $ids;
			}
			else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.USE_FIND_ID'));
			}
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.SET_TARGET_DATA'));
		}
	}

	/**
	 * @return \Intervolga\Migrato\Data\RecordId
	 * @throws \Exception
	 */
	public function findId()
	{
		if ($this->targetData)
		{
			if (!$this->isMultiple())
			{
				return $this->targetData->findRecord($this->getValue());
			}
			else
			{
				throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.USE_FIND_IDS'));
			}
		}
		else
		{
			throw new \Exception(Loc::getMessage('INTERVOLGA_MIGRATO.SET_TARGET_DATA'));
		}
	}
}