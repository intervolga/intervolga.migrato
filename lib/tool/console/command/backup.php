<?
namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Intervolga\Migrato\Data\Module;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

class Backup extends BaseCommand
{
	protected $httpClient;
	protected $site;
	protected $login = 'admin';
	protected $password = '123456';
	protected $exec_time = 120;
	protected $exec_time_sleep = 5;
	protected $cookiesFile = __DIR__.'/cookies.dat';

	private function getCookiesInfo()
	{
		if (!is_file($this->cookiesFile))
		{
			return [];
		}
		$content = file_get_contents($this->cookiesFile);
		$sessionInfo = json_decode($content, true);
		return $sessionInfo;
	}

	private function setCookiesInfo($info)
	{
		file_put_contents($this->cookiesFile, json_encode($info));
	}

	private function delCookiesInfo()
	{
		if (is_file($this->cookiesFile))
		{
			unlink($this->cookiesFile);
		}
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

	public function executeInner()
	{
		$this->httpClient = new HttpClient();
		$this->httpClient->setCookies($this->getCookiesInfo());
		$this->site = $this->getSiteInfo();
		// static::createBackup();



		$html = $this->get('/local/modules/intervolga.migrato/tools/check_session.php');
		$cookies = $this->httpClient->getCookies()->toArray();
		con3([
			'first',
			'result'=>$html,
			'cookies'=>$cookies,
			'type of cookies' => gettype($cookies),
		]);

		$cookies2 = $this->httpClient->getCookies()->toArray();
		con3([
			'second',
			'result'=>$html,
			'cookies'=>$cookies,
			'type of cookies' => gettype($cookies),
		]);
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
		$fullUrl = 'http://'.$this->site['SERVER_NAME'].$urlPath;
		$this->httpClient->setHeader('Authorization', 'Basic '.base64_encode($this->login.':'.$this->password), true);
		$cookies = $this->getCookiesInfo();
		if (!$cookie)
		{
			// $this->httpClient->setCookies($cookies);
		}
		if ($isPost)
		{
			$html = $this->httpClient->post($fullUrl, $postData);
		} else {
			$html = $this->httpClient->get($fullUrl);
		}
		$cookies = $this->httpClient->getCookies()->toArray();
		$this->setCookiesInfo($cookies);

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
		// $postData = $this->prepareParams();
		// $postData['sessid'] = bitrix_sessid();
		// $response = $this->post($startUrl, $postData);
		// con3($response);
		//
		// preg_match ('/AjaxSend\([\'\"]([^\'\"]+)[\'\"]\)/ui', $response, $parts);
		// $nextStepUrl = $startUrl . $parts[1];
		$nextStepUrl = $startUrl . '?process=Y&sessid=d2652ed05a704e8fabf66addd57eebb7';
		con3($nextStepUrl);
		$response = $this->get($nextStepUrl);
		con3($response);
	}
}
