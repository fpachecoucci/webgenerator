<?php
$host = "localhost";
$user = "adm_webgenerator";
$pass = "webgenerator2024";
$dbname = "webgenerator";

mysqli_report(MYSQLI_REPORT_OFF);

$db = @new mysqli($host, $user, $pass, $dbname);

if ($db->connect_error) {
    die("Error de conexión a la base de datos: " . $db->connect_error);
}
?>