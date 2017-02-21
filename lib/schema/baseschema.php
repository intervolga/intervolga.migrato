<?namespace Intervolga\Migrato\Schema;

use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Module\Iblock\Iblock;

abstract class BaseSchema
{
	protected static $instances = array();
	protected $ufObjectName = "";

	/**
	 * @return static
	 */
	public static function getInstance()
	{
		if (!static::$instances[get_called_class()])
		{
			static::$instances[get_called_class()] = new static();
		}

		return static::$instances[get_called_class()];
	}

	/**
	 * @return Field[]
	 */
	public function getFields()
	{
		return $this->getUserFields();
	}

	/**
	 * @return Field[]
	 */
	protected function getUserFields()
	{
		$result = array();
		if ($this->ufObjectName)
		{
			global $USER_FIELD_MANAGER;
			$userFields = $USER_FIELD_MANAGER->getUserFields($this->ufObjectName, 0, "ru");
			foreach ($userFields as $userField)
			{
				$result[] = Field::makeForUserField($this, $userField);
			}
		}

		return $result;
	}

	/**
	 * @return Link[]
	 */
	public static function getDependencies()
	{
		return array(
			"SETTINGS.IBLOCK_ID" => new Link(Iblock::getInstance()),
			//TODO "SETTINGS.HLBLOCK_ID" => new Link(Hlblock::getInstance()),
			//TODO "SETTINGS.HLFIELD_ID" => new Link(Hlfield::getInstance()),
		);
	}
}