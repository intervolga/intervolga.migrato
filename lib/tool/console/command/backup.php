<?
namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Intervolga\Migrato\Data\Module;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

require $_SERVER['DOCUMENT_ROOT'].'/local/modules/intervolga.migrato/tools/simplesign.php';

Loc::loadMessages(__FILE__);

class Backup extends BaseCommand
{
	protected $httpClient;
	protected $cookies = false;
	protected $site;
	protected $exec_time = 20;
	protected $exec_time_sleep = 5;

	protected function init()
	{
		$this->httpClient = new HttpClient();
		$this->site = $this->getSiteInfo();
	}

	protected function configure()
	{
		$this->setName('backup');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.BACKUP_DESCRIPTION'));
		$this->addOption(
			'nokernel',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage("INTERVOLGA_MIGRATO.BACKUP_ARGS_NOKERNEL")
		);
		$this->addOption(
			'nodatabase',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage("INTERVOLGA_MIGRATO.BACKUP_ARGS_NODATABASE")
		);
		$this->addOption(
			'nopublic',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage("INTERVOLGA_MIGRATO.BACKUP_ARGS_NOPUBLIC")
		);
		$this->addOption(
			'noupload',
			null,
			InputOption::VALUE_NONE,
			Loc::getMessage("INTERVOLGA_MIGRATO.BACKUP_ARGS_NOUPLOAD")
		);
		$this->addArgument(
			'size',
			InputOption::VALUE_OPTIONAL,
			Loc::getMessage("INTERVOLGA_MIGRATO.BACKUP_ARGS_FILESIZE")
		);
	}

	private function createAdminSession()
	{
		$request = [
			'action' => 'create_admin_sessid',
		];
		\SimpleSign::getInstance()->sign($request);

		$json = $this->post('/local/modules/intervolga.migrato/tools/create_session.php', $request);
		$values = json_decode($json, true);
		return $values['sessid'] ?? '';
	}

	public function executeInner()
	{
		$this->init();
 		$this->createBackup();
	}

	protected function getSiteInfo()
	{
		$site = array();
		$defSite = \Bitrix\Main\SiteTable::getList(array('filter' => array('DEF' => 'Y')));
		if  ($arSite = $defSite->fetch())
		{
			$site = $arSite;
			if (!$site['SERVER_NAME'])
			{
				$site['SERVER_NAME'] = basename($_SERVER['DOCUMENT_ROOT']);
			}
		}
		return $site;
	}

	protected function makeRequest($urlPath, $isPost=false, $postData=[])
	{
		$protocol = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? "https" : "http";
		$fullUrl = $protocol.'://'.$this->site['SERVER_NAME'].$urlPath;
		if ($this->cookies !== false)
		{
			$this->httpClient->setCookies($this->cookies);
		}
		if ($isPost)
		{
			$html = $this->httpClient->post($fullUrl, $postData);
		} else {
			$html = $this->httpClient->get($fullUrl);
		}
		if ($this->cookies === false)
		{
			$this->cookies = $this->httpClient->getCookies()->toArray();
		}

		return $html;
	}

	protected function get($urlPath)
	{
		return $this->makeRequest($urlPath);
	}

	protected function post($urlPath, $postData=[])
	{
		return $this->makeRequest($urlPath, true, $postData);
	}

	protected function prepareParams()
	{
		$params = $this->input->getArgument('size');
		$size = count($params) ? $params[0] : 100;

		$postData = array(
			"lang" => "ru",
			"process" => "Y",
			"action" => "start",
			"dump_bucket_id" => 0,
			"dump_max_exec_time" => $this->exec_time,
			"dump_max_exec_time_sleep" => $this->exec_time_sleep,
			"dump_archive_size_limit" => $size,
			"max_file_size" => 0,
		);
		if (!$this->input->getOption('nodatabase'))
		{
			$postData["dump_base"] = "Y";
		}
		if (!$this->input->getOption('nokernel'))
		{
			$postData["dump_file_kernel"] = "Y";
		}
		if (!$this->input->getOption('nopublic'))
		{
			$postData["dump_file_public"] = "Y";
		}
		if ($this->input->getOption('noupload'))
		{
			$postData["skip_mask"] = "Y";
			$postData["arMask"] = ["/upload"];
		}

		return $postData;
	}

	protected function createBackup()
	{
		$startUrl = "/bitrix/admin/dump.php";
		$nextStepUrl = false;
		$postData = $this->prepareParams();
		$postData['sessid'] = $this->createAdminSession();
		$response = $this->post($startUrl, $postData);
		while (true)
		{
			$informMessage = '';
			preg_match('/([0-9]{1,3})%/ui', $response, $parts);
			if (!empty($parts[1]))
			{
				$progress = $parts[1];
				$informMessage .= Loc::getMessage('INTERVOLGA_MIGRATO.BACKUP_PROGRESS').sprintf('%3d', $progress).'%   ';
			}
			preg_match('/[0-9:]+:[0-9]{2}/ui', $response, $parts);
			if (!empty($parts[0]))
			{
				$spentTime = $parts[0];
				$informMessage .= Loc::getMessage('INTERVOLGA_MIGRATO.BACKUP_SPENT_TIME').$spentTime.'   ';
			}
			if ($informMessage)
			{
				$this->output->writeln($informMessage);
			}
			preg_match('/AjaxSend\([\'\"]([^\'\"]+)[\'\"]\)/ui', $response, $parts);
			if (empty($parts[0]))
			{
				$this->output->writeln(Loc::getMessage('INTERVOLGA_MIGRATO.BACKUP_100PRC'));
				break;
			}
			if ($nextStepUrl === false) {
				$nextStepUrl = $startUrl . $parts[1];
			}
			$response = $this->get($nextStepUrl);

			sleep($this->exec_time_sleep);
		}
	}
}
