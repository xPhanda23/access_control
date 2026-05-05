<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

// Endpoint que o ESP32 chamará via POST
// Espera receber: card_uid e device_id (ou device_name)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // tentar GET também para simular
    $input = $_GET;
}

$card_uid = $input['card_uid'] ?? '';
$device_id = $input['device_id'] ?? 0;
$device_name = '';

if (!$card_uid || !$device_id) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

// Buscar dispositivo
$devStmt = $conn->prepare("SELECT device_name FROM devices WHERE id = ?");
$devStmt->bind_param("i", $device_id);
$devStmt->execute();
$devResult = $devStmt->get_result();
if ($devResult->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Dispositivo não encontrado']);
    exit;
}
$device = $devResult->fetch_assoc();
$device_name = $device['device_name'];

// Verificar cartão
$cardStmt = $conn->prepare("SELECT id, status FROM cards WHERE card_uid = ?");
$cardStmt->bind_param("s", $card_uid);
$cardStmt->execute();
$cardResult = $cardStmt->get_result();
if ($cardResult->num_rows == 0) {
    // Cartão não cadastrado
    $msg = "Cartão não cadastrado no sistema.";
    logAccess($card_uid, $device_id, $device_name, false, $msg);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
$card = $cardResult->fetch_assoc();
if ($card['status'] != 'active') {
    $msg = "Cartão bloqueado ou inativo.";
    logAccess($card_uid, $device_id, $device_name, false, $msg);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Verificar permissão (tabela card_access)
$permStmt = $conn->prepare("SELECT * FROM card_access WHERE card_id = ? AND device_id = ?");
$permStmt->bind_param("ii", $card['id'], $device_id);
$permStmt->execute();
$permResult = $permStmt->get_result();
if ($permResult->num_rows == 0) {
    $msg = "Este cartão não tem permissão para acessar este local.";
    logAccess($card_uid, $device_id, $device_name, false, $msg);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Tudo OK - conceder acesso
$msg = "Acesso liberado para " . $device_name;
logAccess($card_uid, $device_id, $device_name, true, $msg);
echo json_encode(['success' => true, 'message' => $msg, 'open_door' => true]);

function logAccess($card_uid, $device_id, $device_name, $granted, $message) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO access_logs (card_uid, device_id, device_name, access_granted, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sissi", $card_uid, $device_id, $device_name, $granted, $message);
    $stmt->execute();
}
?>