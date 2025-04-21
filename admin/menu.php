<?php
global $APPLICATION;

use Bitrix\Main\Loader;


if (!Loader::includeModule('intervolga.migrato')) {
    return false;
}

$aMenu = [];
$aMenu[] = [
    'parent_menu' => 'global_menu_settings',
    'section' => 'intervolga_migrato',
    'sort' => 50,
    'text' => 'IntervolgaMigrato',
    'icon' => 'sys_menu_icon',
    'page_icon' => 'sys_page_icon',
    'items_id' => 'intervogla_migrato',
    'url' => 'intervolga_migrato.php',
    'items' => [
        [
            'text' => 'Миграции',
            'url' => 'intervolga_migrato.php',
            'more_url' => [],
            'title' => 'Миграции',
        ]
    ],
];

return $aMenu;

