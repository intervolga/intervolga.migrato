<? namespace Intervolga\Migrato\Data\Module\Main;

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\IO\FileSystemEntry;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Record;
use Intervolga\Migrato\Data\RecordId;

class FileAccess extends BaseData
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
	 * @param bool $accessFilter
	 * @return array
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	protected static function getFilesRecursive(Directory $dir, $accessFilter = false)
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
						if (!$accessFilter || static::isAccessFile($fileSystemEntry))
						{
							$result[] = $fileSystemEntry;
						}
					}
				}
				if ($fileSystemEntry instanceof Directory)
				{
					$result = array_merge($result, static::getFilesRecursive($fileSystemEntry, $accessFilter));
				}
			}
		}

		return $result;
	}

	/**
	 * @return \Bitrix\Main\IO\File[]
	 * @throws \Bitrix\Main\IO\FileNotFoundException
	 */
	public static function getAccessFiles()
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
					if (static::isAccessFile($fileSystemEntry))
					{
						$check[] = $fileSystemEntry;
					}
				}
				if ($fileSystemEntry instanceof Directory)
				{
					$check = array_merge($check, static::getFilesRecursive($fileSystemEntry, true));
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

	public function getList(array $filter = array())
	{
		$result = array();
		$files = static::getAccessFiles();
		$root = Application::getDocumentRoot();

		if ($files && is_array($files))
		{
			foreach ($files as $fileObj)
			{
				$PERM = array();

				include $fileObj->getPath();

				foreach ($PERM as $path => $permissions)
				{
					foreach ($permissions as $group => $permission)
					{
						// prepare
						// dir
						$replaced = str_replace($root, '', $fileObj->getDirectory()->getPath());
						$dir = $replaced ? : '/';

						// group xml id
						$groupIdObject = Group::getInstance()->createId($group);
						$groupXmlId = Group::getInstance()->getXmlId($groupIdObject);

						// record
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

						$result[] = $record;
					}
				}
			}
		}

		return $result;
	}

	public function getXmlId($id)
	{
		return md5(serialize($id->getValue()));
	}

	/**
	 * @param \Bitrix\Main\IO\File $file
	 * @return bool
	 */
	protected static function isAccessFile(File $file)
	{
		return ($file->getName() == '.access.php');
	}
}