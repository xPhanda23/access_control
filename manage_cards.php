<?php

require_once 'includes/auth.php';
requireLogin();
require_once 'includes/device_helpers.php';
require_once 'includes/icons.php';

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

$message = '';
$error = '';
$editCard = null;
$isEditing = false;

// Ações via GET

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM card_access WHERE card_id = $id");
    if ($conn->query("DELETE FROM cards WHERE id = $id")) {
        $message = "Cartão removido com sucesso.";
    } else {
        $error = "Erro ao remover cartão.";
    }
    header("Location: manage_cards.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE cards SET status = IF(status='active', 'blocked', 'active') WHERE id=$id");
    $message = "Status alterado com sucesso.";
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
        $message = "Cartão duplicado com sucesso (UID ajustado).";
    } else {
        $error = "Cartão original não encontrado.";
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
        $error = "Cartão não encontrado.";
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
                $error = "UID já cadastrado.";
            } else {
                $stmt = $conn->prepare("INSERT INTO cards (card_uid, holder_name, holder_type, status) VALUES (?,?,?,?)");
                $stmt->bind_param("ssss", $card_uid, $holder_name, $holder_type, $status);
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    foreach ($devices as $d) {
                        $conn->query("INSERT INTO card_access (card_id, device_id) VALUES ($new_id, " . intval($d) . ")");
                    }
                    $message = "Cartão criado com sucesso.";
                } else {
                    $error = "Erro ao criar: " . $conn->error;
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
                $error = "UID já usado por outro cartão.";
            } else {
                $stmt = $conn->prepare("UPDATE cards SET card_uid=?, holder_name=?, holder_type=?, status=? WHERE id=?");
                $stmt->bind_param("ssssi", $card_uid, $holder_name, $holder_type, $status, $card_id);
                if ($stmt->execute()) {
                    $conn->query("DELETE FROM card_access WHERE card_id = $card_id");
                    foreach ($devices as $d) {
                        $conn->query("INSERT INTO card_access (card_id, device_id) VALUES ($card_id, " . intval($d) . ")");
                    }
                    $message = "Cartão atualizado com sucesso.";
                } else {
                    $error = "Erro ao atualizar: " . $conn->error;
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

// Dispositivos online agora (usados no leitor de cadastro de tag abaixo)
$online_devices = [];
$all_devices_status = $conn->query("SELECT id, device_name, status, last_seen FROM devices ORDER BY device_name");
while ($d = $all_devices_status->fetch_assoc()) {
    if (isDeviceOnline($d)) $online_devices[] = $d;
}

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
    <title>AccessPoint - Cartões</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(__DIR__ . '/assets/style.css'); ?>">

    <style>
        h1 { display: flex; align-items: center; gap: 10px; font-size: 20px; margin-bottom: 22px; }
        h1 .icon { width: 22px; height: 22px; color: var(--n-400); }
        h2.section-title { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; margin: 28px 0 14px; color: var(--n-900); }
        h2.section-title .icon { width: 16px; height: 16px; color: var(--n-400); }
        .enroll-box { background: var(--n-50); border: 1px dashed var(--n-300); border-radius: var(--radius-sm); padding: 14px; margin-top: 10px; }
        .enroll-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-size: 13px; color: var(--n-700); }
        .enroll-row select { padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--n-300); font-size: 13px; }
        .enroll-status { font-size: 12.5px; font-weight: 600; margin-top: 8px; }
        .enroll-status.waiting { color: var(--warning); }
        .enroll-status.success { color: #166534; }
        .enroll-status.error { color: #991b1b; }
    </style>
</head>
<body>
<div class="app-container">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <h1><?php echo icon('credit-card'); ?>Cartões de Acesso</h1>

        <!-- Barra de filtros e busca -->

        <div class="filter-bar">
            <div class="search-box">
                <?php echo icon('search'); ?><input type="text" id="searchInput" placeholder="Nome ou UID...">
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

        <?php if ($message): ?><div class="alert alert-success"><?php echo icon('check'); ?><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo icon('alert'); ?><?php echo $error; ?></div><?php endif; ?>

        <!-- Formulário -->

        <div class="card-form">
            <h2><?php echo $isEditing ? icon('edit') . 'Editar Cartão' : icon('plus') . 'Novo Cartão'; ?></h2>
            <form method="POST" id="cardForm">
                <input type="hidden" name="action" value="<?php echo $isEditing ? 'update' : 'create'; ?>">
                <?php if ($isEditing): ?><input type="hidden" name="card_id" value="<?php echo $editCard['id']; ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="input-group">
                        <label>UID do Cartão *</label>
                        <input type="text" name="card_uid" id="card_uid" value="<?php echo $isEditing ? htmlspecialchars($editCard['card_uid']) : ''; ?>" required placeholder="ex: A1B2C3D4">
                        <div class="enroll-box">
                            <div class="enroll-row">
                                <span>Ler UID pelo leitor:</span>
                                <select id="enrollDevice" <?php echo empty($online_devices) ? 'disabled' : ''; ?>>
                                    <?php if (empty($online_devices)): ?>
                                        <option value="">Nenhum ESP32 online</option>
                                    <?php else: ?>
                                        <option value="">-- Selecione o dispositivo --</option>
                                        <?php foreach ($online_devices as $d): ?>
                                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['device_name']); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <button type="button" id="enrollStartBtn" class="btn-small btn-edit" <?php echo empty($online_devices) ? 'disabled' : ''; ?>><?php echo icon('wifi'); ?>Iniciar leitura</button>
                                <button type="button" id="enrollCancelBtn" class="btn-small btn-danger" style="display:none;"><?php echo icon('x'); ?>Cancelar</button>
                            </div>
                            <div id="enrollStatus" class="enroll-status"></div>
                        </div>
                        <div class="enroll-box">
                            <div class="enroll-row">
                                <span>Gravar este UID num cartão mágico:</span>
                                <select id="writeDevice" <?php echo empty($online_devices) ? 'disabled' : ''; ?>>
                                    <?php if (empty($online_devices)): ?>
                                        <option value="">Nenhum ESP32 online</option>
                                    <?php else: ?>
                                        <option value="">-- Selecione o dispositivo --</option>
                                        <?php foreach ($online_devices as $d): ?>
                                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['device_name']); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <button type="button" id="writeStartBtn" class="btn-small btn-edit" <?php echo empty($online_devices) ? 'disabled' : ''; ?>><?php echo icon('lock'); ?>Gravar no cartão</button>
                                <button type="button" id="writeCancelBtn" class="btn-small btn-danger" style="display:none;"><?php echo icon('x'); ?>Cancelar</button>
                            </div>
                            <div id="writeStatus" class="enroll-status">Requer UID de 8 caracteres hex e um cartão do tipo "mágico" (Gen1A/Gen2/CUID).</div>
                        </div>
                    </div>
                    <div class="input-group"><label>Nome do Portador *</label><input type="text" name="holder_name" value="<?php echo $isEditing ? htmlspecialchars($editCard['holder_name']) : ''; ?>" required></div>
                    <div class="input-group"><label>Tipo</label><select name="holder_type"><?php $tipos = ['aluno'=>'Aluno','professor'=>'Professor','funcionario'=>'Funcionário','visitante'=>'Visitante']; foreach($tipos as $k=>$v){ $sel = ($isEditing && $editCard['holder_type']==$k)?'selected':''; echo "<option value='$k' $sel>$v</option>"; } ?></select></div>
                    <div class="input-group"><label>Status</label><select name="status"><option value="active" <?php echo ($isEditing && $editCard['status']=='active')?'selected':''; ?>>Ativo</option><option value="blocked" <?php echo ($isEditing && $editCard['status']=='blocked')?'selected':''; ?>>Bloqueado</option></select></div>
                </div>
                <div><label>Permissões (portas que este cartão pode abrir)</label>
                    <div class="checkbox-group">
                        <?php if ($devices_list && $devices_list->num_rows > 0): $devices_list->data_seek(0); while($dev = $devices_list->fetch_assoc()): ?>
                            <label><input type="checkbox" name="devices[]" value="<?php echo $dev['id']; ?>" <?php if ($isEditing && in_array($dev['id'], $card_permissions)) echo 'checked'; ?>> <?php echo htmlspecialchars($dev['device_name']); ?></label>
                        <?php endwhile; else: ?><p>Nenhum dispositivo. <a href="manage_devices.php">Criar agora</a></p><?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 22px;"><?php echo icon('save'); ?>Salvar Cartão</button>
            </form>
        </div>

        <!-- Tabela -->

        <h2 class="section-title"><?php echo icon('file-text'); ?>Cartões Cadastrados</h2>
        <div style="overflow-x: auto;">
            <table class="data-table" id="cardsTable">
                <thead><tr><th>ID</th><th>UID</th><th>Portador</th><th>Tipo</th><th>Status</th><th>Permissões</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php if ($cards_result && $cards_result->num_rows > 0): ?>
                        <?php while($card = $cards_result->fetch_assoc()): ?>
                            <tr data-type="<?php echo $card['holder_type']; ?>" data-status="<?php echo $card['status']; ?>" data-name="<?php echo strtolower($card['holder_name']); ?>" data-uid="<?php echo $card['card_uid']; ?>">
                                <td><?php echo $card['id']; ?></td>
                                <td><code><?php echo htmlspecialchars($card['card_uid']); ?></code> <button class="btn-small copy-uid" data-uid="<?php echo $card['card_uid']; ?>"><?php echo icon('copy'); ?></button></td>
                                <td><?php echo htmlspecialchars($card['holder_name']); ?></td>
                                <td><?php echo $card['holder_type']; ?></td>
                                <td><span class="status-badge <?php echo $card['status']; ?>"><?php echo $card['status']=='active'?'Ativo':'Bloqueado'; ?></span></td>
                                <td><?php echo htmlspecialchars($card['devices_names'] ?: 'Nenhuma'); ?></td>
                                <td class="actions-cell">
                                    <a href="?edit=<?php echo $card['id']; ?>" class="btn-small btn-edit"><?php echo icon('edit'); ?>Editar</a>
                                    <button class="btn-small btn-toggle" data-id="<?php echo $card['id']; ?>"><?php echo icon('refresh-cw'); ?>Alternar</button>
                                    <button class="btn-small" data-duplicate="<?php echo $card['id']; ?>"><?php echo icon('copy'); ?>Duplicar</button>
                                    <button class="btn-small btn-test" data-uid="<?php echo $card['card_uid']; ?>"><?php echo icon('wifi'); ?>Testar</button>
                                    <a href="access_logs.php?card=<?php echo $card['card_uid']; ?>" class="btn-small"><?php echo icon('file-text'); ?>Logs</a>
                                    <button type="button" class="btn-small btn-danger" data-delete-url="manage_cards.php?delete=<?php echo $card['id']; ?>" data-name="<?php echo htmlspecialchars($card['holder_name'], ENT_QUOTES); ?>"><?php echo icon('trash'); ?>Excluir</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?><tr><td colspan="7" style="text-align:center; color: var(--n-500);">Nenhum cartão cadastrado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Modal Teste -->
