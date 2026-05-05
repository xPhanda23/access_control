<?php
require_once 'includes/auth.php';
requireLogin();

$conn = mysqli_connect('localhost', 'root', '', 'access_control');
$devices = $conn->query("SELECT id, device_name FROM devices");
$result_msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $card_uid = $_POST['card_uid'];
    $device_id = $_POST['device_id'];
    // simula chamada à API (pode usar file_get_contents com contexto)
    $postData = json_encode(['card_uid' => $card_uid, 'device_id' => $device_id]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $postData
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents('http://localhost/access_control/api/verify_card.php', false, $context);
    $result = json_decode($response, true);
    if ($result && isset($result['success'])) {
        $result_msg = $result['success'] ? "✅ ACESSO PERMITIDO: " . $result['message'] : "❌ ACESSO NEGADO: " . $result['message'];
    } else {
        $result_msg = "Erro na comunicação com a API. Verifique se o endpoint está correto.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulador ESP32</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <h2>🔐 AccessControl</h2>
            <ul>
                <li><a href="dashboard.php">🏠 Dashboard</a></li>
                <li><a href="manage_cards.php">💳 Cartões</a></li>
                <li><a href="manage_devices.php">📡 Dispositivos</a></li>
                <li><a href="access_logs.php">📜 Logs</a></li>
                <li><a href="simulator.php" class="active">🔧 Simulador</a></li>
                <li><a href="logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <h1>🔧 Simulador de Leitor ESP32</h1>
            <p>Simula a leitura de um cartão por um dispositivo ESP32. Teste permissões sem hardware real.</p>
            <div class="simulator-box">
                <form method="POST">
                    <div class="form-row">
                        <div class="input-group">
                            <label>UID do Cartão</label>
                            <input type="text" name="card_uid" placeholder="ex: 12345678, ABCDEF12" required>
                        </div>
                        <div class="input-group">
                            <label>Dispositivo (porta/sala)</label>
                            <select name="device_id">
                                <?php while($d = $devices->fetch_assoc()): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['device_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Simular Leitura</button>
                </form>
                <?php if ($result_msg): ?>
                    <div class="simulator-result"><?= $result_msg ?></div>
                <?php endif; ?>
            </div>
            <div class="info-box">
                <strong>📌 Cartões de exemplo:</strong><br>
                - <code>12345678</code> (João Aluno) → só Biblioteca<br>
                - <code>87654321</code> (Professora Maria) → Laboratório e Biblioteca<br>
                - <code>ABCDEF12</code> (Funcionário José) → bloqueado
            </div>
        </main>
    </div>
</body>
</html>