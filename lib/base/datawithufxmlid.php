<?namespace Intervolga\Migrato\Base;

use Intervolga\Migrato\Tool\XmlIdUserField;

abstract class DataWithUfXmlId extends Data
{
	public static function isXmlIdFieldExists()
	{
		return XmlIdUserField::isFieldExists(static::getModule(), static::getEntityName());
	}

	public static function createXmlIdField()
	{
		return XmlIdUserField::createField(static::getModule(), static::getEntityName());
	}

	public static function setXmlId($id, $xmlId)
	{
		return XmlIdUserField::setXmlId(static::getModule(), static::getEntityName(), $id, $xmlId);
	}

	public static function getXmlId($id)
	{
		return XmlIdUserField::getXmlId(static::getModule(), static::getEntityName(), $id);
	}
}