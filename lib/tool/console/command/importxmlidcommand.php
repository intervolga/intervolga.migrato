<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SystemException;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Intervolga\Migrato\Tool\DataList;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

class ImportXmlIdCommand extends ImportDataCommand
{
	protected function configure()
	{
		$this->setName('importxmlid');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_XMLID_DESCRIPTION'));
		$this->addArgument(
			'module',
			InputArgument::REQUIRED,
			Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_XMLID_MODULE')
		);
		$this->addArgument(
			'data',
			InputArgument::REQUIRED,
			Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_XMLID_DATA')
		);
		$this->addArgument(
			'xmlid',
			InputArgument::REQUIRED,
			Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_XMLID_XMLID')
		);

		$this->addOption(
			'quick',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage('INTERVOLGA_MIGRATO.NO_AFTER_CLEAR_DESCRIPTION')
		);
	}

	public function executeInner()
	{
		$dataClass = $this->findDataClass();
		$record = $this->readRecordFile($dataClass);

		if ($recordId = $record->getData()->findRecord($record->getXmlId()))
		{
			$this->updateWithLog($recordId, $record);
		}
		else
		{
			$this->createWithLog($record);
		}

		if (!$this->input->getOption('quick'))
		{
			$this->runSubcommand('clearcache');
			$this->runSubcommand('urlrewrite');
			$this->runSubcommand('reindex');
		}
	}

	/**
	 * @return \Intervolga\Migrato\Data\BaseData
	 * @throws SystemException
	 */
	protected function findDataClass()
	{
		$module = $this->input->getArgument('module');
		$data = $this->input->getArgument('data');

		$dataClasses = DataList::getAll();
		foreach ($dataClasses as $dataClass)
		{
			if ($dataClass->getModule() == $module
				&& $dataClass->getEntityName() == $data)
			{
				return $dataClass;
			}
		}

		throw new SystemException(Loc::getMessage('INTERVOLGA_MIGRATO.DATA_CLASS_NOT_FOUND'));
	}

	/**
	 * @param \Intervolga\Migrato\Data\BaseData $dataClass
	 * @return \Intervolga\Migrato\Data\Record
	 * @throws SystemException
	 */
	protected function readRecordFile(\Intervolga\Migrato\Data\BaseData $dataClass)
	{
		$xmlId = $this->input->getArgument('xmlid');
		$path = INTERVOLGA_MIGRATO_DIRECTORY . $dataClass->getModule() . $dataClass->getFilesSubdir() . $dataClass->getEntityName() . '/';
		$filePath = $path . DataFileViewXml::FILE_PREFIX . $xmlId . "." . DataFileViewXml::FILE_EXT;
		$file = new File($filePath);
		if ($file->isExists())
		{
			$record = DataFileViewXml::parseFile($file);
			$this->afterRead($record, $dataClass);

			return $record;
		}
		throw new SystemException(Loc::getMessage('INTERVOLGA_MIGRATO.RECORD_FILE_NOT_FOUND', array('#XML_ID#' => $xmlId)));
	}
}