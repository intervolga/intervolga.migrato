<?php

if (is_file($_SERVER["DOCUMENT_ROOT"] . "/local/modules/intervolga.migrato/admin/intervolga_migrato.php")) {
    require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/intervolga.migrato/admin/intervolga_migrato.php");
} else {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/intervolga.migrato/admin/intervolga_migrato.php");
}