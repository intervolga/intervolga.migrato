<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\OptionFileViewXml;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

class ImportOptionCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('importoptions');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.IMPORT_OPTIONS_DESCRIPTION'));
	}

	public function executeInner()
	{
		$directory = new Directory(INTERVOLGA_MIGRATO_DIRECTORY . "options/");
		if ($directory->isExists())
		{
			$total = array();
			foreach ($directory->getChildren() as $dirOrFile)
			{
				if ($dirOrFile instanceof File)
				{
					$file = $dirOrFile;
					$module = str_replace(".xml", "", $file->getName());
					$total[$module] = 0;
					$options = OptionFileViewXml::readFromFileSystem($file->getPath());
					foreach ($options as $option)
					{
						if (Config::getInstance()->isOptionIncluded($option['NAME']))
						{
							Option::set($module, $option['NAME'], $option['VALUE'], $option['SITE_ID']);
							$total[$module]++;
							if ($option['SITE_ID'])
							{
								$id = $option['SITE_ID'] . ':' . $option['NAME'];
							}
							else
							{
								$id = $option['NAME'];
							}
							$this->detailSummaryStart();
							$this->report(
								Loc::getMessage(
									'INTERVOLGA_MIGRATO.STATISTIC_ONE_RECORD',
									array(
										'#MODULE#' => $this->getModuleMessage($module),
										'#ENTITY#' => Loc::getMessage('INTERVOLGA_MIGRATO.ENTITY_NAME_OPTIONS'),
										'#OPERATION#' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_IMPORT_OPTIONS'),
										'#IDS#' => $id,
									)
								),
								static::REPORT_TYPE_OK,
								$count,
								OutputInterface::VERBOSITY_VERY_VERBOSE
							);
						}
					}
				}
			}

			$this->shortSummaryStart();
			foreach ($total as $module => $count)
			{
				$this->report(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.STATISTICS_RECORD',
						array(
							'#MODULE#' => $this->getModuleMessage($module),
							'#ENTITY#' => Loc::getMessage('INTERVOLGA_MIGRATO.ENTITY_NAME_OPTIONS'),
							'#OPERATION#' => Loc::getMessage('INTERVOLGA_MIGRATO.OPERATION_IMPORT_OPTIONS'),
							'#COUNT#' => $count,
						)
					),
					static::REPORT_TYPE_OK,
					$count,
					OutputInterface::VERBOSITY_VERBOSE
				);
			}
		}
	}
}