<div id="testModal" class="modal"><div class="modal-content"><h3><?php echo icon('wifi'); ?>Teste de Acesso</h3><p id="testMsg">Verificando...</p><div class="modal-buttons"><button id="closeTestModal" class="modal-cancel">Fechar</button></div></div></div>

<script src="assets/script.js"></script>
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

    document.querySelectorAll('.copy-uid').forEach(btn => btn.addEventListener('click', () => { navigator.clipboard.writeText(btn.dataset.uid); mostrarModalMensagem('UID copiado.'); }));

    // Alternar status via fetch (sem recarregar automaticamente)

    document.querySelectorAll('.btn-toggle').forEach(btn => btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const id = btn.dataset.id;
        const res = await fetch(`manage_cards.php?toggle=${id}`);
        if (res.ok) location.reload();
        else mostrarModalMensagem('Erro ao alterar status.');
    }));

    // Duplicar

    document.querySelectorAll('[data-duplicate]').forEach(btn => btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.dataset.duplicate;
        mostrarModalConfirmacao('Duplicar cartão?', () => window.location.href = `manage_cards.php?duplicate=${id}`);
    }));

    // Teste com API

    const testModal = document.getElementById('testModal');
    const testMsg = document.getElementById('testMsg');
    document.querySelectorAll('.btn-test').forEach(btn => btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const cardUid = btn.dataset.uid;
        const deviceId = prompt('ID do dispositivo a testar:', '1');
        if (!deviceId) return;
        testMsg.innerText = 'Verificando...';
        testModal.classList.add('active');
        try {
            const res = await fetch('api/verify_card.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ card_uid:cardUid, device_id:parseInt(deviceId) }) });
            const data = await res.json();
            testMsg.innerHTML = data.success ? `<strong style="color:var(--success)">Acesso permitido</strong><br>${data.message}` : `<strong style="color:var(--danger)">Acesso negado</strong><br>${data.message}`;
        } catch(err) { testMsg.innerHTML = 'Erro ao comunicar com a API.'; }
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
        const modal = document.createElement('div'); modal.className = 'modal active'; modal.innerHTML = `<div class="modal-content"><h3>Confirmação</h3><p>${texto}</p><div class="modal-buttons"><button class="modal-cancel">Não</button><button class="modal-confirm">Sim</button></div></div>`;
        document.body.appendChild(modal);
        modal.querySelector('.modal-confirm').onclick = () => { modal.remove(); callback(); };
        modal.querySelector('.modal-cancel').onclick = () => modal.remove();
    }

    // Cadastro de tag pelo leitor RFID do ESP32 (modo de configuração)

    const enrollDevice = document.getElementById('enrollDevice');
    const enrollStartBtn = document.getElementById('enrollStartBtn');
    const enrollCancelBtn = document.getElementById('enrollCancelBtn');
    const enrollStatus = document.getElementById('enrollStatus');
    const cardUidInput = document.getElementById('card_uid');

    let enrollPollTimer = null;
    let enrollTimeoutTimer = null;
    let enrollDeviceId = null;

    function setEnrollStatus(texto, tipo) {
        enrollStatus.textContent = texto;
        enrollStatus.className = 'enroll-status' + (tipo ? ' ' + tipo : '');
    }

    function stopEnrollPolling() {
        if (enrollPollTimer) { clearInterval(enrollPollTimer); enrollPollTimer = null; }
        if (enrollTimeoutTimer) { clearTimeout(enrollTimeoutTimer); enrollTimeoutTimer = null; }
    }

    function resetEnrollUI() {
        stopEnrollPolling();
        enrollDeviceId = null;
        enrollStartBtn.style.display = '';
        enrollStartBtn.disabled = false;
        enrollCancelBtn.style.display = 'none';
        enrollDevice.disabled = false;
    }

    if (enrollStartBtn) {
        enrollStartBtn.addEventListener('click', async () => {
            const deviceId = enrollDevice.value;
            if (!deviceId) { setEnrollStatus('Selecione um dispositivo online primeiro.', 'error'); return; }

            enrollDeviceId = deviceId;
            enrollStartBtn.disabled = true;
            enrollDevice.disabled = true;

            try {
                const res = await fetch('api/enroll_start.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ device_id: deviceId })
                });
                const data = await res.json();
                if (!data.success) {
                    setEnrollStatus(data.message || 'Não foi possível iniciar a leitura.', 'error');
                    resetEnrollUI();
                    return;
                }
            } catch (e) {
                setEnrollStatus('Erro ao comunicar com o servidor.', 'error');
                resetEnrollUI();
                return;
            }

            setEnrollStatus('Aproxime o cartão do leitor no ESP32...', 'waiting');
            enrollStartBtn.style.display = 'none';
            enrollCancelBtn.style.display = '';

            enrollPollTimer = setInterval(pollEnroll, 1500);
            enrollTimeoutTimer = setTimeout(async () => {
                setEnrollStatus('Tempo esgotado. Tente novamente.', 'error');
                await cancelEnroll();
            }, 30000);
        });
    }

    async function pollEnroll() {
        if (!enrollDeviceId) return;
        try {
            const res = await fetch(`api/enroll_poll.php?device_id=${enrollDeviceId}`);
            const data = await res.json();
            if (data.success && data.uid) {
                cardUidInput.value = data.uid;
                setEnrollStatus('UID capturado: ' + data.uid, 'success');
                resetEnrollUI();
            }
        } catch (e) {
            // silencioso - tenta de novo no próximo ciclo
        }
    }

    async function cancelEnroll() {
        if (enrollDeviceId) {
            try {
                await fetch('api/enroll_cancel.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ device_id: enrollDeviceId })
                });
            } catch (e) {}
        }
        resetEnrollUI();
    }

    if (enrollCancelBtn) {
        enrollCancelBtn.addEventListener('click', async () => {
            setEnrollStatus('Leitura cancelada.', '');
            await cancelEnroll();
        });
    }

    // Gravar o UID digitado/lido num cartão mágico (Gen1A/Gen2/CUID)

    const writeDevice = document.getElementById('writeDevice');
    const writeStartBtn = document.getElementById('writeStartBtn');
    const writeCancelBtn = document.getElementById('writeCancelBtn');
    const writeStatus = document.getElementById('writeStatus');

    let writePollTimer = null;
    let writeTimeoutTimer = null;
    let writeDeviceId = null;

    function setWriteStatus(texto, tipo) {
        writeStatus.textContent = texto;
        writeStatus.className = 'enroll-status' + (tipo ? ' ' + tipo : '');
    }

    function stopWritePolling() {
        if (writePollTimer) { clearInterval(writePollTimer); writePollTimer = null; }
        if (writeTimeoutTimer) { clearTimeout(writeTimeoutTimer); writeTimeoutTimer = null; }
    }

    function resetWriteUI() {
        stopWritePolling();
        writeDeviceId = null;
        writeStartBtn.style.display = '';
        writeStartBtn.disabled = false;
        writeCancelBtn.style.display = 'none';
        writeDevice.disabled = false;
    }

    if (writeStartBtn) {
        writeStartBtn.addEventListener('click', async () => {
            const deviceId = writeDevice.value;
            const uid = cardUidInput.value.trim().toUpperCase();

            if (!deviceId) { setWriteStatus('Selecione um dispositivo online primeiro.', 'error'); return; }
            if (!/^[0-9A-F]{8}$/.test(uid)) {
                setWriteStatus('UID precisa ter 8 caracteres hexadecimais para gravar. Atual: "' + uid + '".', 'error');
                return;
            }

            writeDeviceId = deviceId;
            writeStartBtn.disabled = true;
            writeDevice.disabled = true;

            try {
                const res = await fetch('api/write_start.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ device_id: deviceId, uid: uid })
                });
                const data = await res.json();
                if (!data.success) {
                    setWriteStatus(data.message || 'Não foi possível iniciar a gravação.', 'error');
                    resetWriteUI();
                    return;
                }
            } catch (e) {
                setWriteStatus('Erro ao comunicar com o servidor.', 'error');
                resetWriteUI();
                return;
            }

            setWriteStatus('Aproxime o cartão mágico a ser gravado com o UID ' + uid + '...', 'waiting');
            writeStartBtn.style.display = 'none';
            writeCancelBtn.style.display = '';

            writePollTimer = setInterval(pollWrite, 1500);
            writeTimeoutTimer = setTimeout(async () => {
                setWriteStatus('Tempo esgotado. Tente novamente.', 'error');
                await cancelWrite();
            }, 30000);
        });
    }

    async function pollWrite() {
        if (!writeDeviceId) return;
        try {
            const res = await fetch(`api/write_poll.php?device_id=${writeDeviceId}`);
            const data = await res.json();
            if (data.success && data.result) {
                const ok = data.result.startsWith('OK:');
                const texto = data.result.replace(/^(OK|ERRO):/, '');
                setWriteStatus(texto, ok ? 'success' : 'error');
                resetWriteUI();
            }
        } catch (e) {
            // silencioso - tenta de novo no próximo ciclo
        }
    }

    async function cancelWrite() {
        if (writeDeviceId) {
            try {
                await fetch('api/write_cancel.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ device_id: writeDeviceId })
                });
            } catch (e) {}
        }
        resetWriteUI();
    }

    if (writeCancelBtn) {
        writeCancelBtn.addEventListener('click', async () => {
            setWriteStatus('Gravação cancelada.', '');
            await cancelWrite();
        });
    }
</script>
</body>
</html>
