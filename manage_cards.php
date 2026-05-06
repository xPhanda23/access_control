<?php
// ============================================
// manage_cards.php - Gerenciamento de Cartões
// Versão com layout profissional e sem alert()
// ============================================

require_once 'includes/auth.php';
requireLogin();

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

// ============================================
// TRATAMENTO DE MENSAGENS E AÇÕES
// ============================================
$message = '';
$error = '';
$editCard = null;
$isEditing = false;

// Ações via GET
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM card_access WHERE card_id = $id");
    if ($conn->query("DELETE FROM cards WHERE id = $id")) {
        $message = "✅ Cartão removido com sucesso!";
    } else {
        $error = "❌ Erro ao remover cartão.";
    }
    header("Location: manage_cards.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE cards SET status = IF(status='active', 'blocked', 'active') WHERE id=$id");
    $message = "✅ Status alterado com sucesso!";
    header("Location: manage_cards.php?msg=" . urlencode($message));
    exit;
}

if (isset($_GET['duplicate']) && is_numeric($_GET['duplicate'])) {
    $id = intval($_GET['duplicate']);
    $card = $conn->query("SELECT * FROM cards WHERE id=$id")->fetch_assoc();
    if ($card) {
        $new_uid = $card['card_uid'] . '_copy';
        $stmt = $conn->prepare("INSERT INTO cards (card_uid, holder_name, holder_type, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $new_uid, $card['holder_name'], $card['holder_type'], $card['status']);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $perms = $conn->query("SELECT device_id FROM card_access WHERE card_id=$id");
        while ($perm = $perms->fetch_assoc()) {
            $conn->query("INSERT INTO card_access (card_id, device_id) VALUES ($new_id, {$perm['device_id']})");
        }
        $message = "✅ Cartão duplicado com sucesso! (UID ajustado)";
    } else {
        $error = "❌ Cartão original não encontrado.";
    }
    header("Location: manage_cards.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM cards WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editCard = $result->fetch_assoc();
        $isEditing = true;
    } else {
        $error = "❌ Cartão não encontrado.";
        header("Location: manage_cards.php?err=" . urlencode($error));
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $card_id = isset($_POST['card_id']) ? intval($_POST['card_id']) : 0;
    $card_uid = trim($_POST['card_uid']);
    $holder_name = trim($_POST['holder_name']);
    $holder_type = $_POST['holder_type'];
    $status = $_POST['status'];
    $devices = isset($_POST['devices']) ? $_POST['devices'] : [];

    $errors = [];
    if (empty($card_uid)) $errors[] = "UID é obrigatório.";
    if (empty($holder_name)) $errors[] = "Nome do portador é obrigatório.";
    if (strlen($card_uid) < 4) $errors[] = "UID muito curto (mínimo 4 caracteres).";
    if (!in_array($holder_type, ['aluno','professor','funcionario','visitante'])) $errors[] = "Tipo inválido.";

    if (empty($errors)) {
        if ($action == 'create') {
            $check = $conn->prepare("SELECT id FROM cards WHERE card_uid = ?");
            $check->bind_param("s", $card_uid);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = "❌ UID já cadastrado.";
            } else {
                $stmt = $conn->prepare("INSERT INTO cards (card_uid, holder_name, holder_type, status) VALUES (?,?,?,?)");
                $stmt->bind_param("ssss", $card_uid, $holder_name, $holder_type, $status);
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    foreach ($devices as $d) {
                        $conn->query("INSERT INTO card_access (card_id, device_id) VALUES ($new_id, " . intval($d) . ")");
                    }
                    $message = "✅ Cartão criado com sucesso!";
                } else {
                    $error = "❌ Erro ao criar: " . $conn->error;
                }
                $stmt->close();
            }
            $check->close();
        } elseif ($action == 'update' && $card_id > 0) {
            $check = $conn->prepare("SELECT id FROM cards WHERE card_uid = ? AND id != ?");
            $check->bind_param("si", $card_uid, $card_id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = "❌ UID já usado por outro cartão.";
            } else {
                $stmt = $conn->prepare("UPDATE cards SET card_uid=?, holder_name=?, holder_type=?, status=? WHERE id=?");
                $stmt->bind_param("ssssi", $card_uid, $holder_name, $holder_type, $status, $card_id);
                if ($stmt->execute()) {
                    $conn->query("DELETE FROM card_access WHERE card_id = $card_id");
                    foreach ($devices as $d) {
                        $conn->query("INSERT INTO card_access (card_id, device_id) VALUES ($card_id, " . intval($d) . ")");
                    }
                    $message = "✅ Cartão atualizado com sucesso!";
                } else {
                    $error = "❌ Erro ao atualizar: " . $conn->error;
                }
                $stmt->close();
            }
            $check->close();
        }
    } else {
        $error = implode("<br>", $errors);
    }

    if ($message || $error) {
        header("Location: manage_cards.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
        exit;
    }
}

// Recuperar mensagens
if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);
if (isset($_GET['err'])) $error = urldecode($_GET['err']);

