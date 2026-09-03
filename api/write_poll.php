<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';

// O painel (Cartões de Acesso) chama isso em loop curto enquanto espera o
// ESP32 tentar gravar o UID no cartão físico. Assim que write_result
// aparece, é devolvido e limpo do banco (consumido uma única vez).

$device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;
if ($device_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'device_id inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT write_mode, write_result FROM devices WHERE id = ?");
$stmt->bind_param("i", $device_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Dispositivo não encontrado']);
    exit;
}
$device = $result->fetch_assoc();

if ($device['write_result'] !== null) {
    $resultado = $device['write_result'];
    $clear = $conn->prepare("UPDATE devices SET write_result = NULL WHERE id = ?");
    $clear->bind_param("i", $device_id);
    $clear->execute();

    echo json_encode(['success' => true, 'write_mode' => false, 'result' => $resultado]);
    exit;
}

echo json_encode(['success' => true, 'write_mode' => (bool)$device['write_mode'], 'result' => null]);
?>
