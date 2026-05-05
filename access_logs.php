<?php
require_once 'includes/auth.php';
requireLogin();

$conn = mysqli_connect('localhost', 'root', '', 'access_control');
$result = $conn->query("SELECT * FROM access_logs ORDER BY created_at DESC LIMIT 200");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Acesso</title>
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
                <li><a href="access_logs.php" class="active">📜 Logs</a></li>
                <li><a href="simulator.php">🔧 Simulador</a></li>
                <li><a href="logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <h1>📜 Histórico de Tentativas de Acesso</h1>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Data/Hora</th><th>Cartão UID</th><th>Dispositivo</th><th>Acesso</th><th>Mensagem</th></tr>
                    </thead>
                    <tbody>
                    <?php while($log = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $log['created_at'] ?></td>
                            <td><code><?= htmlspecialchars($log['card_uid']) ?></code></td>
                            <td><?= htmlspecialchars($log['device_name']) ?></td>
                            <td><?= $log['access_granted'] ? '<span class="status-badge online">✅ Permitido</span>' : '<span class="status-badge offline">❌ Negado</span>' ?></td>
                            <td><?= htmlspecialchars($log['message']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>