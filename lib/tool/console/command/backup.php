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
		//$httpClient->setHeader('Content-Type', 'application/json', true);
		$httpClient->setAuthorization($USER->GetLogin(), '91sede'); // login and password


		$site = array();
		$defSite = \Bitrix\Main\SiteTable::getList(array('filter' => array('DEF' => 'Y')));
		if  ($arSite = $defSite->fetch())
		{
			$site = $arSite;
		}

		$sessid = explode("=", bitrix_sessid_get())[1];
		$postData = json_encode(array(
			"lang" => 'ru',
			"process" => 'Y',
			"action" => 'start',
			"dump_bucket_id" => 0, // Размещение резервной копии
			//"dump_all" => "Y",
			"dump_max_exec_time" => 20,
			"dump_max_exec_time_sleep" => 1,
			"dump_archive_size_limit" => 100,
			"dump_integrity_check" => 'Y',
			"dump_base" => 'Y',
			"dump_base_skip_stat" => 'Y',
			"dump_base_skip_search" => 'Y',
			"dump_base_skip_log" => 'Y',
			"sessid" => $sessid
		));


		$response = $httpClient->post("http://" . $site['SERVER_NAME'] . "/bitrix/admin/dump.php", $postData);

	}
}