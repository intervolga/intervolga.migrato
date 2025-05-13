<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$root = \Bitrix\Main\Application::getDocumentRoot();
define("INTERVOLGA_MIGRATO_DIRECTORY", $root . "/local/migrato/");
define("INTERVOLGA_MIGRATO_CONFIG_PATH", INTERVOLGA_MIGRATO_DIRECTORY . "config.xml");


$autoloadFilePath = __DIR__ . '/vendor/autoload.php';
if(file_exists($autoloadFilePath))
{
    include $autoloadFilePath;
}