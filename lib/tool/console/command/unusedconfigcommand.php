<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\DataList;

Loc::loadMessages(__FILE__);

class UnusedConfigCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('unused');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.UNUSED_CONFIG_DESCRIPTION'));
	}

	public function executeInner()
	{
		$configDataClassesString = $this->getConfigDataCodes();
		$dataClasses = DataList::getAll();
		foreach ($dataClasses as $conf)
		{
			if (!in_array($conf->getEntityName(), $configDataClassesString[$conf->getModule()]))
			{
				$this->logger->add(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.DATA_NOT_USED',
						array(
							'#MODULE#' => $this->logger->getModuleNameLoc($conf->getModule()),
							'#ENTITY#' => $this->logger->getEntityNameLoc($conf->getModule(), $conf->getEntityName()),
						)
					),
					0,
					Logger::TYPE_INFO
				);
			}
		}
	}

	/**
	 * @return array
	 */
	protected function getConfigDataCodes()
	{
		$configDataClasses = Config::getInstance()->getDataClasses();
		$configDataClassesString = array();
		foreach ($configDataClasses as $conf)
		{
			$configDataClassesString[$conf->getModule()][] = $conf->getEntityName();
		}

		return $configDataClassesString;
	}
}