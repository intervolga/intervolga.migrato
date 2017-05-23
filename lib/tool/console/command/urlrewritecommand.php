<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UrlRewriter;
use Intervolga\Migrato\Tool\Console\Logger;

Loc::loadMessages(__FILE__);

class UrlRewriteCommand extends BaseCommand
{
	protected function configure()
	{
		$this->setName('urlrewrite');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.URLREWRITE_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->urlRewrite();
	}

	protected function urlRewrite()
	{
		$res = UrlRewriter::reindexAll();
		$this->logger->registerFinal(
			Loc::getMessage(
				'INTERVOLGA_MIGRATO.URLREWRITE_UPDATED',
				array(
					'#COUNT#' => $res,
				)
			),
			Logger::TYPE_OK
		);
	}
}