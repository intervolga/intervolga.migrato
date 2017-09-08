<?php
namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Application;
use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Code;
use Intervolga\Migrato\Tool\Console\Logger;
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
		$this->initTable();
		$this->checkComponents();
		$this->logger->add($this->table->getOutput());
	}

	protected function initTable()
	{
		$this->table = new TableHelper();
		$headers = array(
			'FILE' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_FILE'),
			'LINE' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_LINE'),
			'OBJECT' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_OBJECT'),
			'FIELD' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_FIELD'),
			'VALUE' => Loc::getMessage('INTERVOLGA_MIGRATO.HEADER_VALUE'),
		);
		$this->table->addHeader($headers);
	}

	protected function checkComponents()
	{
		$check = array_merge(
			Code::getPublicFiles(),
			Code::getTemplateFiles()
		);
		foreach ($check as $file)
		{
			$this->checkFileForComponentsErrors($file);
		}
	}

	/**
	 * @param File $file
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	protected function checkFileForComponentsErrors(File $file)
	{
		if ($components = \PHPParser::parseScript($file->getContents()))
		{
			foreach ($components as $component)
			{
				$this->checkComponentForErrors($component, $file);
			}
		}
	}

	/**
	 * @param array $component
	 * @param File $file
	 */
	protected function checkComponentForErrors(array $component, File $file)
	{
		if ($errors = $this->getComponentErrors($component['DATA']))
		{
			$this->logComponentErrors($errors, $component, $file);
		}
	}

	/**
	 * @param array $errors
	 * @param array $component
	 * @param File $file
	 */
	protected function logComponentErrors(array $errors, array $component, File $file)
	{
		foreach ($errors as $param => $values)
		{
			foreach ($values as $value)
			{
				$this->addTableRow(array(
					'FILE' => $file->getPath(),
					'LINE' => $component['START'],
					'OBJECT' => $component['DATA']['COMPONENT_NAME'],
					'FIELD' => $param,
					'VALUE' => $value,

					'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.CHECK_COMPONENT'),
					'COMMENT' => 'INTERVOLGA_MIGRATO.CHECK_COMPONENT_COMMENT',
				));
			}
		}
	}

	/**
	 * @param array $component
	 * @return array
	 */
	protected function getComponentErrors(array $component)
	{
		$result = array();
		foreach ($component['PARAMS'] as $param => $value)
		{
			if ($values = $this->getProbablyIdNumeric($param, $value))
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
		if ($this->isProbablyIdParam($param))
		{
			if (is_array($value))
			{
				if ($numericValues = $this->getNumericValues($value))
				{
					return $numericValues;
				}
			}
			else
			{
				if ($this->isNumericValue($value))
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
			if ($this->isNumericValue($value))
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
	protected function isNumericValue($value)
	{
		return is_numeric($value);
	}

	/**
	 * @param array $row
	 */
	protected function addTableRow(array $row)
	{
		$root = Application::getDocumentRoot();
		$row['FILE'] = str_replace($root, '', $row['FILE']);

		$this->logger->addDb(
			array(
				'OPERATION' => $row['OPERATION'],
				'ID' => RecordId::createStringId($row['OBJECT']),
				'COMMENT' => Loc::getMessage(
					$row['COMMENT'],
					array(
						'#FILE#' => $row['FILE'],
						'#LINE#' => $row['LINE'],
						'#FIELD#' => $row['FIELD'],
						'#VALUE#' => $row['VALUE'],
					)
				),
			),
			Logger::TYPE_FAIL
		);

		unset($row['COMMENT']);
		unset($row['OPERATION']);

		$this->table->addRow($row);
	}
}