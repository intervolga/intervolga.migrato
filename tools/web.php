<?php

if ($_GET['command']) {
    $command = htmlspecialchars($_GET['command']);
    exec('php run.php '. $command, $out);
    echo json_encode($out);

}