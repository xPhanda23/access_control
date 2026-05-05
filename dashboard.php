<?php
require_once 'includes/auth.php';
requireLogin();

$role = $_SESSION['user_role'];
$fullname = $_SESSION['user_fullname'];
$conn = mysqli_connect('localhost', 'root', '', 'access_control');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Controle de Acesso</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <h2>🔐 AccessControl</h2>
            <ul>
                <li><a href="dashboard.php" class="active">🏠 Dashboard</a></li>
                <li><a href="manage_cards.php">💳 Cartões</a></li>
                <li><a href="manage_devices.php">📡 Dispositivos</a></li>
                <li><a href="access_logs.php">📜 Logs</a></li>
                <li><a href="simulator.php">🔧 Simulador ESP32</a></li>
                <li><a href="logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <h1>Olá, <?php echo htmlspecialchars($fullname); ?></h1>
            <p>Perfil: <?php echo ($role == 'admin') ? 'Administrador' : 'Direção'; ?></p>

            <div class="cards-stats">
                <div class="stat-card">
                    <h3>Cartões Ativos</h3>
                    <?php
                    $result = $conn->query("SELECT COUNT(*) AS total FROM cards WHERE status='active'");
                    $row = $result->fetch_assoc();
                    echo "<div class='stat-number'>{$row['total']}</div>";
                    ?>
                </div>
                <div class="stat-card">
                    <h3>Dispositivos</h3>
                    <?php
                    $result = $conn->query("SELECT COUNT(*) AS total FROM devices");
                    $row = $result->fetch_assoc();
                    echo "<div class='stat-number'>{$row['total']}</div>";
                    ?>
                </div>
                <div class="stat-card">
                    <h3>Logs de Hoje</h3>
                    <?php
                    $result = $conn->query("SELECT COUNT(*) AS total FROM access_logs WHERE DATE(created_at) = CURDATE()");
                    $row = $result->fetch_assoc();
                    echo "<div class='stat-number'>{$row['total']}</div>";
                    ?>
                </div>
            </div>

            <div class="quick-info">
                <h2>📌 Funcionamento do Sistema</h2>
                <ul>
                    <li>✔ Cartões associados a portas físicas (ESP32).</li>
                    <li>✔ Comunidade escolar usa cartão para acessar áreas permitidas.</li>
                    <li>✔ Direção gerencia cartões e dispositivos.</li>
                    <li>✔ Simulador integrado para testes sem hardware.</li>
                </ul>
            </div>
        </main>
    </div>
    <script src="assets/script.js"></script>
</body>
</html>