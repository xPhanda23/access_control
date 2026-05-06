<?php
// ============================================
// manage_devices.php - Gerenciamento de Dispositivos ESP32
// ============================================

require_once 'includes/auth.php';
requireLogin();

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

// ============================================
// VARIÁVEIS E AÇÕES
// ============================================
$message = '';
$error = '';
$editDevice = null;
$isEditing = false;

// Alternar status online/offline (simulação)
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    $conn->query("UPDATE devices SET status = IF(status='online', 'offline', 'online'), last_seen = NOW() WHERE id = $id");
    $message = "✅ Status do dispositivo alterado com sucesso!";
    header("Location: manage_devices.php?msg=" . urlencode($message));
    exit;
}

// Excluir dispositivo
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Remove primeiro as permissões associadas
    $conn->query("DELETE FROM card_access WHERE device_id = $id");
    if ($conn->query("DELETE FROM devices WHERE id = $id")) {
        $message = "✅ Dispositivo removido com sucesso!";
    } else {
        $error = "❌ Erro ao remover dispositivo.";
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
        $error = "❌ Dispositivo não encontrado.";
        header("Location: manage_devices.php?err=" . urlencode($error));
        exit;
    }
}

// Processar formulário (criar / atualizar)

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $device_id = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;
    $device_name = trim($_POST['device_name']);
    $location = trim($_POST['location']);
    $status = $_POST['status'] ?? 'offline';

    $errors = [];
    if (empty($device_name)) $errors[] = "O nome do dispositivo é obrigatório.";
    if (strlen($device_name) < 3) $errors[] = "Nome muito curto (mínimo 3 caracteres).";

    if (empty($errors)) {
        if ($action == 'create') {
            $stmt = $conn->prepare("INSERT INTO devices (device_name, location, status) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $device_name, $location, $status);
            if ($stmt->execute()) {
                $message = "✅ Dispositivo criado com sucesso!";
            } else {
                $error = "❌ Erro ao criar: " . $conn->error;
            }
            $stmt->close();
        } elseif ($action == 'update' && $device_id > 0) {
            $stmt = $conn->prepare("UPDATE devices SET device_name=?, location=?, status=? WHERE id=?");
            $stmt->bind_param("sssi", $device_name, $location, $status, $device_id);
            if ($stmt->execute()) {
                $message = "✅ Dispositivo atualizado com sucesso!";
            } else {
                $error = "❌ Erro ao atualizar: " . $conn->error;
            }
            $stmt->close();
        }
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
    <title>Gerenciar Dispositivos - AccessControl</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ========== LAYOUT ESPECÍFICO ========== */
        .status-badge.online { background: #d1fae5; color: #065f46; }
        .status-badge.offline { background: #fee2e2; color: #991b1b; }
        .last-seen { font-size: 0.8rem; color: #6c757d; }
        .actions-cell { display: flex; flex-wrap: wrap; gap: 8px; }
        .btn-small { padding: 5px 12px; border-radius: 30px; font-size: 0.75rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; border: none; background: #f1f5f9; color: #1e293b; transition: 0.2s; }
        .btn-edit { background: #e0e7ff; color: #3730a3; }
        .btn-toggle { background: #fef3c7; color: #92400e; }
        .btn-delete { background: #fee2e2; color: #991b1b; }
        .btn-primary { background: linear-gradient(105deg, #4361ee, #3a56d4); color: white; border: none; padding: 12px 28px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .filter-bar { background: white; border-radius: 24px; padding: 20px 28px; margin-bottom: 28px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; align-items: center; }
        .search-box { display: flex; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 40px; padding: 6px 16px; }
        .search-box input { border: none; background: transparent; padding: 10px 0; width: 240px; outline: none; }
        .filter-group select { padding: 10px 16px; border-radius: 30px; border: 1px solid #e2e8f0; background: white; }
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; visibility: hidden; opacity: 0; transition: 0.2s; }
        .modal.active { visibility: visible; opacity: 1; }
        .modal-content { background: white; border-radius: 32px; padding: 32px; max-width: 450px; width: 90%; text-align: center; }
        .modal-buttons { display: flex; gap: 16px; justify-content: center; margin-top: 28px; }
        .modal-confirm { background: #ef4444; color: white; border: none; padding: 10px 24px; border-radius: 40px; cursor: pointer; }
        .modal-cancel { background: #e2e8f0; border: none; padding: 10px 24px; border-radius: 40px; cursor: pointer; }
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-box input { width: 100%; }
        }
    </style>
</head>
<body>
<div class="app-container">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="margin-bottom: 24px;">📡 Dispositivos ESP32 (Portas/Salas)</h1>

        <!-- Filtro e busca -->
        <div class="filter-bar">
            <div class="search-box">🔍 <input type="text" id="searchInput" placeholder="Nome ou localização..."></div>
            <div class="filter-group">
                <select id="statusFilter"><option value="">Todos os status</option><option value="online">Online</option><option value="offline">Offline</option></select>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <!-- Formulário de criação/edição -->
        <div class="card-form">
            <h2 style="margin-bottom: 24px;"><?php echo $isEditing ? '✏️ Editar Dispositivo' : '➕ Adicionar Dispositivo'; ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $isEditing ? 'update' : 'create'; ?>">
                <?php if ($isEditing): ?><input type="hidden" name="device_id" value="<?php echo $editDevice['id']; ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="input-group">
                        <label>📛 Nome do dispositivo *</label>
                        <input type="text" name="device_name" value="<?php echo $isEditing ? htmlspecialchars($editDevice['device_name']) : ''; ?>" required placeholder="ex: Sala de Robótica">
                    </div>
                    <div class="input-group">
                        <label>📍 Localização</label>
                        <input type="text" name="location" value="<?php echo $isEditing ? htmlspecialchars($editDevice['location']) : ''; ?>" placeholder="ex: Bloco B, Sala 12">
                    </div>
                    <div class="input-group">
                        <label>⚡ Status (inicial)</label>
                        <select name="status">
                            <option value="online" <?php echo ($isEditing && $editDevice['status']=='online') ? 'selected' : ''; ?>>Online</option>
                            <option value="offline" <?php echo ($isEditing && $editDevice['status']=='offline') ? 'selected' : ''; ?>>Offline</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="margin-top: 20px;">💾 Salvar Dispositivo</button>
            </form>
        </div>

        <!-- Tabela de dispositivos -->
        <h2 style="margin: 32px 0 16px;">📋 Lista de Dispositivos</h2>
        <div style="overflow-x: auto;">
            <table class="data-table" id="devicesTable">
                <thead>
                    <tr><th>ID</th><th>Nome</th><th>Localização</th><th>Status</th><th>Última vez</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php if ($devices_result && $devices_result->num_rows > 0): ?>
                        <?php while ($device = $devices_result->fetch_assoc()): ?>
                            <tr data-name="<?php echo strtolower($device['device_name']); ?>" data-location="<?php echo strtolower($device['location']); ?>" data-status="<?php echo $device['status']; ?>">
                                <td><?php echo $device['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($device['device_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($device['location'] ?: '—'); ?></td>
                                <td><span class="status-badge <?php echo $device['status']; ?>"><?php echo $device['status'] == 'online' ? '🔵 Online' : '🔴 Offline'; ?></span></td>
                                <td class="last-seen"><?php echo $device['last_seen'] ? date('d/m/Y H:i:s', strtotime($device['last_seen'])) : 'nunca'; ?></td>
                                <td class="actions-cell">
                                    <a href="?edit=<?php echo $device['id']; ?>" class="btn-small btn-edit">✏️ Editar</a>
                                    <button class="btn-small btn-toggle" data-id="<?php echo $device['id']; ?>">🔄 Simular Online/Offline</button>
                                    <a href="access_logs.php?device=<?php echo $device['id']; ?>" class="btn-small">📜 Logs associados</a>
                                    <button class="btn-small btn-delete" data-id="<?php echo $device['id']; ?>" data-name="<?php echo htmlspecialchars($device['device_name']); ?>">🗑️ Excluir</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">Nenhum dispositivo cadastrado. Adicione um acima.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mensagem de informação sobre integração real -->
        <div class="info-card" style="background: #eef2ff; border-radius: 20px; padding: 20px; margin-top: 30px;">
            <p>⚠️ <strong>Ambiente de gestão</strong> – Os dispositivos ESP32 reais se comunicarão via <code>api/verify_card.php</code>.<br>
            Teste o fluxo completo usando o <strong>Simulador ESP32</strong> no menu ao lado.</p>
        </div>
    </main>
</div>

<!-- Modal para exclusão -->
<div id="deleteModal" class="modal"><div class="modal-content"><h3>🗑️ Confirmar exclusão</h3><p id="deleteMsg">Deseja excluir este dispositivo?</p><div class="modal-buttons"><button id="confirmDelete" class="modal-confirm">Excluir</button><button id="cancelDelete" class="modal-cancel">Cancelar</button></div></div></div>

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

    // Alternar status via fetch (simula online/offline e recarrega)
    document.querySelectorAll('.btn-toggle').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const id = btn.getAttribute('data-id');
            const res = await fetch(`manage_devices.php?toggle_status=${id}`);
            if (res.ok) location.reload();
            else alert('Erro ao alternar status');
        });
    });

    // Modal de exclusão personalizado
    let deleteId = null;
    const deleteModal = document.getElementById('deleteModal');
    const deleteMsg = document.getElementById('deleteMsg');
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            deleteId = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            deleteMsg.innerText = `Excluir o dispositivo "${name}"? Todas as permissões serão removidas.`;
            deleteModal.classList.add('active');
        });
    });
    document.getElementById('confirmDelete').onclick = () => {
        if (deleteId) window.location.href = `manage_devices.php?delete=${deleteId}`;
    };
    document.getElementById('cancelDelete').onclick = () => deleteModal.classList.remove('active');
    deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) deleteModal.classList.remove('active'); });
</script>
</body>
</html>