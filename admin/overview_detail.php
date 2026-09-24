<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Web\Helper;
use Intervolga\Migrato\Tool\Web\Overview;

/**
 * Детальная страница записи, подключается из admin/overview.php
 *
 * @global CMain $APPLICATION
 * @var string $path
 * @var string $xmlId
 */
$parts = Overview::getPathParts($path);
$dataClass = Overview::getDataClass($parts[0], $parts[1]);
$record = $dataClass ? Overview::getRecord($parts[0], $parts[1], $xmlId) : null;

$listUrl = Helper::getUrl(Helper::PAGE_OVERVIEW, array('path' => $path));

$APPLICATION->SetTitle(Loc::getMessage(
	'INTERVOLGA_MIGRATO.WEB_OVERVIEW_RECORD_TITLE',
	array(
		'#PATH#' => $path,
		'#XML_ID#' => $xmlId,
	)
));

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

$contextMenu = new CAdminContextMenu(array(
	array(
		'TEXT' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_BACK'),
		'LINK' => $listUrl,
		'ICON' => 'btn_list',
	),
));
$contextMenu->Show();

if (!$record)
{
	ShowError(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_RECORD_NOT_FOUND'));
	require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
	die();
}

$file = Overview::getFileInfo($dataClass, $xmlId);
$comparison = Overview::compareWithFile($record, $dataClass);
$fields = Overview::getFieldsInfo($record);
$links = Overview::getLinksInfo($record);

if ($comparison['STATUS'] === Overview::FILE_MATCH)
{
	CAdminMessage::ShowNote(Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_MATCH'));
}
elseif ($comparison['STATUS'] === Overview::FILE_DIFFERS)
{
	CAdminMessage::ShowMessage(array(
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_DIFFERS'),
		'DETAILS' => implode('<br>', array_map('htmlspecialcharsbx', $comparison['DIFFERENCES'])),
		'HTML' => true,
	));
}
else
{
	CAdminMessage::ShowMessage(array(
		'TYPE' => 'PROGRESS',
		'MESSAGE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_ABSENT'),
	));
}
?>
<style>
	.migrato-detail-value
	{
		max-height: 200px;
		overflow: auto;
		word-break: break-word;
		white-space: pre-wrap;
	}
	.migrato-detail-name
	{
		font-family: "Courier New", Consolas, monospace;
	}
	.migrato-detail-hint
	{
		color: #8b8b8b;
		font-size: 11px;
		display: block;
		margin-top: 3px;
	}
	.migrato-detail-empty
	{
		color: #8b8b8b;
	}
</style>
<?php
$tabControl = new CAdminTabControl(
	'migratoRecordTabControl',
	array(
		array(
			'DIV' => 'record',
			'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TAB_RECORD'),
			'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TAB_RECORD_TITLE'),
		),
		array(
			'DIV' => 'fields',
			'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TAB_FIELDS') . ' (' . count($fields) . ')',
			'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TAB_FIELDS_TITLE'),
		),
		array(
			'DIV' => 'links',
			'TAB' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TAB_LINKS') . ' (' . count($links) . ')',
			'TITLE' => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TAB_LINKS_TITLE'),
		),
	)
);
$tabControl->Begin();

$tabControl->BeginNextTab();
?>
<tr>
	<td width="30%"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_MODULE') ?>:</td>
	<td width="70%">
		<a href="<?= htmlspecialcharsbx(Helper::getUrl(Helper::PAGE_OVERVIEW, array('path' => '/' . $parts[0]))) ?>">
			<?= htmlspecialcharsbx($parts[0]) ?></a>
		<span class="migrato-detail-hint"><?= htmlspecialcharsbx(Overview::getModuleNameLoc($parts[0])) ?></span>
	</td>
</tr>
<tr>
	<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TYPE_ENTITY') ?>:</td>
	<td>
		<a href="<?= htmlspecialcharsbx($listUrl) ?>"><?= htmlspecialcharsbx($parts[1]) ?></a>
		<span class="migrato-detail-hint"><?= htmlspecialcharsbx($dataClass->getEntityNameLoc()) ?></span>
	</td>
</tr>
<tr>
	<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_XML_ID') ?>:</td>
	<td><span class="migrato-detail-name"><?= htmlspecialcharsbx($record->getXmlId()) ?></span></td>
</tr>
<tr>
	<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_ID') ?>:</td>
	<td><span class="migrato-detail-name"><?= htmlspecialcharsbx(Overview::getIdString($record->getId())) ?></span></td>
