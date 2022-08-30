<?php
$_SERVER["DOCUMENT_ROOT"] = realpath(__DIR__."/../../../..");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$backupScriptPath = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/tools/backup.php';

function setValue($name, $value)
{
  COption::SetOptionInt('main', $name.'_auto', $value);
}

$noUpload = false;

$params = [
  'dump_base_skip_stat' => 0,
  'dump_base_skip_search' => 1,
  'dump_base_skip_log' => 0,
  'skip_mask' => 1,
  'dump_archive_size_limit' => 100 * 1024**2,
  'dump_base' => 1,
  'dump_file_kernel' => 1,
  'dump_file_public' => 1,
  'dump_delete_old' => 0,
  'dump_encrypt' => 0,
];

foreach ($argv as $arg) {
  $command = $cmd;
  if (mb_substr($arg, 0, 2) == '--') {
    $cmd = mb_substr($arg, 2);
  } else $cmd = '';
  $value = intval($arg);
  if ($command == 'size') {
    $params['dump_archive_size_limit'] = $value * 1024 ** 2;
  }
  if ($cmd == 'help') {
    echo "\n\n\n";
    echo "Usage: php run.php backup [--nokernel] [--nopublic] [--nodatabase] [--noupload] [--size 100]\n";
    echo "\n\n\n";
    die();
  }
  if ($cmd == 'nodatabase') {
    $params['dump_base'] = 0;
  }
  if ($cmd == 'nokernel') {
    $params['dump_file_kernel'] = 0;
  }
  if ($cmd == 'nopublic') {
    $params['dump_file_public'] = 0;
  }
  if ($cmd == 'noupload') {
    $noUpload = true;
  }
}

$fileMaskSerialized = COption::GetOptionString('main', 'skip_mask_array_auto');
$fileMask = unserialize($fileMaskSerialized);
if ($noUpload) {
  $fileMask = array_unique(array_merge($fileMask, ['/upload']));
} else {
  $fileMask = array_flip($fileMask);
  unset($fileMask['/upload']);
  $fileMask = array_flip($fileMask);
}
$fileMaskSerialized = serialize($fileMask);
COption::SetOptionString('main', 'skip_mask_array_auto', $fileMaskSerialized);

foreach ($params as $name => $value) {
  setValue($name, $value);
}

exec("php ".$backupScriptPath);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
