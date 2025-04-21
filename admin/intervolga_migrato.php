<?php

use Bitrix\Main\Loader;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");
if (!Loader::includeModule('intervolga.migrato')) {
    throw new Exception('Не установлен модуль intervolga.migrato');
}

if ($APPLICATION->GetGroupRight('intervolga.migrato') == 'D') {
    throw new Exception('Доступ запрещён');
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

?>

<div>
    <button id="migrato_validate">
        Проверить систему
    </button>

    <textarea id="migrato_result" rows="50" cols="100">

    </textarea>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        let validateBtn = document.getElementById('migrato_validate');
        if (validateBtn) {
            validateBtn.addEventListener('click', function () {
                fetch('/local/modules/intervolga.migrato/tools/web.php?command=validate')
                .then(response => {
                   let migratoResultArea = document.getElementById('migrato_result');
                   if (migratoResultArea) {
                       response.json().then((data) => {
                           migratoResultArea.innerHTML += '&#13;&#10; '
                           data.forEach((element) => {
                               migratoResultArea.innerHTML += '&#13;&#10; ' + element;
                           })

                       });
                   }
               });
            });
        }
    });
</script>
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");