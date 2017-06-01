<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Symfony\Component\Console\Helper\Table;

Loc::loadMessages(__FILE__);

class LogCommand extends BaseCommand
{
	protected $clearLogs = false;

	public function configure()
	{
		$this->setName('log');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.LOG_DESCRIPTION'));
	}

	public function executeInner()
	{
		$table = $this->getTable();
		$getList = $this->getList();
		while ($log = $getList->fetch())
		{
			$this->addLogToTable($log, $table);
		}

		$table->render();
	}

	protected function getTable()
	{
		$table = new Table($this->output);
		$headers = array(
			'TIME',
			'DATA',
			'XML_ID',
			'ID',
			'OPERATION',
			'RESULT',
			'COMMENT'
		);
		if ($this->input->getOption('fails'))
		{
			$headers = array_diff($headers, array('RESULT'));
		}
		$table->setHeaders($headers);

		return $table;
	}

	protected function getList()
	{
		$filter = array();
		if ($this->input->getOption('fails'))
		{
			$filter['=RESULT'] = 'fail';
		}
		return LogTable::getList(array('filter' => $filter));
	}

	protected function addLogToTable(array $log, Table $table)
	{
		$row = array(
			'TIME' => $log['TIMESTAMP_X'],
			'DATA' => $log['MODULE_NAME'] . ':' . $log['ENTITY_NAME'],
			'XML_ID' => $log['DATA_XML_ID'],
			'ID' => $this->getId($log),
			'OPERATION' => $log['OPERATION'],
			'RESULT' => $log['RESULT'],
			'COMMENT' => $log['COMMENT'],
		);
		if ($this->input->getOption('fails'))
		{
			unset($row['RESULT']);
		}
		$table->addRow($row);
	}

	/**
	 * @param array $log
	 *
	 * @return string
	 */
	protected function getId(array $log)
	{
		if ($log['DATA_ID_COMPLEX'])
		{
			$tmp = array();
			foreach ($log['DATA_ID_COMPLEX'] as $key => $value)
			{
				$tmp[] = $key . '=' . $value;
			}
			$id = implode(';', $tmp);
		}
		else
		{
			$id = trim($log['DATA_ID_NUM'] . ' ' . $log['DATA_ID_STR']);
		}
		return $id;
	}
}