<?php
namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Code;
use Intervolga\Migrato\Tool\Console\TableHelper;

Loc::loadMessages(__FILE__);

class CheckExecCommand extends BaseCommand
{
	/**
	 * @var \Intervolga\Migrato\Tool\Console\TableHelper table
	 */
	protected $table = null;

	protected function configure()
	{
		$this->setName('checkexec');
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.CHECK_EXEC_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->table = new TableHelper();
		$this->initTable();
		$errors = static::getComponentErrors();
		foreach ($errors as $error)
		{
			$this->addErrorToTable($error);
		}

		$this->logger->add($this->table->getOutput());
	}

	protected function initTable()
	{
		$headers = array(
			'FILE' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_FILE'),
			'LINE' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_LINE'),
			'COMPONENT' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_COMPONENT'),
			'PARAM' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_PARAM'),
			'VALUE' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_VALUE'),
		);
		$this->table->addHeader($headers);
	}

	/**
	 * @param array $error
	 */
	protected function addErrorToTable(array $error)
	{
		foreach ($error['PARAMS_VALUES'] as $param => $value)
		{
			$root = Application::getDocumentRoot();
			$row = array(
				'FILE' => str_replace($root, '', $error['FILE']),
				'LINE' => $error['LINE'],
				'COMPONENT' => $error['COMPONENT'],
				'PARAM' => $param,
				'VALUE' => is_array($value) ? implode(PHP_EOL, $value) : $value,
			);
			$this->table->addRow($row);
		}
	}

	/**
	 * @return array
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	public function getComponentErrors()
	{
		$result = array();
		/**
		 * @var \Bitrix\Main\IO\File[] $check
		 */
		$check = array_merge(Code::getPublicFiles(), Code::getTemplateFiles());
		foreach ($check as $file)
		{
			$data = \PHPParser::parseScript($file->getContents());
			if ($data)
			{
				foreach ($data as $component)
				{
					if ($errors = static::getComponentProbablyNumericIds($component['DATA']))
					{
						$result[] = array(
							'FILE' => $file->getPath(),
							'LINE' => $component['START'],
							'COMPONENT' => $component['DATA']['COMPONENT_NAME'],
							'PARAMS_VALUES' => $errors,
						);
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @param array $component
	 * @return array
	 */
	protected function getComponentProbablyNumericIds(array $component)
	{
		$result = array();
		foreach ($component['PARAMS'] as $param => $value)
		{
			if ($values = static::getProbablyIdNumeric($param, $value))
			{
				$result[$param] = $values;
			}
		}

		return $result;
	}

	/**
	 * @param string $param
	 * @param mixed $value
	 * @return int[]
	 */
	protected function getProbablyIdNumeric($param, $value)
	{
		if (static::isProbablyIdParam($param))
		{
			if (is_array($value))
			{
				if ($numericValues = static::getNumericValues($value))
				{
					return $numericValues;
				}
			}
			else
			{
				if (static::isNumericValue($value))
				{
					return array($value);
				}
			}
		}

		return array();
	}

	/**
	 * @param string $param
	 * @return bool
	 */
	protected function isProbablyIdParam($param)
	{
		$probablyIdParams = array(
			'IBLOCK_ID',
			'PROPERTY_CODE',
		);
		foreach ($probablyIdParams as $probablyIdParam)
		{
			if (substr_count($param, $probablyIdParam) > 0)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array $values
	 * @return array
	 */
	protected function getNumericValues(array $values)
	{
		$result = array();
		foreach ($values as $value)
		{
			if (static::isNumericValue($value))
			{
				$result[] = $value;
			}
		}

		return $result;
	}

	/**
	 * @param mixed $value
	 * @return bool
	 */
	protected static function isNumericValue($value)
	{
		return is_numeric($value);
	}
}