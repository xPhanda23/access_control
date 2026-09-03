<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/device_helpers.php';

// Endpoint de "heartbeat" que o ESP32 chama periodicamente (a cada poucos
// segundos, mesmo sem nenhum cartão sendo lido) pra avisar que continua
// online e pra saber se deve entrar em modo de cadastro de tag.
//
// Uso pelo ESP32: POST { "mac_address": "AA:BB:CC:11:22:33" }
// O dispositivo é criado automaticamente na primeira chamada de cada MAC —
// não existe cadastro manual de dispositivo neste sistema.

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_GET;
}

$mac_address = isset($input['mac_address']) ? trim($input['mac_address']) : '';

if (!$mac_address) {
    echo json_encode(['success' => false, 'message' => 'mac_address é obrigatório']);
    exit;
}

$device = getOrCreateDeviceByMac($conn, $mac_address);

echo json_encode([
    'success' => true,
    'device_id' => (int)$device['id'],
    'device_name' => $device['device_name'],
    'enroll_mode' => (bool)$device['enroll_mode'],
    'write_mode' => (bool)$device['write_mode'],
    'write_target_uid' => $device['write_mode'] ? $device['write_target_uid'] : null
]);
?>
