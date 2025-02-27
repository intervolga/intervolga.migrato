<?php

namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\DataFileViewXml;
use Symfony\Component\Console\Input\InputOption;

Loc::loadMessages(__FILE__);

class CleanDeletedXml extends BaseCommand
{
	protected function configure()
	{
		$this->setName('cleandeletedxml');
		$this->setDescription('Удаление файлов, помеченных как удаленные');
		$this->addOption(
			'all',
			null,
			InputOption::VALUE_NONE,
			'Удалить все файлы, помеченные как удаленные'
		);
		$this->addOption(
			'backup',
			null,
			InputOption::VALUE_NONE,
			'Создать резервную копию папки migrato перед удалением файлов'
		);
	}

	public function executeInner()
	{
		if ($this->input->getOption('backup'))
		{
			$this->copyData();
		}
		$result = $this->input->getOption('all') ? $this->cleanAll() : $this->clean();
		$count = count(array_merge(...array_values($result)));
		if ($count)
		{
			$this->logger->add("Краткая сводка по удаленным файлам:", $this->logger::LEVEL_SHORT);
			foreach ($result as $moduleId => $files)
			{
				if (count($files))
				{
					$this->logger->add("Модуль {$moduleId}: удалено файлов " . count($files) . " шт.", $this->logger::LEVEL_SHORT, $this->logger::TYPE_INFO);
				}
			}
		}
		else
		{
			$this->logger->add("Ничего не удалено", $this->logger::LEVEL_NORMAL, $this->logger::TYPE_OK);
		}

	}

	private function clean(): array
	{
		$modules = Config::getInstance()->getModules();
		$result = [];
		foreach ($modules as $module)
		{
			if (isset($result[$module]))
			{
				continue;
			}
			$result[$module] = DataFileViewXml::getFilesMarkedAsDeletedRecursively(INTERVOLGA_MIGRATO_DIRECTORY . $module . '/');
			foreach ($result[$module] as $file)
			{
				$this->deleteFile($module, $file);
			}
		}

		return $result;
	}

	private function cleanAll(): array
	{
		$files = DataFileViewXml::getFilesMarkedAsDeletedRecursively(INTERVOLGA_MIGRATO_DIRECTORY);
		$result = [];
		foreach ($files as $file)
		{
			$module = preg_match('/^' . preg_quote(INTERVOLGA_MIGRATO_DIRECTORY, '/') . '([^\/]+)\//', $file->getPath(), $matches) ? $matches[1] : '';
			if (!isset($result[$module]))
			{
				$result[$module] = [];
			}
			$result[$module][] = $file;
			$this->deleteFile($module, $file);
		}

		return $result;
	}

	private function deleteFile(string $module, File $file): void
	{
		$deleteResult = $file->delete();
		if ($deleteResult)
		{
			$this->logger->add("Модуль {$module}: " .  $file->getName(), $this->logger::LEVEL_DETAIL, $this->logger::TYPE_OK);
		}
		else
		{
			$this->logger->add("Модуль {$module}: " .  $file->getName(), $this->logger::LEVEL_DETAIL, $this->logger::TYPE_FAIL);
		}
	}

	protected function copyData()
	{
		$copyDir = preg_replace('/\/$/', '_backup/', INTERVOLGA_MIGRATO_DIRECTORY);
		Directory::deleteDirectory($copyDir);

		CopyDirFiles(INTERVOLGA_MIGRATO_DIRECTORY, $copyDir, false, true);
	}
}