// Listar cartões
$cards_result = $conn->query("
    SELECT c.*, GROUP_CONCAT(d.device_name SEPARATOR ', ') as devices_names
    FROM cards c
    LEFT JOIN card_access ca ON ca.card_id = c.id
    LEFT JOIN devices d ON d.id = ca.device_id
    GROUP BY c.id
    ORDER BY c.id DESC
");

$devices_list = $conn->query("SELECT id, device_name FROM devices ORDER BY device_name");
$card_permissions = [];
if ($isEditing && $editCard) {
    $permRes = $conn->query("SELECT device_id FROM card_access WHERE card_id = {$editCard['id']}");
    while ($r = $permRes->fetch_assoc()) $card_permissions[] = $r['device_id'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Cartões - AccessControl</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ========== LAYOUT COMPLETO E ALINHADO ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #f1f5f9;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        /* Cards e containers */
        .card-form, .filter-bar, .info-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 32px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .filter-bar {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            padding: 6px 16px;
        }
        .search-box input {
            border: none;
            background: transparent;
            padding: 10px 0;
            width: 240px;
            outline: none;
        }
        .filter-group select {
            padding: 10px 16px;
            border-radius: 30px;
            border: 1px solid #e2e8f0;
            background: white;
            font-size: 0.9rem;
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
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            background: #f8fafc;
            padding: 16px;
            border-radius: 20px;
            margin-top: 8px;
        }
        .btn-primary {
            background: linear-gradient(105deg, #4361ee, #3a56d4);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67,97,238,0.3);
        }
        .data-table {
            width: 100%;
            background: white;
            border-radius: 24px;
            overflow-x: auto;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #eef2ff;
        }
        .data-table th {
            background: #f8fafc;
            font-weight: 600;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }
        .status-badge.blocked {
            background: #fee2e2;
            color: #991b1b;
        }
        .actions-cell {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .btn-small {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            text-decoration: none;
            background: #f1f5f9;
            color: #1e293b;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            border: none;
        }
        .btn-small:hover {
            background: #e2e8f0;
        }
        .btn-edit { background: #e0e7ff; color: #3730a3; }
        .btn-toggle { background: #fef3c7; color: #92400e; }
        .btn-delete { background: #fee2e2; color: #991b1b; }
        .alert {
            padding: 16px 20px;
            border-radius: 20px;
            margin: 20px 0;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 5px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 5px solid #ef4444; }
        /* Modal personalizado */
        .modal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            visibility: hidden;
            opacity: 0;
            transition: 0.2s;
        }
        .modal.active {
            visibility: visible;
            opacity: 1;
        }
        .modal-content {
            background: white;
            border-radius: 32px;
            padding: 32px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 40px rgba(0,0,0,0.2);
        }
        .modal-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 28px;
        }
        .modal-buttons button {
            padding: 10px 24px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .modal-confirm { background: #ef4444; color: white; }
        .modal-cancel { background: #e2e8f0; }
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .sidebar { width: 100%; position: relative; height: auto; }
            .app-container { flex-direction: column; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-box input { width: 100%; }
        }
    </style>
</head>
<body>
<div class="app-container">
    
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="margin-bottom: 24px;">💳 Gerenciamento de Cartões</h1>

        <!-- Barra de filtros e busca -->
        <div class="filter-bar">
            <div class="search-box">
                🔍 <input type="text" id="searchInput" placeholder="Nome ou UID...">
            </div>
            <div class="filter-group">
                <select id="typeFilter"><option value="">Todos os tipos</option>
                    <option value="aluno">Aluno</option><option value="professor">Professor</option>
                    <option value="funcionario">Funcionário</option><option value="visitante">Visitante</option></select>
                <select id="statusFilter"><option value="">Todos status</option>
                    <option value="active">Ativo</option><option value="blocked">Bloqueado</option></select>
            </div>
        </div>

        <!-- Mensagens de retorno -->
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <!-- Formulário -->
        <div class="card-form">
            <h2 style="margin-bottom: 24px;"><?php echo $isEditing ? '✏️ Editar Cartão' : '➕ Novo Cartão'; ?></h2>
            <form method="POST" id="cardForm">
                <input type="hidden" name="action" value="<?php echo $isEditing ? 'update' : 'create'; ?>">
                <?php if ($isEditing): ?><input type="hidden" name="card_id" value="<?php echo $editCard['id']; ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="input-group"><label>🔑 UID do Cartão *</label><input type="text" name="card_uid" value="<?php echo $isEditing ? htmlspecialchars($editCard['card_uid']) : ''; ?>" required placeholder="ex: A1B2C3D4"></div>
                    <div class="input-group"><label>👤 Nome do Portador *</label><input type="text" name="holder_name" value="<?php echo $isEditing ? htmlspecialchars($editCard['holder_name']) : ''; ?>" required></div>
                    <div class="input-group"><label>📌 Tipo</label><select name="holder_type"><?php $tipos = ['aluno'=>'Aluno','professor'=>'Professor','funcionario'=>'Funcionário','visitante'=>'Visitante']; foreach($tipos as $k=>$v){ $sel = ($isEditing && $editCard['holder_type']==$k)?'selected':''; echo "<option value='$k' $sel>$v</option>"; } ?></select></div>
                    <div class="input-group"><label>⚡ Status</label><select name="status"><option value="active" <?php echo ($isEditing && $editCard['status']=='active')?'selected':''; ?>>Ativo</option><option value="blocked" <?php echo ($isEditing && $editCard['status']=='blocked')?'selected':''; ?>>Bloqueado</option></select></div>
                </div>
                <div><label>🔓 Permissões (portas que este cartão pode abrir)</label>
                    <div class="checkbox-group">
                        <?php if ($devices_list && $devices_list->num_rows > 0): $devices_list->data_seek(0); while($dev = $devices_list->fetch_assoc()): ?>
                            <label><input type="checkbox" name="devices[]" value="<?php echo $dev['id']; ?>" <?php if ($isEditing && in_array($dev['id'], $card_permissions)) echo 'checked'; ?>> <?php echo htmlspecialchars($dev['device_name']); ?></label>
                        <?php endwhile; else: ?><p>Nenhum dispositivo. <a href="manage_devices.php">Criar agora</a></p><?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="margin-top: 24px;">💾 Salvar Cartão</button>
            </form>
        </div>

        <!-- Tabela -->
        <h2 style="margin: 32px 0 16px;">📋 Cartões Cadastrados</h2>
        <div style="overflow-x: auto;">
            <table class="data-table" id="cardsTable">
                <thead><tr><th>ID</th><th>UID</th><th>Portador</th><th>Tipo</th><th>Status</th><th>Permissões</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php if ($cards_result && $cards_result->num_rows > 0): ?>
                        <?php while($card = $cards_result->fetch_assoc()): ?>
                            <tr data-type="<?php echo $card['holder_type']; ?>" data-status="<?php echo $card['status']; ?>" data-name="<?php echo strtolower($card['holder_name']); ?>" data-uid="<?php echo $card['card_uid']; ?>">
                                <td><?php echo $card['id']; ?></td>
                                <td><code><?php echo htmlspecialchars($card['card_uid']); ?></code> <button class="btn-small copy-uid" data-uid="<?php echo $card['card_uid']; ?>">📋 Copiar</button></td>
                                <td><?php echo htmlspecialchars($card['holder_name']); ?></td>
                                <td><?php echo $card['holder_type']; ?></td>
                                <td><span class="status-badge <?php echo $card['status']; ?>"><?php echo $card['status']=='active'?'Ativo':'Bloqueado'; ?></span></td>
                                <td><?php echo htmlspecialchars($card['devices_names'] ?: 'Nenhuma'); ?></td>
                                <td class="actions-cell">
                                    <a href="?edit=<?php echo $card['id']; ?>" class="btn-small btn-edit">✏️ Editar</a>
                                    <button class="btn-small btn-toggle" data-id="<?php echo $card['id']; ?>">🔄 Alternar</button>
                                    <button class="btn-small" data-duplicate="<?php echo $card['id']; ?>">📋 Duplicar</button>
                                    <button class="btn-small btn-test" data-uid="<?php echo $card['card_uid']; ?>">📡 Testar</button>
                                    <a href="access_logs.php?card=<?php echo $card['card_uid']; ?>" class="btn-small">📜 Logs</a>
                                    <button class="btn-small btn-delete" data-id="<?php echo $card['id']; ?>" data-name="<?php echo htmlspecialchars($card['holder_name']); ?>">🗑️ Excluir</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?><tr><td colspan="7">Nenhum cartão cadastrado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Modal Exclusão -->
<div id="deleteModal" class="modal"><div class="modal-content"><h3>🗑️ Confirmar exclusão</h3><p id="deleteMsg">Deseja excluir este cartão?</p><div class="modal-buttons"><button id="confirmDelete">Excluir</button><button id="cancelDelete">Cancelar</button></div></div></div>
<!-- Modal Teste -->
<div id="testModal" class="modal"><div class="modal-content"><h3>📡 Teste de Acesso</h3><p id="testMsg">Verificando...</p><div class="modal-buttons"><button id="closeTestModal">Fechar</button></div></div></div>

<script>
    // Filtros
    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    function filterTable() {
        const search = searchInput.value.toLowerCase();
        const type = typeFilter.value;
        const status = statusFilter.value;
        document.querySelectorAll('#cardsTable tbody tr').forEach(row => {
            let show = true;
            if (type && row.dataset.type !== type) show = false;
            if (status && row.dataset.status !== status) show = false;
            if (search && !row.dataset.name.includes(search) && !row.dataset.uid.includes(search)) show = false;
            row.style.display = show ? '' : 'none';
        });
    }
    searchInput.addEventListener('keyup', filterTable);
    typeFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);

    // Copiar UID
    document.querySelectorAll('.copy-uid').forEach(btn => btn.addEventListener('click', () => { navigator.clipboard.writeText(btn.dataset.uid); mostrarModalMensagem('✅ UID copiado!'); }));

    // Alternar status via fetch (sem recarregar automaticamente)
    document.querySelectorAll('.btn-toggle').forEach(btn => btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const id = btn.dataset.id;
        const res = await fetch(`manage_cards.php?toggle=${id}`);
        if (res.ok) location.reload();
        else mostrarModalMensagem('❌ Erro ao alterar status');
    }));

    // Duplicar
    document.querySelectorAll('[data-duplicate]').forEach(btn => btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.dataset.duplicate;
        mostrarModalConfirmacao('Duplicar cartão?', () => window.location.href = `manage_cards.php?duplicate=${id}`);
    }));

    // Exclusão modal
    let deleteId = null;
    const deleteModal = document.getElementById('deleteModal');
    const deleteMsg = document.getElementById('deleteMsg');
    document.querySelectorAll('.btn-delete').forEach(btn => btn.addEventListener('click', (e) => {
        e.preventDefault();
        deleteId = btn.dataset.id;
        deleteMsg.innerText = `Excluir cartão de ${btn.dataset.name}?`;
        deleteModal.classList.add('active');
    }));
    document.getElementById('confirmDelete').onclick = () => { if(deleteId) window.location.href = `manage_cards.php?delete=${deleteId}`; };
    document.getElementById('cancelDelete').onclick = () => deleteModal.classList.remove('active');
    deleteModal.addEventListener('click', (e) => { if(e.target === deleteModal) deleteModal.classList.remove('active'); });

    // Teste com API
    const testModal = document.getElementById('testModal');
    const testMsg = document.getElementById('testMsg');
    document.querySelectorAll('.btn-test').forEach(btn => btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const cardUid = btn.dataset.uid;
        const deviceId = prompt('Digite o ID do dispositivo (ex: 1 para Sala de Robótica):', '1');
        if (!deviceId) return;
        testMsg.innerText = 'Verificando...';
        testModal.classList.add('active');
        try {
            const res = await fetch('api/verify_card.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ card_uid:cardUid, device_id:parseInt(deviceId) }) });
            const data = await res.json();
            testMsg.innerHTML = data.success ? `✅ ACESSO PERMITIDO!<br>${data.message}` : `❌ ACESSO NEGADO!<br>${data.message}`;
        } catch(err) { testMsg.innerHTML = '❌ Erro ao comunicar com a API.'; }
    }));
    document.getElementById('closeTestModal').onclick = () => testModal.classList.remove('active');
    testModal.addEventListener('click', (e) => { if(e.target === testModal) testModal.classList.remove('active'); });

    function mostrarModalMensagem(texto) {
        const modal = document.createElement('div'); modal.className = 'modal active'; modal.innerHTML = `<div class="modal-content"><p>${texto}</p><div class="modal-buttons"><button class="modal-cancel">OK</button></div></div>`;
        document.body.appendChild(modal);
        modal.querySelector('button').onclick = () => modal.remove();
        setTimeout(() => modal.remove(), 2000);
    }
    function mostrarModalConfirmacao(texto, callback) {
        const modal = document.createElement('div'); modal.className = 'modal active'; modal.innerHTML = `<div class="modal-content"><h3>Confirmação</h3><p>${texto}</p><div class="modal-buttons"><button class="modal-confirm">Sim</button><button class="modal-cancel">Não</button></div></div>`;
        document.body.appendChild(modal);
        modal.querySelector('.modal-confirm').onclick = () => { modal.remove(); callback(); };
        modal.querySelector('.modal-cancel').onclick = () => modal.remove();
    }
</script>
</body>
</html>