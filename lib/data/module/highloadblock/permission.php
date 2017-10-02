<?php
namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Highloadblock\HighloadBlockRightsTable;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);


/**
 * @class Permission
 * @package Intervolga\Migrato\Data\Module\Highloadblock
 *
 * Класс для миграции прав доступа к Highload-блокам.
 */
class Permission extends BaseData
{
	protected function configure()
	{
		Loader::includeModule('highloadblock');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.HIGHLOADBLOCK_HIGHLOADBLOCK'));
		$this->setFilesSubdir('/type/');
		$this->setDependencies(array(
			'IBLOCK_TYPE_ID' => new Link(Type::getInstance()),
			'SITE' => new Link(Site::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$hlBlocks = HighloadBlockTable::getList();
		$result = array();
		while ($hlBlock = $hlBlocks->fetch())
		{
			$record = new Record($this);
			$id = RecordId::createNumericId($hlBlock["ID"]);
			$xmlId = $this->getXmlId($id);
			$record->setXmlId($xmlId);
			$record->setId($id);
			$record->addFieldsRaw(array(
				"NAME" => $hlBlock["NAME"],
				"TABLE_NAME" => $hlBlock["TABLE_NAME"],
			));
			$this->addMessages($hlBlock, $record);

			$result[] = $record;
		}

		return $result;
	}

	/**
	 * Пытается добавить локалезависимые сообщения о пергру
	 * @param array $hlBlock данные хайлоадблока
	 * @param Record $record запись, в которую записываются данные
	 */
	protected function addMessages($hlBlock, $record)
	{
		$cursor = HighloadBlockLangTable::getList(array(
			'filter' => array('ID' => $hlBlock['ID'])
		));
		$arMessages = array();
		while($arLangSetting = $cursor->fetch()) {
			$arMessages['MESSAGES.' . $arLangSetting['LID']]  = $arLangSetting['NAME'];
		}
		$record->addFieldsRaw($arMessages);
	}

	public function getXmlId($id)
	{
		$record = HighloadBlockTable::getById($id->getValue())->fetch();

		return strtolower($record['TABLE_NAME']);
	}

	/**
	 * Возвращает набор строк ключ-значение для вставки или обновления элемента инфоблока
	 * @param string[] $arFields массив
	 * @return string[] обработанный массив, из которого извлечены изменённые строки
	 */
	public static function getFieldsForCreateOrUpdate($arFields)
	{
		$arResult = array();
		if (count($arFields)) {
			$messagesKeys = "MESSAGES.";
			$messagesKeysLength = mb_strlen($messagesKeys);
			foreach($arFields as $key => $value) {
				if (mb_substr($key, 0, $messagesKeysLength) != $messagesKeys) {
					$arResult[$key] = $value;
				}
			}
		}
		return $arResult;
	}

	/**
	 * Обновляет подписи Highload-блока
	 * @param int $hlBlockId ID блока
	 * @param array $arFields массив полей блока
	 */
	public static function updateMessages($hlBlockId, $arFields)
	{
		if ($hlBlockId > 0) {
			// Удаляем все предыдущие записи. Здесь ID не уникально, поэтому самый простой способ импорта
			// - удалить вообще всё.
			HighloadBlockLangTable::delete($hlBlockId);
			$messagesKeys = "MESSAGES.";
			$messagesKeysLength = mb_strlen($messagesKeys);
			foreach($arFields as $key => $value) {
				if (mb_substr($key, 0, $messagesKeysLength) == $messagesKeys) {
					$lid = mb_substr($key, $messagesKeysLength);
					HighloadBlockLangTable::add(array(
						'ID' => $hlBlockId,
						'LID' => $lid,
						'NAME' => $value
					));
				}
			}
		}
	}

	public function update(Record $record)
	{
		$arFields = $record->getFieldsRaw();
		$arUpdateFields = self::getFieldsForCreateOrUpdate($arFields);
		$hlBlockId = $record->getId()->getValue();
		$result = HighloadBlockTable::update($record->getId()->getValue(), $arUpdateFields);
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
		else
		{
			self::updateMessages($hlBlockId, $arFields);
		}
	}

	protected function createInner(Record $record)
	{
		$arFields = $record->getFieldsRaw();
		$arCreateFields = self::getFieldsForCreateOrUpdate($arFields);
		$result = HighloadBlockTable::add($record->getFieldsRaw($arCreateFields));
		if ($result->isSuccess())
		{
			$id = RecordId::createNumericId($result->getId());
			self::updateMessages($result->getId(), $arFields);
			return $id;
		}
		else
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$result = HighloadBlockTable::delete($id->getValue());
		if (!$result->isSuccess())
		{
			throw new \Exception(ExceptionText::getFromResult($result));
		}
	}
}