<?
namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Intervolga\Migrato\Data\Module;

Loc::loadMessages(__FILE__);

class Backup extends BaseCommand
{
	protected function configure()
	{
		$this->setName('backup');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.BACKUP_DESCRIPTION'));
	}

	public function executeInner()
	{
		static::createBackup();
	}

	protected static function createBackup()
	{
		global $USER;
		$USER->Authorize(1);

		$httpClient = new HttpClient();
		$httpClient->setAuthorization($USER->GetLogin(), ''); // login and password

		//$sessid = explode("=", bitrix_sessid_get())[1];
		$postData = array(
			"lang" => 'ru',
			"process" => 'Y',
			"action" => 'start',
			"dump_bucket_id" => 0, // Размещение резервной копии
			"dump_all" => "Y",
			"sessid" => ''
		);


		$response = $httpClient->post("http://gurjev.ivdev.ru/bitrix/admin/dump.php", $postData);




	}
}