<?php
namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\XmlIdProvider\BaseXmlIdProvider;

Loc::loadMessages(__FILE__);

class FileAccess extends BaseData
{
	protected function configure()
	{
		$this->setEntityNameLoc(Loc::getMessage('INTERVOLGA_MIGRATO.MAIN_FILE_ACCESS'));
		$this->setVirtualXmlId(true);
		$this->setDependencies(array(
			'GROUP' => new Link(Group::getInstance()),
		));
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$files = $this->getAccessFiles();

		if ($files)
		{
			foreach ($files as $file)
			{
				$PERM = array();

				include $file->getPath();

				if ($PERM)
				{
					$result = array_merge($result, $this->permToRecords(
						$PERM,
						$file->getDirectory()->getPath()
					));
				}
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

					$group = $this->prepareGroup($group);
					$record = $this->makeRecord($dir, $path, $group, $permission);
					if ($record)
					{
						$result[] = $record;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @param string|int $group
	 * @return string|int $group
	 */
	protected function prepareGroup($group)
	{
		if (preg_match('/^G([0-9]+)$/', $group, $match))
		{
			return $match[1];
		}

		return $group;
	}

	/**
	 * @param string $dir
	 * @param string $path
	 * @param int $group
	 * @param string $permission
	 * @return null|\Intervolga\Migrato\Data\Record
	 */
	protected function makeRecord($dir, $path, $group, $permission)
	{
		if ($dir && $path && $permission)
		{
			$isForAll = false;
			if ($this->isForAllGroup($group))
			{
				$isForAll = true;
			}

			$record = new Record($this);

			$complexId = $this->createId(array(
				'DIR' => $dir,
				'PATH' => $path,
				'GROUP' => $group,
			));

			$record->setId($complexId);
			if ($xmlId = $this->getXmlId($complexId))
			{
				$record->setXmlId($xmlId);
			}

			$arFields = array(
				'DIR' => $dir,
				'PATH' => $path,
				'PERMISSION' => $permission,
			);

			if ($isForAll)
			{
				$arFields = array_merge(array('GROUP' => '*'), $arFields);
			}

			$record->addFieldsRaw($arFields);

			if (!$isForAll)
			{
				$this->addGroupDependency($record, $group);
			}


			if (is_numeric($group) || $isForAll || preg_match('/^G([0-9]+)$/', $group))
			{
				return $record;
			}
			else
			{
				$record->registerValidateError(Loc::getMessage(
					'INTERVOLGA_MIGRATO.CODE_GROUP_IS_NOT_EXIST',
					array(
						'#CODE_GROUP#' =>$group
					)
				));
				return $record;
			}
		}

		return null;
	}

	/**
	 * @param string $group
	 * @return bool
	 */
	public function isForAllGroup($group)
	{
		return $group === '*';
	}

	public function createId($id)
	{
		$arComplex = array(
			'DIR' => $id['DIR'],
			'PATH' => $id['PATH'],
			'GROUP' => $id['GROUP'],
		);

		return RecordId::createComplexId($arComplex);
	}

	public function getXmlId($id)
	{
		$array = $id->getValue();
		$md5 = array();

		if ($this->isForAllGroup($array['GROUP']))
		{
			$md5 = md5(serialize(array(
				$array['DIR'],
				$array['PATH'],
				$array['GROUP'],
			)));
		}
		else
		{
			$groupData = Group::getInstance();
			$groupXmlId = $groupData->getXmlId($groupData->createId($array['GROUP']));

			if ($groupXmlId)
			{
				$md5 = md5(serialize(array(
					$array['DIR'],
					$array['PATH'],
					$groupXmlId,
				)));
			}
		}

		return BaseXmlIdProvider::formatXmlId($md5);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param int $group
	 * @throws \Exception
	 * @return bool
	 */
	protected function addGroupDependency(Record $record, $group)
	{
		$groupIdObject = Group::getInstance()->createId($group);
		$groupXmlId = Group::getInstance()->getXmlId($groupIdObject);

		if (strlen($groupXmlId))
		{
			$link = clone $this->getDependency('GROUP');
			$link->setValue($groupXmlId);
			$record->setDependency('GROUP', $link);
		}
	}

	protected function deleteInner(RecordId $id)
	{
		$array = $id->getValue();

		$accessPath = Application::getDocumentRoot() . $array['DIR'] . '/.access.php';

		if (File::isFileExists($accessPath))
		{
			$path = $array['PATH'];
			$group = $array['GROUP'];

			$PERM = array();
			include $accessPath;

			$PERM = $this->permissionReformat($PERM, $path, $group);
			if ($PERM[$path][$group])
			{
				unset($PERM[$path][$group]);
				if (!count($PERM[$path]))
				{
					unset($PERM[$path]);
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
		$fields = $this->recordToArray($record);
		$this->updateFile($fields);

		return $this->createId($fields);
	}

	public function update(Record $record)
	{
		$fields = $this->recordToArray($record);
		$this->updateFile($fields);
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @return \string[]
	 * @throws \Exception
	 */
	protected function recordToArray(Record $record)
	{
		$result = $record->getFieldsRaw();

		if ($result['GROUP'] != '*')
		{
			$groupDependency = $record->getDependency('GROUP');
			if ($groupDependency)
			{
				$groupId = $groupDependency->findId();
				if ($groupId)
				{
					$result['GROUP'] = $groupId->getValue();
				}
			}
		}

		return $result;
	}

	/**
	 * @param array $fields
	 */
	protected function updateFile(array $fields)
	{
		$path = $fields['PATH'];
		$group = $fields['GROUP'];
		$perm = $fields['PERMISSION'];
		$accessPath = Application::getDocumentRoot() . $fields['DIR'] . '/.access.php';

		$PERM = array();
		if (File::isFileExists($accessPath))
		{
			include $accessPath;

			$PERM = $this->permissionReformat($PERM, $path, $group);

			if (!$PERM[$path][$group] || $PERM[$path][$group] !== $perm)
			{
				$PERM[$path][$group] = $perm;
				$this->writeToFile($PERM, $accessPath);
			}
		}
		else
		{
			$PERM[$path][$group] = $perm;
			$this->writeToFile($PERM, $accessPath);
		}
	}

	public function permissionReformat($PERM, $path, $group)
	{
		if ($PERM[$path]['G' . $group])
		{
			$PERM[$path][$group] = $PERM[$path]['G' . $group];
			unset($PERM[$path]['G' . $group]);
		}

		return $PERM;
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