<?namespace Intervolga\Migrato\Base;

use Intervolga\Migrato\Tool\XmlIdUserField;

abstract class DataWithUfXmlId extends Data
{
	public function isXmlIdFieldExists()
	{
		return XmlIdUserField::isFieldExists(static::getModule(), static::getEntityName());
	}

	public function createXmlIdField()
	{
		return XmlIdUserField::createField(static::getModule(), static::getEntityName());
	}

	public function setXmlId($id, $xmlId)
	{
		return XmlIdUserField::setXmlId(static::getModule(), static::getEntityName(), $id, $xmlId);
	}

	public function getXmlId($id)
	{
		return XmlIdUserField::getXmlId(static::getModule(), static::getEntityName(), $id);
	}
}