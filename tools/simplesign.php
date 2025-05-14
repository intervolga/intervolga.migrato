<?php

class SimpleSign
{
	public function __construct()
	{
	}
	public function __clone()
	{
	}
	public function __wakeup()
	{
	}

	private static $instance = false;
	public static function getInstance()
	{
		if (!self::$instance)
		{
			self::$instance = new self;
		}
		return self::$instance;
	}

	private $secret = false;

	private function getSecret()
	{
		if (!$this->secret)
		{
			$dir = new \Bitrix\Main\IO\Directory($_SERVER['DOCUMENT_ROOT'].'/upload/');
			$files = $dir->getChildren();
			$contentDir = [];
			foreach ($files as $file)
			{
				$contentDir[] = $file->getName();
			}
			sort($contentDir);
			$serialized = serialize($contentDir);
			$this->secret = md5($serialized);
		}
		return $this->secret;
	}

	private function formatArray(&$array)
	{
		ksort($array);
		foreach ($array as $var => $value)
		{
			if (is_array($value))
			{
			 	$this->formatArray($value);
				$array[$var] = $value;
			} else {
				$array[$var] = strval($value);
			}
		}
	}

	private function countSign($params, $time)
	{
		$secret = $this->getSecret();
		unset ($params['sign']);
		$this->formatArray($params);
		return $time.'-'.hash('SHA256', serialize($params).'|'.$time.'|'.$secret);
	}

	public function sign(&$params)
	{
		$params['sign'] = $this->countSign($params, time());
	}

	public function check($params, $timeDelta=10)
	{
		if (!isset($params['sign']))
		{
			return false;
		}
		$time = time();
		$signTime = intval(explode('-', $params['sign'])[0]);
		if ($signTime > $time || $signTime + $timeDelta < $time)
		{
			return false;
		}
		return $params['sign'] === $this->countSign($params, $signTime);
	}
}
