<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';

// Cancela o modo de gravação de UID (usuário desistiu ou o tempo esgotou no painel).

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$device_id = isset($input['device_id']) ? intval($input['device_id']) : 0;
if ($device_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'device_id inválido']);
    exit;
}

$update = $conn->prepare("UPDATE devices SET write_mode = 0, write_target_uid = NULL, write_result = NULL WHERE id = ?");
$update->bind_param("i", $device_id);
$update->execute();

echo json_encode(['success' => true]);
?>
