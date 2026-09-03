<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';
require_once '../includes/device_helpers.php';

// Chamado pelo painel (Cartões de Acesso) para colocar um ESP32 online em
// modo de gravação de UID: o operador define o UID no site, aproxima um
// cartão MÁGICO (Gen1A/Gen2/CUID) do leitor, e o ESP32 regrava o bloco 0
// do cartão com esse UID. Cartões comuns (UID travado de fábrica) não
// suportam isso - só funciona em cartões vendidos como "regraváveis".

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$device_id = isset($input['device_id']) ? intval($input['device_id']) : 0;
$uid = isset($input['uid']) ? strtoupper(trim($input['uid'])) : '';

if ($device_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'device_id inválido']);
    exit;
}

if (!preg_match('/^[0-9A-F]{8}$/', $uid)) {
    echo json_encode(['success' => false, 'message' => 'UID precisa ter exatamente 8 caracteres hexadecimais (4 bytes) para gravar no cartão.']);
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

// Mutuamente exclusivo com o modo de cadastro de tag
$update = $conn->prepare("UPDATE devices SET write_mode = 1, write_target_uid = ?, write_result = NULL, enroll_mode = 0, enroll_uid = NULL WHERE id = ?");
$update->bind_param("si", $uid, $device_id);
$update->execute();

echo json_encode(['success' => true]);
?>
