<?php
namespace Intervolga\Migrato\Tool;

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\IO\FileSystemEntry;

class Code
{
	/**
	 * @return \Bitrix\Main\IO\File[]
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	public static function getTemplateFiles()
	{
		$result = array();
		$root = \Bitrix\Main\Application::getDocumentRoot();
		/**
		 * @var \Bitrix\Main\IO\Directory[] $dirs
		 */
		$dirs = array(
			new Directory($root . '/bitrix/templates/'),
			new Directory($root . '/local/templates/'),
		);
		foreach ($dirs as $dir)
		{
			foreach ($dir->getChildren() as $templateDir)
			{
				if ($templateDir instanceof Directory)
				{
					foreach ($templateDir->getChildren() as $templateFile)
					{
						$checkFiles = array('header.php', 'footer.php');
						if (in_array($templateFile->getName(), $checkFiles))
						{
							$result[] = $templateFile;
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @return \Bitrix\Main\IO\File[]
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	public static function getPublicFiles()
	{
		$root = Application::getDocumentRoot();
		$dir = new Directory($root);
		/**
		 * @var \Bitrix\Main\IO\File[] $check
		 */
		$check = array();
		foreach ($dir->getChildren() as $fileSystemEntry)
		{
			if (!static::isServiceEntry($fileSystemEntry))
			{
				if ($fileSystemEntry instanceof File)
				{
					if (static::isCodeFile($fileSystemEntry))
					{
						$check[] = $fileSystemEntry;
					}
				}
				if ($fileSystemEntry instanceof Directory)
				{
					$check = array_merge($check, static::getFilesRecursive($fileSystemEntry));
				}
			}
		}

		return $check;
	}

	/**
	 * @param \Bitrix\Main\IO\FileSystemEntry $fileSystemEntry
	 * @return bool
	 */
	protected static function isServiceEntry(FileSystemEntry $fileSystemEntry)
	{
		if ($fileSystemEntry->isFile())
		{
			if ($fileSystemEntry->getName() == 'urlrewrite.php')
			{
				return true;
			}
		}
		if ($fileSystemEntry->isDirectory())
		{
			$names = array(
				'bitrix',
				'local',
				'upload',
				'.git',
				'.svn',
			);
			if (in_array($fileSystemEntry->getName(), $names))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param \Bitrix\Main\IO\File $file
	 * @return bool
	 */
	protected static function isCodeFile(File $file)
	{
		return ($file->getExtension() == 'php');
	}

	/**
	 * @param Directory $dir
	 * @return array
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	protected static function getFilesRecursive(Directory $dir)
	{
		$result = array();
		if ($dir->isExists())
		{
			foreach ($dir->getChildren() as $fileSystemEntry)
			{
				if ($fileSystemEntry instanceof File)
				{
					if (static::isCodeFile($fileSystemEntry))
					{
						$result[] = $fileSystemEntry;
					}
				}
				if ($fileSystemEntry instanceof Directory)
				{
					$result = array_merge($result, static::getFilesRecursive($fileSystemEntry));
				}
			}
		}

		return $result;
	}
}