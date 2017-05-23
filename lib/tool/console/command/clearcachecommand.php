<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Data\StaticHtmlCache;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ClearCacheCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('clearcache');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.CLEARCACHE_DESCRIPTION'));
	}

	public function executeInner()
	{
		BXClearCache(true);
		$GLOBALS["CACHE_MANAGER"]->CleanAll();
		$GLOBALS["stackCacheManager"]->CleanAll();
		$staticHtmlCache = StaticHtmlCache::getInstance();
		$staticHtmlCache->deleteAll();
	}
}