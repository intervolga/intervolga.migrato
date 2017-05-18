<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ReIndexCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('reindex');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.REINDEX_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->reindex();
	}

	protected function reindex()
	{
		if (Loader::includeModule('search'))
		{
			$count = \CSearch::ReIndexAll(true);
			$this->customFinalReport = Loc::getMessage(
				'INTERVOLGA_MIGRATO.REINDEX_RESULT',
				array(
					'#COUNT#' => $count,
				)
			);
		}
		else
		{
			$this->customFinalReport = Loc::getMessage('INTERVOLGA_MIGRATO.NO_SEARCH_MODULE');
		}
	}
}