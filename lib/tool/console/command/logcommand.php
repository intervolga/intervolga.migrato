<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\TableHelper;
use Intervolga\Migrato\Tool\Orm\LogTable;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

class LogCommand extends BaseCommand
{
	protected $clearLogs = false;
	/**
	 * @var \Intervolga\Migrato\Tool\Console\TableHelper table
	 */
	protected $table = null;

	public function configure()
	{
		$this->setName('log');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.LOG_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->table = new TableHelper();
		$this->initTable();
		$getList = $this->getList();
		while ($log = $getList->fetch())
		{
			$this->addLogToTable($log);
		}
		$this->logger->add($this->table->getOutput());
	}

	protected function initTable()
	{
		$headers = array(
			'TIME' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_TIME'),
			'DATA' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_DATA'),
			'XML_ID' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_XML_ID'),
			'ID' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_ID'),
			'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_OPERATION'),
			'RESULT' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_RESULT'),
			'COMMENT' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_COMMENT'),
		);
		if ($this->input->getOption('fails'))
		{
			unset($headers['RESULT']);
		}
		$this->table->addHeader($headers);
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

	protected function addLogToTable(array $log)
	{
		if($this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE && $log['COMMENT'])
		{
			$comment = explode(PHP_EOL . PHP_EOL, $log['COMMENT']);
			$log['COMMENT'] = $comment[0];
		}

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
		$this->table->addRow($row);
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