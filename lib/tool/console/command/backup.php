<?
namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Intervolga\Migrato\Data\Module;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

Loc::loadMessages(__FILE__);

class Backup extends BaseCommand
{
	protected $httpClient;
	protected $cookies = false;
	protected $site;
	protected $login;
	protected $password;
	protected $exec_time = 20;
	protected $exec_time_sleep = 5;

	protected function init()
	{
		$this->login = $this->ask('Login: ', 'admin');
		$this->password = $this->ask('Password: ', '123456', true);
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

	protected function ask($questionText, $defaultValue='', $hidden=false)
	{
		$helper = $this->getHelper('question');
		$question = new Question($questionText, $defaultValue);
		$question->setHidden($hidden);
		$answer = $helper->ask($this->input, $this->output, $question);
		return $answer;
	}

	private function test()
	{
		for($loop=0;$loop<4;$loop++) {
			$html = $this->get('/local/modules/intervolga.migrato/tools/check_session.php');
			con3([
				$loop,
				'result'=>$html,
				'cookies'=>$cookies,
				'type of cookies' => gettype($cookies),
			]);
			sleep (35);
		}
	}

	public function executeInner()
	{
		$this->init();
		// $this->test();
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
		$fullUrl = 'http://'.$this->site['SERVER_NAME'].$urlPath;
		$this->httpClient->setHeader('Authorization', 'Basic '.base64_encode($this->login.':'.$this->password), true);
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
		$postData['sessid'] = bitrix_sessid();
		$response = $this->post($startUrl, $postData);

		while (true)
		{
			con3('loop start');
			con3($response);

			preg_match('/[0-9]{1-3}%/ui', $response, $parts);
			if (!empty($parts[0]))
			{
				$progress = $parts[0];
				echo 'Прогресс: '.$progress.'   ';
			}
			preg_match('/[0-9:]+:[0-9]{2}/ui', $response, $parts);
			if (!empty($parts[0]))
			{
				$spentTime = $parts[0];
				echo 'Прошло времени: '.$spentTime.'';
			}
			echo "\n";
			preg_match('/AjaxSend\([\'\"]([^\'\"]+)[\'\"]\)/ui', $response, $parts);
			if (empty($parts[0]))
			{
				echo "Завершено.\n";
				break;
			}
			if ($nextStepUrl === false) {
				$nextStepUrl = $startUrl . $parts[1];
			}
			con3($nextStepUrl);
			flush();
			$response = $this->get($nextStepUrl);

			sleep($this->exec_time_sleep);
		}
	}
}
