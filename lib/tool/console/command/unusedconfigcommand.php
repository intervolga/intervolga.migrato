<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Config;

Loc::loadMessages(__FILE__);

class UnusedConfigCommand extends BaseCommand
{
	public function executeInner()
	{
		$configDataClassesString = $this->getConfigDataCodes();
		$allConfigDataClasses = Config::getInstance()->getAllDateClasses();
		foreach ($allConfigDataClasses as $conf)
		{
			if (!in_array($conf->getEntityName(), $configDataClassesString[$conf->getModule()]))
			{
				$this->report(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.DATA_NOT_USED',
						array(
							'#MODULE#' => $this->getModuleMessage($conf->getModule()),
							'#ENTITY#' => $this->getEntityMessage($conf->getEntityName()),
						)
					),
					static::REPORT_TYPE_INFO
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

	protected function configure()
	{
		$this->setName('unused');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.UNUSED_CONFIG_DESCRIPTION'));
	}
}