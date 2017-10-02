<?php
namespace Intervolga\Migrato\Data\Module\Highloadblock;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Highloadblock\HighloadBlockRightsTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\TaskTable;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Module\Main\Group;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Console\Application;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;
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
		$this->setFilesSubdir('/highloadblock/permissions/');
		$this->setDependencies(array(
			'HL_ID' => new Link(HighloadBlock::getInstance()),
			'GROUP_ID' => new Link(Group::getInstance()),
		));
	}

	/**
	 * Получает список всех прав на доступ к Highload-блоку, параллельно проверяя их на возможность экспорта
	 * @param array $filter
	 * @return array
	 */
	public function getList(array $filter = array())
	{
		$hlBlocks = HighloadBlockTable::getList();
		$result = array();
		while ($hlBlock = $hlBlocks->fetch()) {
			$hlBlockId = $hlBlock['ID'];
			$rightsCursor = HighloadBlockRightsTable::getList(array(
				'filter' => array(
					'HL_ID' => $hlBlockId
				)
			));
			while ($arPermission = $rightsCursor->fetch()) {
				if (mb_strlen($arPermission['ACCESS_CODE'])) {
					$accessCode =  $arPermission['ACCESS_CODE'];
					if ($accessCode[0] == 'G') {
						$groupId = intval(mb_substr($accessCode, 1));

						$id = $this->createId(array(
							"IBLOCK_ID" => $hlBlockId,
							"GROUP_ID" => $groupId,
						));
						$record = new Record($this);
						$record->setXmlId($this->getXmlId($id));
						$record->setId($id);

						// Получаем по TASK_ID
						$taskId = $arPermission['TASK_ID'];
						$taskCursor = TaskTable::getList(array(
							'filter' => array('ID' => $taskId)
						));
						if ($arTask = $taskCursor->fetch()) {
							$record->addFieldsRaw(array(
								"PERMISSION" => $arTask['NAME'],
							));

							$dependency = clone $this->getDependency("GROUP_ID");
							$dependency->setValue(
								Group::getInstance()->getXmlId(RecordId::createNumericId($groupId))
							);
							$record->setDependency("GROUP_ID", $dependency);

							$dependency = clone $this->getDependency("HL_ID");
							$dependency->setValue(
								HighloadBlock::getInstance()->getXmlId(RecordId::createNumericId($hlBlockId))
							);
							$record->setDependency("HL_ID", $dependency);

							$result[] = $record;
						} else {
							Application::getInstance()->renderWarning("У Highload-блока $hlBlockId не найдено значение уровня доступа с ID $taskId (таблица b_task_table). Возможно произошел сбой БД.");
						}
					} else {
						Application::getInstance()->renderWarning("У Highload-блока $hlBlockId право доступа сформулировано, как $accessCode. Это значит, что оно привязано к пользователю или к группе соц. сети. Данный модуль не поддерживает такие привязки. Данное правило не будет экспортировано.");
					}
				} else {
					Application::getInstance()->renderWarning("У Highload-блока $hlBlockId есть пустое разрешение на доступ. Возможно произошел сбой БД.");
				}
			}
		}

		return $result;
	}


	/**
	 * Возвращает XML_ID для записи
	 * @param RecordId $id
	 * @return string
	 */
	public function getXmlId($id)
	{
		$array = $id->getValue();
		$hlData = HighloadBlock::getInstance();
		$groupData = Group::getInstance();
		$iblockXmlId = $hlData->getXmlId($hlData->createId($array['HL_ID']));
		$groupXmlId = $groupData->getXmlId($groupData->createId($array['GROUP_ID']));
		$md5 = md5(serialize(array(
			$iblockXmlId,
			$groupXmlId,
		)));
		return BaseXmlIdProvider::formatXmlId($md5);
	}

	/**
	 * Создает ID для записи
	 * @param mixed $id
	 * @return RecordId
	 */
	public function createId($id)
	{
		return RecordId::createComplexId(array(
				"HL_ID" => intval($id['HL_ID']),
				"GROUP_ID" => intval($id['GROUP_ID']),
			)
		);
	}

	// TODO: Пофиксить все ошибки ниже

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