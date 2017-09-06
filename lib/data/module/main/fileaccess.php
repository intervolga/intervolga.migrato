<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

Loc::loadMessages(__FILE__);

class FileAccess extends BaseData
{
	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_FILE_ACCESS'));
		$this->setVirtualXmlId(true);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$files = $this->getAccessFiles();

		if ($files && is_array($files))
		{
			foreach ($files as $fileObj)
			{
				$PERM = array();

				include $fileObj->getPath();

				$fileAccess = new FileAccess();
				$result = array_merge($result, $fileAccess->permToRecords(
					$PERM,
					$fileObj->getDirectory()->getPath()
				));
			}
		}

		return $result;
	}

	/**
	 * @return \Bitrix\Main\IO\File[]
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	protected function getAccessFiles()
	{
		$root = Application::getDocumentRoot();
		$dir = new Directory($root);
		$check = array();
		foreach ($dir->getChildren() as $fileSystemEntry)
		{
			if ($fileSystemEntry instanceof File)
			{
				if ($this->isAccessFile($fileSystemEntry))
				{
					$check[] = $fileSystemEntry;
				}
			}
			if ($fileSystemEntry instanceof Directory)
			{
				if (!$this->isServiceDirectory($fileSystemEntry))
				{
					$check = array_merge($check, $this->getFilesRecursive($fileSystemEntry));
				}
			}
		}

		$bitrixAccess = new File($root . '/bitrix/.access.php');
		if ($bitrixAccess->isExists())
		{
			$check[] = $bitrixAccess;
		}

		return $check;
	}

	/**
	 * @param \Bitrix\Main\IO\File $file
	 * @return bool
	 */
	protected function isAccessFile(File $file)
	{
		return ($file->getName() == '.access.php');
	}

	/**
	 * @param \Bitrix\Main\IO\Directory $directory
	 * @return bool
	 */
	protected function isServiceDirectory(Directory $directory)
	{
		$names = array(
			'bitrix',
			'local',
			'upload',
			'.git',
			'.svn',
		);
		if (in_array($directory->getName(), $names))
		{
			return true;
		}

		return false;
	}

	/**
	 * @param \Bitrix\Main\IO\Directory $dir
	 * @return \Bitrix\Main\IO\File[]
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	protected function getFilesRecursive(Directory $dir)
	{
		$result = array();
		if ($dir->isExists())
		{
			foreach ($dir->getChildren() as $fileSystemEntry)
			{
				if ($fileSystemEntry instanceof File)
				{
					if ($this->isAccessFile($fileSystemEntry))
					{
						$result[] = $fileSystemEntry;
					}
				}
				if ($fileSystemEntry instanceof Directory)
				{
					$result = array_merge($result, $this->getFilesRecursive($fileSystemEntry));
				}
			}
		}

		return $result;
	}

	/**
	 * @param array $perm
	 * @param string $fullPath
	 * @return array
	 */
	protected function permToRecords($perm, $fullPath)
	{
		$result = array();
		$root = Application::getDocumentRoot();

		if ($perm)
		{
			foreach ($perm as $path => $permissions)
			{
				foreach ($permissions as $group => $permission)
				{
					$replaced = str_replace($root, '', $fullPath);
					$dir = $replaced ?: '/';

					$groupIdObject = Group::getInstance()->createId($group);
					$groupXmlId = Group::getInstance()->getXmlId($groupIdObject);

					$record = $this->makeRecord($dir, $path, $groupXmlId, $permission);
					if ($record)
					{
						$result[$dir . $path . $group] = $record;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @param string $dir
	 * @param string $path
	 * @param string $groupXmlId
	 * @param string $permission
	 * @return null|\Intervolga\Migrato\Data\Record
	 */
	protected function makeRecord($dir, $path, $groupXmlId, $permission)
	{
		if ($dir && $path && $permission)
		{
			$record = new Record($this);
			$complexId = RecordId::createComplexId(array(
				$dir, $path, $groupXmlId,
			));
			$record->setId($this->createId($complexId));
			$record->setXmlId($this->getXmlId($complexId));
			$record->addFieldsRaw(array(
				'DIR' => $dir,
				'PATH' => $path,
				'GROUP' => $groupXmlId,
				'PERMISSION' => $permission,
			));

			return $record;
		}

		return null;
	}

	public function getXmlId($id)
	{
		return md5(serialize($id->getValue()));
	}

	protected function deleteInner(RecordId $id)
	{
		if ($arRecord = $this->getList())
		{
			foreach ($arRecord as $idRecord => $record)
			{
				$fields = $record->getFields();
				$complexId = RecordId::createComplexId(array(
					$fields['DIR']->getValue(),
					$fields['PATH']->getValue(),
					$fields['GROUP']->getValue(),
				));

				if (implode('.', $id->getValue()) === implode('.', $complexId->getValue()))
				{
					$this->deleteRecord($fields);
					break;
				}
			}
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Value[] $fields
	 */
	protected function deleteRecord(array $fields)
	{
		$accessPath = Application::getDocumentRoot() . $fields['DIR']->getValue() . '/.access.php';
		$groupXmlId = $fields['GROUP']->getValue();
		$group = $groupXmlId ? Group::getInstance()->getPublicId($groupXmlId) : '*';
		$path = $fields['PATH']->getValue();

		if (File::isFileExists($accessPath))
		{
			$PERM = array();

			include $accessPath;

			if ($PERM[$path][$group])
			{
				if (count($PERM[$path][$group]) == 1)
				{
					unset($PERM[$path]);
				}
				else
				{
					unset($PERM[$path][$group]);
				}

				if (count($PERM) > 0)
				{
					$this->writeToFile($PERM, $accessPath);
				}
				else
				{
					File::deleteFile($accessPath);
				}
			}
		}
	}

	public function createInner(Record $record)
	{
		$fields = $record->getFields();
		$this->updateFile($fields);

		return RecordId::createComplexId(array(
			$fields['DIR']->getValue(),
			$fields['PATH']->getValue(),
			$fields['GROUP']->getValue(),
		));
	}

	public function update(Record $record)
	{
		$fields = $record->getFields();
		$this->updateFile($fields);

		return RecordId::createComplexId(array(
			$fields['DIR']->getValue(),
			$fields['PATH']->getValue(),
			$fields['GROUP']->getValue(),
		));
	}

	/**
	 * @param \Intervolga\Migrato\Data\Value[] $fields
	 */
	protected function updateFile(array $fields)
	{
		$path = $fields['PATH']->getValue();
		$groupXmlId = $fields['GROUP']->getValue();
		$group = $groupXmlId ? Group::getInstance()->getPublicId($groupXmlId) : '*';
		$perm = $fields['PERMISSION']->getValue();
		$documentRoot = Application::getDocumentRoot() . $fields['DIR']->getValue() . '/.access.php';
		$PERM = array();

		if (File::isFileExists($documentRoot))
		{
			include $documentRoot;

			if (!$PERM[$path][$group] || $PERM[$path][$group] !== $perm)
			{
				$PERM[$path][$group] = $perm;
				$this->writeToFile($PERM, $documentRoot);
			}
		}
		else
		{
			$PERM[$path][$group] = $perm;
			$this->writeToFile($PERM, $documentRoot);
		}
	}

	/**
	 * @param array $perm
	 * @param string $accessPath
	 */
	protected function writeToFile($perm, $accessPath)
	{
		$str = "<?\n";
		foreach ($perm as $permPath => $permissions)
		{
			foreach ($permissions as $permGroup => $permission)
			{
				$str .= "\$PERM[\"" . EscapePHPString($permPath) . "\"][\"" .
					EscapePHPString($permGroup) . "\"]=\"" . EscapePHPString($permission) . "\";\n";
			}
		}
		$str .= "?>";

		File::putFileContents($accessPath, $str);
	}
}