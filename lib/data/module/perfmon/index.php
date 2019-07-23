<?php
namespace Intervolga\Migrato\Data\Module\Perfmon;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\ExceptionText;

Loc::loadMessages(__FILE__);

class Index extends BaseData
{
	const XML_ID_SEPARATOR = '.';

	public static function getMinVersion()
	{
		return "14.0";
	}

	protected function configure()
	{
		$this->setVirtualXmlId(true);
		Loader::includeModule('perfmon');
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.PERFMON_INDEX'));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CPerfomanceIndexComplete::getList($filter);
		while ($index = $getList->fetch())
		{
			$record = new Record($this);
			$id = $this->createId($index['ID']);
			$record->setId($id);
			$record->setXmlId($this->getXmlId($id));
			$record->addFieldsRaw(array(
				'INDEX_NAME' => $index['INDEX_NAME'],
				'TABLE_NAME' => $index['TABLE_NAME'],
				'COLUMN_NAMES' => $index['COLUMN_NAMES'],
				'BANNED' => $index['BANNED'],
			));
			$result[] = $record;
		}
		return $result;
	}

	public function getXmlId($id)
	{
		$arFilter = array('ID' => $id->getValue());
		$getList = \CPerfomanceIndexComplete::getList($arFilter);
		if ($index = $getList->fetch())
		{
			$table = str_replace('`', '', $index['TABLE_NAME']);
			$columns = str_replace('`', '', $index['COLUMN_NAMES']);
			return $table . static::XML_ID_SEPARATOR . md5($columns . $index['BANNED']);
		}
		return '';
	}

	public function update(Record $record)
	{
		if (!$this->isEqual($record, $record->getId()->getValue()))
		{
			$this->deleteInner($record->getId());
			$this->createInner($record);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param int $id
	 * @return bool
	 */
	protected function isEqual(Record $record, $id)
	{
		$currentFields = \CPerfomanceIndexComplete::getList(['ID' => $id])->fetch();
		$fields = $record->getFieldsRaw();

		return $fields['BANNED'] == $currentFields['BANNED']
		&& $fields['INDEX_NAME'] == $currentFields['INDEX_NAME']
		&& $fields['TABLE_NAME'] == $currentFields['TABLE_NAME']
		&& $fields['COLUMN_NAMES'] == $currentFields['COLUMN_NAMES'];
	}

	/**
	 * @param Record $record
	 * @return array
	 */
	protected function recordToArray(Record $record)
	{
		$array = array(
			'INDEX_NAME' => $record->getFieldRaw('INDEX_NAME'),
			'TABLE_NAME' => $record->getFieldRaw('TABLE_NAME'),
			'COLUMN_NAMES' => $record->getFieldRaw('COLUMN_NAMES'),
			'BANNED' => $record->getFieldRaw('BANNED'),
		);
		return $array;
	}

	protected function createInner(Record $record)
	{
		global $DB;
		$data = $this->recordToArray($record);

		$DB->db_Error = '';
		$id = \CPerfomanceIndexComplete::add($data);
		if ($id)
		{
			if ($data['BANNED'] == 'Y')
			{
				return $this->createId($id);
			}
			else
			{
				$table = new \CPerfomanceTable();
				if ($table->isExists($data['TABLE_NAME']))
				{
					$query = $table->getCreateIndexDDL($data['TABLE_NAME'], $data['INDEX_NAME'], [$data['COLUMN_NAMES']]);
					$indexCreateResult = $DB->query($query);
					if ($indexCreateResult)
					{
						return $this->createId($id);
					}
					else
					{
						\CPerfomanceIndexComplete::delete($id);
					}
				}
				else
				{
					\CPerfomanceIndexComplete::delete($id);
					throw new \Exception(ExceptionText::getFromString(Loc::getMessage('INTERVOLGA_MIGRATO.TABLE_DOESNT_EXIST', ['#TABLE#' => $data['TABLE_NAME']])));
				}
			}
		}
		throw new \Exception(ExceptionText::getFromString($DB->db_Error));
	}

	protected function deleteInner(RecordId $id)
	{
		$data = \CPerfomanceIndexComplete::getList(['ID' => $id->getValue()])->fetch();
		if ($data['INDEX_NAME'])
		{
			$table = new \CPerfomanceTable();
			if ($table->isExists($data['TABLE_NAME']))
			{
				$indexes = $table->getIndexes($data['TABLE_NAME']);
				if ($indexes[$data['INDEX_NAME']])
				{
					global $DB;
					$query = 'ALTER TABLE ' . $data['TABLE_NAME'] . ' DROP INDEX ' . $data['INDEX_NAME'];
					$result = $DB->query($query, true);
					if (!$result)
					{
						throw new \Exception($query . PHP_EOL . ExceptionText::getFromString($DB->db_Error));
					}
				}
			}
		}

		\CPerfomanceIndexComplete::delete($data['ID']);
	}
}