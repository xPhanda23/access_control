<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';

// O painel (Cartões de Acesso) chama isso em loop curto enquanto espera o
// ESP32 ler uma tag em modo de cadastro. Assim que enroll_uid aparece, ele é
// devolvido e limpo do banco (consumido uma única vez).

$device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;
if ($device_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'device_id inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT enroll_mode, enroll_uid FROM devices WHERE id = ?");
$stmt->bind_param("i", $device_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Dispositivo não encontrado']);
    exit;
}
$device = $result->fetch_assoc();

if ($device['enroll_uid'] !== null) {
    $uid = $device['enroll_uid'];
    $clear = $conn->prepare("UPDATE devices SET enroll_uid = NULL WHERE id = ?");
    $clear->bind_param("i", $device_id);
    $clear->execute();

    echo json_encode(['success' => true, 'enroll_mode' => false, 'uid' => $uid]);
    exit;
}

echo json_encode(['success' => true, 'enroll_mode' => (bool)$device['enroll_mode'], 'uid' => null]);
?>
