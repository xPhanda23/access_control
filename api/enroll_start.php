<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';
require_once '../includes/device_helpers.php';

// Chamado pelo painel (Cartões de Acesso) para colocar um ESP32 online em
// modo de cadastro de tag: ele acende o LED amarelo, mostra "Modo Cadastro"
// no LCD e manda pro servidor o UID da próxima tag aproximada do leitor.

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$device_id = isset($input['device_id']) ? intval($input['device_id']) : 0;
if ($device_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'device_id inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->bind_param("i", $device_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Dispositivo não encontrado']);
    exit;
}
$device = $result->fetch_assoc();

if (!isDeviceOnline($device)) {
    echo json_encode(['success' => false, 'message' => 'Dispositivo está offline']);
    exit;
}

$update = $conn->prepare("UPDATE devices SET enroll_mode = 1, enroll_uid = NULL WHERE id = ?");
$update->bind_param("i", $device_id);
$update->execute();

echo json_encode(['success' => true]);
?>
