<?php

require_once 'includes/auth.php';
requireLogin();
require_once 'includes/device_helpers.php';
require_once 'includes/icons.php';

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

$message = '';
$error = '';
$editDevice = null;
$isEditing = false;

// Excluir dispositivo

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Remove primeiro as permissões associadas
    $conn->query("DELETE FROM card_access WHERE device_id = $id");
    if ($conn->query("DELETE FROM devices WHERE id = $id")) {
        $message = "Dispositivo removido com sucesso.";
    } else {
        $error = "Erro ao remover dispositivo.";
    }
    header("Location: manage_devices.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// Buscar dispositivo para edição

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM devices WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editDevice = $result->fetch_assoc();
        $isEditing = true;
    } else {
        $error = "Dispositivo não encontrado.";
        header("Location: manage_devices.php?err=" . urlencode($error));
        exit;
    }
}

// Processar formulário (só edição de nome/localização - dispositivos são
// criados automaticamente quando um ESP32 real se conecta, nunca manualmente)

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $device_id = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;
    $device_name = trim($_POST['device_name']);
    $location = trim($_POST['location']);

    $errors = [];
    if (empty($device_name)) $errors[] = "O nome do dispositivo é obrigatório.";
    if (strlen($device_name) < 3) $errors[] = "Nome muito curto (mínimo 3 caracteres).";
    if ($device_id <= 0) $errors[] = "Dispositivo inválido.";

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE devices SET device_name=?, location=? WHERE id=?");
        $stmt->bind_param("ssi", $device_name, $location, $device_id);
        if ($stmt->execute()) {
            $message = "Dispositivo atualizado com sucesso.";
        } else {
            $error = "Erro ao atualizar: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }

    if ($message || $error) {
        header("Location: manage_devices.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
        exit;
    }
}

// Recuperar mensagens da URL

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);
if (isset($_GET['err'])) $error = urldecode($_GET['err']);

// Listar todos os dispositivos

$devices_result = $conn->query("SELECT * FROM devices ORDER BY id DESC");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AccessPoint - Dispositivos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(__DIR__ . '/assets/style.css'); ?>">

    <style>
        .mac-cell { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 12px; color: var(--n-500); }
        .last-seen { font-size: 12.5px; color: var(--n-500); }
        h1 { display: flex; align-items: center; gap: 10px; font-size: 20px; margin-bottom: 22px; }
        h1 .icon { width: 22px; height: 22px; color: var(--n-400); }
        h2.section-title { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; margin: 28px 0 14px; color: var(--n-900); }
        h2.section-title .icon { width: 16px; height: 16px; color: var(--n-400); }
    </style>
</head>
<body>
<div class="app-container">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <h1><?php echo icon('wifi'); ?>Dispositivos ESP32</h1>

        <!-- Filtro e busca -->
        <div class="filter-bar">
            <div class="search-box"><?php echo icon('search'); ?><input type="text" id="searchInput" placeholder="Nome ou localização..."></div>
            <div class="filter-group">
                <select id="statusFilter"><option value="">Todos os status</option><option value="online">Online</option><option value="offline">Offline</option></select>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if ($message): ?><div class="alert alert-success"><?php echo icon('check'); ?><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo icon('alert'); ?><?php echo $error; ?></div><?php endif; ?>

        <!-- Formulário de edição (dispositivos só aparecem aqui quando um ESP32 real se conecta) -->
        <?php if ($isEditing): ?>
        <div class="card-form">
            <h2><?php echo icon('edit'); ?>Editar Dispositivo</h2>
            <form method="POST">
                <input type="hidden" name="device_id" value="<?php echo $editDevice['id']; ?>">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Nome do dispositivo *</label>
                        <input type="text" name="device_name" value="<?php echo htmlspecialchars($editDevice['device_name']); ?>" required placeholder="ex: Sala de Robótica">
                    </div>
                    <div class="input-group">
                        <label>Localização</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($editDevice['location']); ?>" placeholder="ex: Sala 02">
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary"><?php echo icon('save'); ?>Salvar Alterações</button>
                    <a href="manage_devices.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Tabela de dispositivos -->
        <h2 class="section-title"><?php echo icon('file-text'); ?>Lista de Dispositivos</h2>
        <div style="overflow-x: auto;">
            <table class="data-table" id="devicesTable">
                <thead>
                    <tr><th>ID</th><th>Nome</th><th>MAC Address</th><th>Localização</th><th>Status</th><th>Última vez</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php if ($devices_result && $devices_result->num_rows > 0): ?>
                        <?php while ($device = $devices_result->fetch_assoc()): ?>
                            <?php
                                $realmenteOnline = isDeviceOnline($device);
                                $statusClasse = $realmenteOnline ? 'online' : 'offline';
                            ?>
                            <tr data-name="<?php echo strtolower($device['device_name']); ?>" data-location="<?php echo strtolower($device['location']); ?>" data-status="<?php echo $statusClasse; ?>">
                                <td><?php echo $device['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($device['device_name']); ?></strong></td>
                                <td class="mac-cell"><?php echo htmlspecialchars($device['mac_address'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($device['location'] ?: '—'); ?></td>
                                <td><span class="status-badge <?php echo $statusClasse; ?>"><?php echo icon('dot'); ?><?php echo $realmenteOnline ? 'Online' : 'Offline'; ?></span></td>
                                <td class="last-seen"><?php echo $device['last_seen'] ? date('d/m/Y H:i:s', strtotime($device['last_seen'])) : 'nunca'; ?></td>
                                <td class="actions-cell">
                                    <a href="?edit=<?php echo $device['id']; ?>" class="btn-small btn-edit"><?php echo icon('edit'); ?>Editar</a>
                                    <a href="access_logs.php?device=<?php echo $device['id']; ?>" class="btn-small"><?php echo icon('file-text'); ?>Logs</a>
                                    <button type="button" class="btn-small btn-danger" data-delete-url="manage_devices.php?delete=<?php echo $device['id']; ?>" data-name="<?php echo htmlspecialchars($device['device_name'], ENT_QUOTES); ?>"><?php echo icon('trash'); ?>Excluir</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align: center; color: var(--n-500);">Nenhum ESP32 conectado ainda. Ligue o dispositivo na rede Wi-Fi configurada no firmware e aguarde alguns segundos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="info-box">
            <h3>Registro automático</h3>
            <p>O ESP32 se identifica sozinho pelo MAC address na primeira vez que se conecta — não há cadastro manual. Renomeie e defina a localização por aqui depois que ele aparecer na lista.</p>
        </div>
    </main>
</div>

<script src="assets/script.js"></script>
<script>
    // Filtros
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    function filterTable() {
        const search = searchInput.value.toLowerCase();
        const status = statusFilter.value;
        document.querySelectorAll('#devicesTable tbody tr').forEach(row => {
            let show = true;
            if (status && row.dataset.status !== status) show = false;
            if (search && !row.dataset.name.includes(search) && !row.dataset.location.includes(search)) show = false;
            row.style.display = show ? '' : 'none';
        });
    }
    searchInput.addEventListener('keyup', filterTable);
    statusFilter.addEventListener('change', filterTable);
</script>
</body>
</html>
