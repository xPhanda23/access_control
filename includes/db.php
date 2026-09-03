<?php
// Sem isso, o fuso padrão do PHP (definido no php.ini, ex: Europe/Berlin)
// diverge do fuso do MySQL (America/Sao_Paulo), e strtotime()/time() passam
// a calcular "há quantos segundos foi o último sinal do ESP32" errado -
// fazendo um dispositivo online parecer offline. Precisa bater com o fuso
// que o MySQL usa para NOW()/last_seen.
date_default_timezone_set('America/Sao_Paulo');

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'access_control';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>