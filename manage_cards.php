<?php
require_once 'includes/auth.php';
requireLogin();

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

// Adicionar novo cartão
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $card_uid = trim($_POST['card_uid']);
    $holder_name = trim($_POST['holder_name']);
    $holder_type = $_POST['holder_type'];
    $status = 'active';

    $stmt = $conn->prepare("INSERT INTO cards (card_uid, holder_name, holder_type, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $card_uid, $holder_name, $holder_type, $status);
    if ($stmt->execute()) {
        $card_id = $stmt->insert_id;
        if (isset($_POST['devices'])) {
            $devices = $_POST['devices'];
            foreach ($devices as $device_id) {
                $stmt2 = $conn->prepare("INSERT INTO card_access (card_id, device_id) VALUES (?, ?)");
                $stmt2->bind_param("ii", $card_id, $device_id);
                $stmt2->execute();
            }
        }
        $success = "Cartão adicionado com sucesso!";
    } else {
        $error = "Erro ao adicionar cartão: " . $conn->error;
    }
}

// Alternar status
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE cards SET status = IF(status='active', 'blocked', 'active') WHERE id=$id");
    header("Location: manage_cards.php");
    exit;
}

// Excluir
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM cards WHERE id=$id");
    header("Location: manage_cards.php");
    exit;
}

$result = $conn->query("SELECT * FROM cards ORDER BY created_at DESC");
$devices_result = $conn->query("SELECT id, device_name FROM devices");
$devices = [];
while ($d = $devices_result->fetch_assoc()) {
    $devices[] = $d;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Cartões</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <h2>🔐 AccessControl</h2>
            <ul>
                <li><a href="dashboard.php">🏠 Dashboard</a></li>
                <li><a href="manage_cards.php" class="active">💳 Cartões</a></li>
                <li><a href="manage_devices.php">📡 Dispositivos</a></li>
                <li><a href="access_logs.php">📜 Logs</a></li>
                <li><a href="simulator.php">🔧 Simulador</a></li>
                <li><a href="logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <h1>Gerenciamento de Cartões de Acesso</h1>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card-form">
                <h2>➕ Novo Cartão</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-row">
                        <div class="input-group">
                            <label>UID do Cartão</label>
                            <input type="text" name="card_uid" placeholder="ex: A1B2C3D4" required>
                        </div>
                        <div class="input-group">
                            <label>Nome do Portador</label>
                            <input type="text" name="holder_name" required>
                        </div>
                        <div class="input-group">
                            <label>Tipo</label>
                            <select name="holder_type">
                                <option value="aluno">Aluno</option>
                                <option value="professor">Professor</option>
                                <option value="funcionario">Funcionário</option>
                                <option value="visitante">Visitante</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <label>Permissões de Acesso</label>
                        <div class="checkbox-group">
                            <?php foreach ($devices as $device): ?>
                                <label><input type="checkbox" name="devices[]" value="<?php echo $device['id']; ?>"> <?php echo htmlspecialchars($device['device_name']); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar Cartão</button>
                </form>
            </div>

            <h2>📋 Cartões Cadastrados</h2>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>UID</th><th>Portador</th><th>Tipo</th><th>Status</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($card = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $card['id']; ?></td>
                            <td><code><?php echo htmlspecialchars($card['card_uid']); ?></code></td>
                            <td><?php echo htmlspecialchars($card['holder_name']); ?></td>
                            <td><?php echo $card['holder_type']; ?></td>
                            <td><span class="status-badge <?php echo $card['status']; ?>"><?php echo $card['status']; ?></span></td>
                            <td>
                                <a href="?toggle=<?php echo $card['id']; ?>" class="btn-small">🔁 Alternar Status</a>
                                <a href="?delete=<?php echo $card['id']; ?>" class="btn-small btn-danger" onclick="return confirm('Remover cartão?')">🗑️ Excluir</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="assets/script.js"></script>
</body>
</html>