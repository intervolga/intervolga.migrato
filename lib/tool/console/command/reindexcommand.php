<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Logger;

Loc::loadMessages(__FILE__);

class ReIndexCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('reindex');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.REINDEX_DESCRIPTION'));
	}

	public function executeInner()
	{
		if (Loader::includeModule('search'))
		{
			$count = \CSearch::ReIndexAll(true);
			$this->logger->registerFinal(
				Loc::getMessage(
					'INTERVOLGA_MIGRATO.REINDEX_RESULT',
					array(
						'#COUNT#' => $count,
					)
				),
				Logger::TYPE_OK
			);
		}
		else
		{
			$this->logger->registerFinal(
				Loc::getMessage('INTERVOLGA_MIGRATO.NO_SEARCH_MODULE'),
				Logger::TYPE_INFO
			);
		}
	}
}