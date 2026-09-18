<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\Web\CommandRunner;
use Intervolga\Migrato\Tool\Web\Helper;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

if (!Loader::includeModule('intervolga.migrato'))
{
	die(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_MODULE_NOT_INSTALLED'));
}

Helper::checkWriteRights();

if (!check_bitrix_sessid())
{
	die(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_BAD_SESSID'));
}

if (!Helper::isConsoleAvailable())
{
	die(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_NO_SYMFONY'));
}

$commandName = (string)($_REQUEST['command'] ?? '');
$command = CommandRunner::getCommand($commandName);
if (!$command)
{
	die(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_UNKNOWN_COMMAND'));
}
if (!Helper::isConfigExists())
{
	die(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_NO_CONFIG'));
}

$parameters = CommandRunner::extractParameters($command, $_REQUEST);
$errors = CommandRunner::validateParameters($command, $parameters);
if ($errors)
{
	die(htmlspecialcharsbx(implode('; ', $errors)));
}
$verbosity = CommandRunner::normalizeVerbosity($_REQUEST['VERBOSITY'] ?? OutputInterface::VERBOSITY_NORMAL);

@header('Content-Type: text/html; charset=' . LANG_CHARSET);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
<head>
	<meta charset="<?= htmlspecialcharsbx(LANG_CHARSET) ?>">
	<title><?= htmlspecialcharsbx($commandName) ?></title>
	<style>
		html, body
		{
			margin: 0;
			padding: 0;
			background: #1f2529;
		}
		pre
		{
			margin: 0;
			padding: 12px 14px;
			color: #d7dfe6;
			font-family: "Courier New", Consolas, monospace;
			font-size: 12px;
			line-height: 1.45;
			white-space: pre-wrap;
			word-wrap: break-word;
		}
		.migrato-result
		{
			border-top: 1px solid #3a444c;
			padding: 10px 14px;
			color: #d7dfe6;
			font-family: "Courier New", Consolas, monospace;
			font-size: 12px;
		}
		.migrato-result-ok
		{
			color: #2ecc71;
		}
		.migrato-result-fail
		{
			color: #e74c3c;
		}
	</style>
	<script>
		var migratoStickToBottom = true;
		window.addEventListener('scroll', function () {
			var delta = document.documentElement.scrollHeight - window.scrollY - window.innerHeight;
			migratoStickToBottom = (delta < 40);
		});
		setInterval(function () {
			if (migratoStickToBottom)
			{
				window.scrollTo(0, document.documentElement.scrollHeight);
			}
		}, 300);
	</script>
</head>
<body>
<pre><?php
$startTime = microtime(true);
$returnCode = CommandRunner::run($commandName, $parameters, $verbosity);
$duration = round(microtime(true) - $startTime, 1);
?></pre>
<?php
$resultClass = ($returnCode === Logger::SUCCESS_RETURN_CODE) ? 'migrato-result-ok' : 'migrato-result-fail';
?>
<div class="migrato-result <?= $resultClass ?>">
	<?= htmlspecialcharsbx(Loc::getMessage(
		'INTERVOLGA_MIGRATO.WEB_FINISHED',
		array(
			'#CODE#' => $returnCode,
			'#TIME#' => $duration,
		)
	)) ?>
</div>
<script>
	migratoStickToBottom = true;
	window.scrollTo(0, document.documentElement.scrollHeight);
</script>
</body>
</html>
<?php
die();
