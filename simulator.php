<?php

require_once 'includes/auth.php';
requireLogin();
require_once 'includes/device_helpers.php';

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

$simulation_result = null;
$card_uid = '';
$device_id = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simulate'])) {
    $card_uid = trim($_POST['card_uid']);
    $device_id = intval($_POST['device_id']);
    
    if (empty($card_uid)) {
        $simulation_result = ['success' => false, 'message' => '❌ Digite o UID do cartão.'];
    } elseif ($device_id <= 0) {
        $simulation_result = ['success' => false, 'message' => '❌ Selecione um dispositivo válido.'];
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
            $simulation_result = ['success' => false, 'message' => '❌ Erro de conexão com a API: ' . curl_error($ch)];
        } elseif ($httpCode != 200) {
            $simulation_result = ['success' => false, 'message' => "❌ API respondeu com código HTTP $httpCode."];
        } else {
            $api_response = json_decode($response, true);
            if ($api_response && isset($api_response['success'])) {
                $simulation_result = $api_response;
            } else {
                $simulation_result = ['success' => false, 'message' => '❌ Resposta inválida da API.'];
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
    <title>AccessPoint - Simulador ESP32</title>
    <link rel="stylesheet" href="assets/style.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #f1f5f9;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        .simulator-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .result-box {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            margin-top: 24px;
            border-left: 5px solid #4361ee;
            transition: 0.2s;
        }
        .result-success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .result-error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .device-badge {
            display: inline-block;
            background: #e2e8f0;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            color: #1e293b;
        }
        .quick-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .quick-card {
            background: #f1f5f9;
            border-radius: 16px;
            padding: 10px 16px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
        }
        .quick-card:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        .access-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .access-granted {
            background: #d1fae5;
            color: #065f46;
        }
        .access-denied {
            background: #fee2e2;
            color: #991b1b;
        }
        .logs-list {
            max-height: 320px;
            overflow-y: auto;
        }
        .log-item {
            padding: 12px 0;
            border-bottom: 1px solid #eef2ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .input-group label {
            font-weight: 600;
            color: #1e293b;
        }
        .input-group input, .input-group select {
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            font-size: 0.95rem;
            transition: 0.2s;
        }
        .input-group input:focus, .input-group select:focus {
            border-color: #4361ee;
            outline: none;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
        }
        .btn-primary {
            background: linear-gradient(105deg, #4361ee, #3a56d4);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .sidebar { width: 100%; position: relative; height: auto; }
            .app-container { flex-direction: column; }
            .quick-cards { justify-content: center; }
            .log-item { flex-direction: column; align-items: flex-start; }
        }

    </style>
</head>
<body>
<div class="app-container">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 24px;">
            <h1 style="margin: 0;">🔧 Simulador ESP32</h1>
            <span class="device-badge"><?php echo count($devices); ?> dispositivo(s) online</span>
        </div>

        <!-- Formulário principal -->

        <div class="simulator-card">
            <h2 style="margin-bottom: 20px;">📡 Testar Acesso com Cartão RFID</h2>
            <?php if (empty($devices)): ?>
                <p>⚠️ Nenhum ESP32 online no momento. Ligue o dispositivo (Wi-Fi + firmware configurado) e aguarde alguns segundos para ele aparecer aqui.</p>
            <?php else: ?>
            <form method="POST" id="simulatorForm">
                <div class="form-grid">
                    <div class="input-group">
                        <label>🆔 UID do Cartão *</label>
                        <input type="text" name="card_uid" id="card_uid" required
                               value="<?php echo htmlspecialchars($card_uid); ?>"
                               placeholder="Digite o UID do cartão aqui">
                    </div>
                    <div class="input-group">
                        <label>📡 Dispositivo Online (Porta/ESP32) *</label>
                        <select name="device_id" id="device_id" required>
                            <option value="">-- Selecione --</option>
                            <?php foreach ($devices as $dev): ?>
                                <option value="<?php echo $dev['id']; ?>" <?php echo ($device_id == $dev['id']) ? 'selected' : ''; ?>>
                                    🔵 <?php echo htmlspecialchars($dev['device_name'] . ' - ' . ($dev['location'] ?: 'local não definido')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="simulate" class="btn-primary">🔄 Testar Leitura (Enviar para API)</button>
            </form>
            <?php endif; ?>

            <?php if ($simulation_result !== null): ?>
                <div class="result-box <?php echo $simulation_result['success'] ? 'result-success' : 'result-error'; ?>">
                    <h3 style="margin-bottom: 12px;">📟 Resposta do ESP32</h3>
                    <p><strong>Status:</strong> 
                        <span style="color: <?php echo $simulation_result['success'] ? '#10b981' : '#ef4444'; ?>; font-weight: bold;">
                            <?php echo $simulation_result['success'] ? '✅ ACESSO LIBERADO' : '❌ ACESSO NEGADO'; ?>
                        </span>
                    </p>
                    <p><strong>Mensagem:</strong> <?php echo htmlspecialchars($simulation_result['message']); ?></p>
                    <?php if ($simulation_result['success']): ?>
                        <div style="background: #d1fae5; padding: 10px 15px; border-radius: 12px; margin-top: 12px;">
                            🔓 <strong>Ação simulada:</strong> Trava eletrônica destravada por 5 segundos.<br>
                        </div>
                    <?php else: ?>
                        <div style="background: #fee2e2; padding: 10px 15px; border-radius: 12px; margin-top: 12px;">
                            ⚠️ Acesso negado. Nenhum acionamento da trava.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Histórico de testes recentes -->

        <div class="simulator-card">
            <h2 style="margin-bottom: 16px;">🕒 Últimos Testes Realizados</h2>
            <?php if ($recent_tests && $recent_tests->num_rows > 0): ?>
                <div class="logs-list">
                    <?php while($log = $recent_tests->fetch_assoc()): ?>
                        <div class="log-item">
                            <div>
                                <code><?php echo htmlspecialchars($log['card_uid']); ?></code>
                                → <strong><?php echo htmlspecialchars($log['device_name']); ?></strong>
                            </div>
                            <div>
                                <span class="access-badge <?php echo $log['access_granted'] ? 'access-granted' : 'access-denied'; ?>">
                                    <?php echo $log['access_granted'] ? '✅ Permitido' : '❌ Negado'; ?>
                                </span>
                                <span style="font-size: 0.7rem; color: #666; margin-left: 8px;">
                                    <?php echo date('d/m H:i', strtotime($log['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>Nenhum teste registrado ainda. Faça uma simulação.</p>
            <?php endif; ?>
        </div>

        <!-- Bloco técnico -->

        <div style="background: #eef2ff; border-radius: 20px; padding: 24px; margin-top: 20px;">
            <h3 style="margin-bottom: 16px;">📡 Como o ESP32 se comunica com o sistema?</h3>
            <ul style="line-height: 1.7; margin-left: 20px;">
                <li>O ESP32 se identifica sozinho pelo endereço MAC (sem cadastro manual) e avisa que está online a cada poucos segundos.</li>
                <li>Ao ler uma tag no leitor RFID, envia POST para <code>api/verify_card.php</code> com JSON <code>{"card_uid":"UID", "mac_address":"AA:BB:CC:.."}</code>.</li>
                <li>A API verifica se o cartão está ativo e tem permissão para aquele dispositivo.</li>
                <li>Se autorizado, responde <code>{"success":true, "open_door":true, "message":"..."}</code> e o ESP32 destrava a porta (servo a 90°).</li>
                <li>Este simulador testa exatamente essa mesma API, contra um dispositivo realmente online, sem precisar aproximar um cartão fisicamente do leitor.</li>
                <li>Toda tentativa fica registrada nos logs acima.</li>
            </ul>
        </div>
    </main>
</div>

<script>
    (function() {

        const uidInput = document.getElementById('card_uid');
        const quickCards = document.querySelectorAll('.quick-card');
        
        quickCards.forEach(card => {
            card.addEventListener('click', function() {
                if (this.hasAttribute('data-clear')) {
                    uidInput.value = '';
                } else {
                    const uid = this.getAttribute('data-uid');
                    if (uid) {
                        uidInput.value = uid;
                    } else {
                        const match = this.innerText.match(/\(([^)]+)\)/);
                        if (match) uidInput.value = match[1];
                    }
                }
                uidInput.dispatchEvent(new Event('change'));
            });
        });
        
        // Validação antes de enviar o formulário

        const form = document.getElementById('simulatorForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const uid = uidInput.value.trim();
                const device = document.getElementById('device_id').value;
                if (!uid) {
                    e.preventDefault();
                    alert('Por favor, informe o UID do cartão.');
                    uidInput.focus();
                } else if (!device || device === '') {
                    e.preventDefault();
                    alert('Por favor, selecione um dispositivo.');
                    document.getElementById('device_id').focus();
                }
            });
        }
    })();

</script>
</body>
</html>