</tr>
<tr>
	<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_COLUMN_ATTRIBUTES') ?>:</td>
	<td><?= (int)Overview::getAttributesCount($record) ?></td>
</tr>
<tr class="heading">
	<td colspan="2"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE') ?></td>
</tr>
<tr>
	<td><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_PATH') ?>:</td>
	<td>
		<?php if ($file['EXISTS']): ?>
			<span class="migrato-detail-name"><?= htmlspecialcharsbx($file['RELATIVE_PATH']) ?></span>
			<span class="migrato-detail-hint">
				<a href="<?= htmlspecialcharsbx($file['RELATIVE_PATH']) ?>" download>
					<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_DOWNLOAD') ?>
				</a>
				&mdash;
				<a href="<?= htmlspecialcharsbx(Helper::getFileManViewUrl($file['RELATIVE_PATH'])) ?>">
					<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_VIEW') ?>
				</a>
			</span>
		<?php else: ?>
			<span class="migrato-detail-empty"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_FILE_ABSENT') ?></span>
		<?php endif; ?>
	</td>
</tr>
<?php
$tabControl->BeginNextTab();
?>
<?php if (!$fields): ?>
	<tr>
		<td colspan="2"><span class="migrato-detail-empty">
			<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_NO_FIELDS') ?></span></td>
	</tr>
<?php endif; ?>
<?php foreach ($fields as $field): ?>
	<tr>
		<td width="30%">
			<span class="migrato-detail-name"><?= htmlspecialcharsbx($field['NAME']) ?></span>
			<?php if ($field['IS_MULTIPLE']): ?>
				<span class="migrato-detail-hint"><?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_MULTIPLE') ?></span>
			<?php endif; ?>
		</td>
		<td width="70%">
			<div class="migrato-detail-value"><?= implode(
				'<br>',
				array_map('htmlspecialcharsbx', $field['VALUES'])
			) ?></div>
			<?php if ($field['DESCRIPTION']): ?>
				<span class="migrato-detail-hint"><?= htmlspecialcharsbx($field['DESCRIPTION']) ?></span>
			<?php endif; ?>
		</td>
	</tr>
<?php endforeach; ?>
<?php
$tabControl->BeginNextTab();
?>
<?php if (!$links): ?>
	<tr>
		<td colspan="2"><span class="migrato-detail-empty">
			<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_NO_LINKS') ?></span></td>
	</tr>
<?php endif; ?>
<?php foreach ($links as $link): ?>
	<tr>
		<td width="30%">
			<span class="migrato-detail-name"><?= htmlspecialcharsbx($link['NAME']) ?></span>
			<span class="migrato-detail-hint"><?= htmlspecialcharsbx($link['KIND_NAME']) ?></span>
		</td>
		<td width="70%">
			<?php
			$targetPath = ($link['MODULE'] && $link['ENTITY'])
				? '/' . $link['MODULE'] . '/' . $link['ENTITY']
				: '';
			$isTargetKnown = $targetPath && Overview::getDataClass($link['MODULE'], $link['ENTITY']);
			?>
			<?php foreach ($link['VALUES'] as $value): ?>
				<div>
					<?php if ($isTargetKnown && $value !== ''): ?>
						<a href="<?= htmlspecialcharsbx(Helper::getUrl(
							Helper::PAGE_OVERVIEW,
							array('path' => $targetPath, 'xml_id' => $value)
						)) ?>"><?= htmlspecialcharsbx($value) ?></a>
					<?php else: ?>
						<span class="migrato-detail-name"><?= htmlspecialcharsbx($value) ?></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<span class="migrato-detail-hint">
				<?php if ($targetPath): ?>
					<a href="<?= htmlspecialcharsbx(Helper::getUrl(
						Helper::PAGE_OVERVIEW,
						array('path' => $targetPath)
					)) ?>"><?= htmlspecialcharsbx($link['MODULE'] . ':' . $link['ENTITY']) ?></a>
				<?php else: ?>
					<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_TARGET_UNKNOWN') ?>
				<?php endif; ?>
				<?= $link['DESCRIPTION'] ? ' &mdash; ' . htmlspecialcharsbx($link['DESCRIPTION']) : '' ?>
			</span>
		</td>
	</tr>
<?php endforeach; ?>
<?php
$tabControl->Buttons(false);
?>
<input type="button" name="back" value="<?= Loc::getMessage('INTERVOLGA_MIGRATO.WEB_OVERVIEW_BACK') ?>"
	onclick="window.location='<?= CUtil::JSEscape($listUrl) ?>';">
<?php
$tabControl->End();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
