<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/device_helpers.php';

// Chamado pelo ESP32 quando ele está em modo de cadastro (enroll_mode = 1)
// e detecta uma tag no leitor RC522. Sem login, no mesmo padrão de
// verify_card.php/heartbeat.php (dispositivo já autenticado por estar na
// mesma rede local).

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$mac_address = isset($input['mac_address']) ? trim($input['mac_address']) : '';
$uid = isset($input['uid']) ? trim($input['uid']) : '';

if (!$mac_address || !$uid) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

$mac_address = strtoupper($mac_address);
$stmt = $conn->prepare("SELECT * FROM devices WHERE mac_address = ?");
$stmt->bind_param("s", $mac_address);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Dispositivo não encontrado']);
    exit;
}
$device = $result->fetch_assoc();

$update = $conn->prepare("UPDATE devices SET enroll_uid = ?, enroll_mode = 0, status = 'online', last_seen = NOW() WHERE id = ?");
$update->bind_param("si", $uid, $device['id']);
$update->execute();

echo json_encode(['success' => true, 'message' => 'UID recebido']);
?>
