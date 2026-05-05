<?php
require_once 'includes/auth.php';
requireLogin();

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $name = $_POST['device_name'];
        $loc = $_POST['location'];
        $stmt = $conn->prepare("INSERT INTO devices (device_name, location) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $loc);
        $stmt->execute();
    } elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $name = $_POST['device_name'];
        $loc = $_POST['location'];
        $stmt = $conn->prepare("UPDATE devices SET device_name=?, location=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $loc, $id);
        $stmt->execute();
    }
    header("Location: manage_devices.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM devices WHERE id=$id");
    header("Location: manage_devices.php");
    exit;
}

if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    $conn->query("UPDATE devices SET status = IF(status='online', 'offline', 'online'), last_seen = NOW() WHERE id=$id");
    header("Location: manage_devices.php");
    exit;
}

$result = $conn->query("SELECT * FROM devices ORDER BY id");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispositivos ESP32</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <h2>🔐 AccessControl</h2>
            <ul>
                <li><a href="dashboard.php">🏠 Dashboard</a></li>
                <li><a href="manage_cards.php">💳 Cartões</a></li>
                <li><a href="manage_devices.php" class="active">📡 Dispositivos</a></li>
                <li><a href="access_logs.php">📜 Logs</a></li>
                <li><a href="simulator.php">🔧 Simulador</a></li>
                <li><a href="logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <h1>Dispositivos ESP32 (Portas/Salas)</h1>

            <div class="card-form">
                <h2>➕ Adicionar Dispositivo</h2>
                <form method="POST">
                    <input type="hidden" name="add" value="1">
                    <div class="form-row">
                        <div class="input-group">
                            <label>Nome do dispositivo</label>
                            <input type="text" name="device_name" required>
                        </div>
                        <div class="input-group">
                            <label>Localização</label>
                            <input type="text" name="location" placeholder="Bloco, sala">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Adicionar</button>
                </form>
            </div>

            <h2>Lista de Dispositivos</h2>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>Nome</th><th>Local</th><th>Status</th><th>Última vez</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                    <?php while($dev = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $dev['id'] ?></td>
                            <td><?= htmlspecialchars($dev['device_name']) ?></td>
                            <td><?= htmlspecialchars($dev['location']) ?></td>
                            <td><span class="status-badge <?= $dev['status'] ?>"><?= $dev['status'] ?></span></td>
                            <td><?= $dev['last_seen'] ?: 'nunca' ?></td>
                            <td>
                                <a href="?toggle_status=<?= $dev['id'] ?>" class="btn-small">🔄 Simular</a>
                                <a href="?delete=<?= $dev['id'] ?>" class="btn-small btn-danger" onclick="return confirm('Remover dispositivo?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="info-box">
                ⚠️ Em ambiente real, o ESP32 enviaria requisição para <strong>api/verify_card.php</strong>. Use o Simulador para testar.
            </div>
        </main>
    </div>
    <script src="assets/script.js"></script>
</body>
</html>