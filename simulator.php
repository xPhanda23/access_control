<?php

require_once 'includes/auth.php';
requireLogin();
require_once 'includes/device_helpers.php';
require_once 'includes/icons.php';

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

$simulation_result = null;
$card_uid = '';
$device_id = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simulate'])) {
    $card_uid = trim($_POST['card_uid']);
    $device_id = intval($_POST['device_id']);

    if (empty($card_uid)) {
        $simulation_result = ['success' => false, 'message' => 'Digite o UID do cartão.'];
    } elseif ($device_id <= 0) {
        $simulation_result = ['success' => false, 'message' => 'Selecione um dispositivo válido.'];
    } else {
        $api_url = 'http://' . $_SERVER['HTTP_HOST'] . '/access_point/api/verify_card.php';
        $postData = json_encode(['card_uid' => $card_uid, 'device_id' => $device_id]);

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $simulation_result = ['success' => false, 'message' => 'Erro de conexão com a API: ' . curl_error($ch)];
        } elseif ($httpCode != 200) {
            $simulation_result = ['success' => false, 'message' => "API respondeu com código HTTP $httpCode."];
        } else {
            $api_response = json_decode($response, true);
            if ($api_response && isset($api_response['success'])) {
                $simulation_result = $api_response;
            } else {
                $simulation_result = ['success' => false, 'message' => 'Resposta inválida da API.'];
            }
        }
        curl_close($ch);
    }
}

$all_devices = $conn->query("SELECT id, device_name, location, status, last_seen FROM devices ORDER BY device_name");
$devices = [];
while ($d = $all_devices->fetch_assoc()) {
    if (isDeviceOnline($d)) $devices[] = $d;
}
$recent_tests = $conn->query("SELECT card_uid, device_name, access_granted, message, created_at FROM access_logs ORDER BY created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>AccessPoint - Simulador</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(__DIR__ . '/assets/style.css'); ?>">

    <style>
        h1 { display: flex; align-items: center; gap: 10px; font-size: 20px; }
        h1 .icon { width: 22px; height: 22px; color: var(--n-400); }
        .header-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 22px; gap: 10px; }
        .device-badge { display: inline-block; background: var(--n-100); border: 1px solid var(--n-200); padding: 5px 14px; border-radius: var(--radius-pill); font-size: 12.5px; color: var(--n-600); }
        .result-box { background: var(--n-50); border-radius: var(--radius); padding: 18px 20px; margin-top: 20px; border-left: 3px solid var(--accent); }
        .result-success { border-left-color: var(--success); background: var(--success-soft); }
        .result-error { border-left-color: var(--danger); background: var(--danger-soft); }
        .result-box h3 { display: flex; align-items: center; gap: 8px; font-size: 14px; margin-bottom: 10px; }
        .result-box .icon { width: 16px; height: 16px; }
        .result-detail { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.6); padding: 10px 14px; border-radius: var(--radius-sm); margin-top: 10px; font-size: 13px; }
        .logs-list { max-height: 320px; overflow-y: auto; }
        .log-item { padding: 12px 0; border-bottom: 1px solid var(--n-100); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .log-item:last-child { border-bottom: none; }
        .log-time { font-size: 12px; color: var(--n-400); margin-left: 8px; }
        @media (max-width: 768px) {
            .log-item { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="app-container">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="header-row">
            <h1><?php echo icon('terminal'); ?>Simulador ESP32</h1>
            <span class="device-badge"><?php echo count($devices); ?> dispositivo(s) online</span>
        </div>

        <!-- Formulário principal -->

        <div class="card-form">
            <h2><?php echo icon('wifi'); ?>Testar Acesso com Cartão RFID</h2>
            <?php if (empty($devices)): ?>
                <p style="color: var(--n-500);">Nenhum ESP32 online no momento. Ligue o dispositivo e aguarde alguns segundos para ele aparecer aqui.</p>
            <?php else: ?>
            <form method="POST" id="simulatorForm">
                <div class="form-grid">
                    <div class="input-group">
                        <label>UID do Cartão *</label>
                        <input type="text" name="card_uid" id="card_uid" required
                               value="<?php echo htmlspecialchars($card_uid); ?>"
                               placeholder="Digite o UID do cartão aqui">
                    </div>
                    <div class="input-group">
                        <label>Dispositivo Online *</label>
                        <select name="device_id" id="device_id" required>
                            <option value="">-- Selecione --</option>
                            <?php foreach ($devices as $dev): ?>
                                <option value="<?php echo $dev['id']; ?>" <?php echo ($device_id == $dev['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dev['device_name'] . ' - ' . ($dev['location'] ?: 'local não definido')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="simulate" class="btn btn-primary"><?php echo icon('refresh-cw'); ?>Testar Leitura</button>
            </form>
            <?php endif; ?>

            <?php if ($simulation_result !== null): ?>
                <div class="result-box <?php echo $simulation_result['success'] ? 'result-success' : 'result-error'; ?>">
                    <h3><?php echo $simulation_result['success'] ? icon('check') : icon('x'); ?>
                        <?php echo $simulation_result['success'] ? 'Acesso liberado' : 'Acesso negado'; ?>
                    </h3>
                    <p><?php echo htmlspecialchars($simulation_result['message']); ?></p>
                    <?php if ($simulation_result['success']): ?>
                        <div class="result-detail"><?php echo icon('unlock'); ?>Trava destravada por 5 segundos.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Histórico de testes recentes -->

        <div class="card-form">
            <h2><?php echo icon('clock'); ?>Últimos Testes Realizados</h2>
            <?php if ($recent_tests && $recent_tests->num_rows > 0): ?>
                <div class="logs-list">
                    <?php while($log = $recent_tests->fetch_assoc()): ?>
                        <div class="log-item">
                            <div>
                                <code><?php echo htmlspecialchars($log['card_uid']); ?></code>
                                &rarr; <strong><?php echo htmlspecialchars($log['device_name']); ?></strong>
                            </div>
                            <div>
                                <span class="status-badge <?php echo $log['access_granted'] ? 'active' : 'blocked'; ?>">
                                    <?php echo $log['access_granted'] ? 'Permitido' : 'Negado'; ?>
                                </span>
                                <span class="log-time"><?php echo date('d/m H:i', strtotime($log['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="color: var(--n-500);">Nenhum teste registrado ainda.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    const uidInput = document.getElementById('card_uid');
    const form = document.getElementById('simulatorForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const uid = uidInput.value.trim();
            const device = document.getElementById('device_id').value;
            if (!uid) {
                e.preventDefault();
                alert('Por favor, informe o UID do cartão.');
                uidInput.focus();
            } else if (!device) {
                e.preventDefault();
                alert('Por favor, selecione um dispositivo.');
                document.getElementById('device_id').focus();
            }
        });
    }
</script>
</body>
</html>
