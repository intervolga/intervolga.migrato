<? namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;

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
		$allConfigDataClasses = Config::getInstance()->getAllDataClasses();

		$event = new \Bitrix\Main\Event("intervolga.migrato", "OnMigratoDataBuildList");
		$event->send();
		if ($event->getResults())
		{
			foreach($event->getResults() as $evenResult)
			{
				try
				{
					if(!is_array($evenResult->getParameters()))
						throw new \Exception('INTERVOLGA_MIGRATO.EVENT_ERROR.BUILD_LIST.NOT_ARRAY');
					foreach($evenResult->getParameters() as $parameter)
						if(!$parameter instanceof \Intervolga\Migrato\Data\BaseData)
							throw new \Exception("INTERVOLGA_MIGRATO.EVENT_ERROR.BUILD_LIST.NOT_BASE_DATA");
				} catch(\Exception $exp) {
					$this->logger->add(
						Loc::getMessage(
							$exp->getMessage(), array('#EVENT#' => 'OnMigratoDataBuildList')
						),
						0,
						Logger::TYPE_FAIL
					);
				}
				$allConfigDataClasses = array_merge($allConfigDataClasses, $evenResult->getParameters());
			}
		}

		foreach ($allConfigDataClasses as $conf)
		{
			if (!in_array($conf->getEntityName(), $configDataClassesString[$conf->getModule()]))
			{
				$this->logger->add(
					Loc::getMessage(
						'INTERVOLGA_MIGRATO.DATA_NOT_USED',
						array(
							'#MODULE#' => $this->logger->getModuleMessage($conf->getModule()),
							'#ENTITY#' => $this->logger->getEntityMessage($conf->getEntityName()),
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