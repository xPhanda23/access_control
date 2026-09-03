<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

// Chamado pelo ESP32 depois de tentar gravar o UID no cartão físico (com
// sucesso ou não). Sem login, mesmo padrão de verify_card.php/heartbeat.php.

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$mac_address = isset($input['mac_address']) ? strtoupper(trim($input['mac_address'])) : '';
$sucesso     = isset($input['success']) ? (bool)$input['success'] : false;
$mensagem    = isset($input['message']) ? trim($input['message']) : '';

if (!$mac_address) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM devices WHERE mac_address = ?");
$stmt->bind_param("s", $mac_address);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Dispositivo não encontrado']);
    exit;
}
$device = $result->fetch_assoc();

$prefixo = $sucesso ? 'OK:' : 'ERRO:';
$resultado = substr($prefixo . $mensagem, 0, 150);

$update = $conn->prepare("UPDATE devices SET write_mode = 0, write_target_uid = NULL, write_result = ?, status = 'online', last_seen = NOW() WHERE id = ?");
$update->bind_param("si", $resultado, $device['id']);
$update->execute();

echo json_encode(['success' => true]);
?>
