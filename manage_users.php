<?php

require_once 'includes/auth.php';
requireAdmin();

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

$message = '';
$error = '';
$editUser = null;
$isEditing = false;

// Excluir usuário (não permite excluir a si mesmo)

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id == $_SESSION['user_id']) {
        $error = "❌ Você não pode excluir seu próprio usuário.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "✅ Usuário excluído com sucesso!";
        } else {
            $error = "❌ Erro ao excluir usuário.";
        }
        $stmt->close();
    }
    header("Location: manage_users.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// Buscar usuário para edição

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM users WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editUser = $result->fetch_assoc();
        $isEditing = true;
    } else {
        $error = "❌ Usuário não encontrado.";
        header("Location: manage_users.php?err=" . urlencode($error));
        exit;
    }
}

// Processar formulário

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (empty($username)) $errors[] = "Nome de usuário é obrigatório.";
    if (empty($fullname)) $errors[] = "Nome completo é obrigatório.";
    if (!in_array($role, ['admin', 'direcao'])) $errors[] = "Perfil inválido.";
    if ($action == 'create' && empty($password)) $errors[] = "A senha é obrigatória para novos usuários.";
    if (!empty($password) && $password !== $confirm_password) $errors[] = "As senhas não coincidem.";

    if (empty($errors)) {
        if ($action == 'create') {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = "❌ Nome de usuário já existe. Escolha outro.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $hashed_password, $fullname, $role);
                if ($stmt->execute()) {
                    $message = "✅ Usuário criado com sucesso!";
                } else {
                    $error = "❌ Erro ao criar: " . $conn->error;
                }
                $stmt->close();
            }
            $check->close();
        } elseif ($action == 'update' && $user_id > 0) {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->bind_param("si", $username, $user_id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = "❌ Nome de usuário já está em uso por outro usuário.";
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username=?, password=?, fullname=?, role=? WHERE id=?");
                    $stmt->bind_param("ssssi", $username, $hashed_password, $fullname, $role, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username=?, fullname=?, role=? WHERE id=?");
                    $stmt->bind_param("sssi", $username, $fullname, $role, $user_id);
                }
                if ($stmt->execute()) {
                    $message = "✅ Usuário atualizado com sucesso!";
                    if ($user_id == $_SESSION['user_id']) {
                        $_SESSION['username'] = $username;
                        $_SESSION['user_fullname'] = $fullname;
                        $_SESSION['user_role'] = $role;
                    }
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
        header("Location: manage_users.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
        exit;
    }
}

// Mensagens da URL

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);
if (isset($_GET['err'])) $error = urldecode($_GET['err']);

$users_result = $conn->query("SELECT * FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AccessPoint - Gerenciar Usuários</title>
    <link rel="stylesheet" href="assets/style.css">

    <style>
        .info-note {
            background: linear-gradient(135deg, #fef3c7, #fffbeb);
            border-left: 5px solid #f59e0b;
            border-radius: 20px;
            padding: 18px 24px;
            margin-bottom: 28px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .info-note::before {
            content: "⚠️";
            font-size: 1.5rem;
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #f1f5f9;
            color: #1e293b;
        }
        .role-badge.admin {
            background: #fef3c7;
            color: #92400e;
        }
        .role-badge.direcao {
            background: #dbeafe;
            color: #1e40af;
        }
        .current-user-badge {
            background: #10b981;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 30px;
            margin-left: 10px;
            display: inline-block;
        }
        .actions-cell {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .btn-small {
            padding: 5px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            background: #f1f5f9;
            color: #1e293b;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            border: none;
        }
        .btn-small:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        .btn-edit {
            background: #e0e7ff;
            color: #3730a3;
        }
        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }
        .profile-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            margin-top: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #eef2ff;
        }
        .profile-card h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }
        .profile-item {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            transition: 0.2s;
        }
        .profile-item:hover {
            background: #f1f5f9;
            transform: translateY(-3px);
        }
        .profile-icon {
            font-size: 2rem;
            margin-bottom: 12px;
        }
        .profile-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .profile-desc {
            color: #475569;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        /* Modal */
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
            transition: 0.2s;
        }
        .modal-confirm {
            background: #ef4444;
            color: white;
        }
        .modal-cancel {
            background: #e2e8f0;
        }
        @media (max-width: 768px) {
            .info-note { font-size: 0.8rem; padding: 14px; }
            .actions-cell { flex-direction: row; flex-wrap: wrap; }
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
            display: inline-block;
            text-align: center;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 28px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: 0.2s;
        }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

    </style>
</head>
<body>
<div class="app-container">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 28px;">
            <h1 style="margin: 0;">👥 Gerenciar Usuários</h1>
            <span style="background: #e2e8f0; padding: 6px 14px; border-radius: 40px; font-size: 0.85rem;">
                Logado como: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            </span>
        </div>

        <div class="info-note">
            <div><strong>Apenas Administradores</strong> têm acesso a esta página. Aqui você pode criar, editar e excluir usuários do sistema (direção e outros administradores).</div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card-form">
            <h2 style="margin-bottom: 24px;"><?php echo $isEditing ? '✏️ Editar Usuário' : '➕ Criar Novo Usuário'; ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $isEditing ? 'update' : 'create'; ?>">
                <?php if ($isEditing): ?>
                    <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div class="input-group">
                        <label>👤 Nome de Usuário *</label>
                        <input type="text" name="username" value="<?php echo $isEditing ? htmlspecialchars($editUser['username']) : ''; ?>" required placeholder="ex: diretor_tafarel">
                    </div>
                    <div class="input-group">
                        <label>📛 Nome Completo *</label>
                        <input type="text" name="fullname" value="<?php echo $isEditing ? htmlspecialchars($editUser['fullname']) : ''; ?>" required placeholder="ex: Tafarel Cantuária">
                    </div>
                    <div class="input-group">
                        <label>🎭 Perfil *</label>
                        <select name="role" required>
                            <option value="admin" <?php echo ($isEditing && $editUser['role'] == 'admin') ? 'selected' : ''; ?>>👑 Administrador</option>
                            <option value="direcao" <?php echo ($isEditing && $editUser['role'] == 'direcao') ? 'selected' : ''; ?>>📋 Direção</option>
                        </select>
                        <small>Administrador: controle total | Direção: gerencia cartões e dispositivos</small>
                    </div>
                    <div class="input-group">
                        <label>🔒 Senha <?php echo $isEditing ? '(deixe em branco para manter)' : '*'; ?></label>
                        <input type="password" name="password" placeholder="<?php echo $isEditing ? 'Nova senha (opcional)' : 'Digite uma senha'; ?>">
                    </div>
                    <div class="input-group">
                        <label>✅ Confirmar Senha</label>
                        <input type="password" name="confirm_password" placeholder="Digite a senha novamente">
                    </div>
                </div>
                <div style="margin-top: 24px;">
                    <button type="submit" class="btn-primary"><?php echo $isEditing ? '💾 Salvar Alterações' : '➕ Criar Usuário'; ?></button>
                    <?php if ($isEditing): ?>
                        <a href="manage_users.php" class="btn-secondary" style="margin-left: 12px;">❌ Cancelar Edição</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h2 style="margin: 40px 0 20px;">📋 Usuários Cadastrados</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Usuário</th>
            <th>Nome Completo</th>
            <th>Perfil</th>
            <th>Data Cadastro</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($users_result && $users_result->num_rows > 0): ?>
            <?php while ($user = $users_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($user['username']); ?>
                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                            <span class="current-user-badge">VOCÊ</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                    <td>
                        <span class="role-badge <?php echo $user['role']; ?>">
                            <?php echo $user['role'] == 'admin' ? '👑 Administrador' : '📋 Direção'; ?>
                        </span>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                    <td class="actions-cell">
                        <a href="?edit=<?php echo $user['id']; ?>" class="btn-small btn-edit">✏️ Editar</a>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button class="btn-small btn-delete" data-id="<?php echo $user['id']; ?>" data-name="<?php echo htmlspecialchars($user['username']); ?>">🗑️ Excluir</button>
                        <?php else: ?>
                            <span class="btn-small" style="background: #cbd5e1; cursor: not-allowed;" title="Não pode excluir a si mesmo">🚫 Auto-exclusão</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align: center;">Nenhum usuário cadastrado ainda.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
        </div>

        <div class="profile-card">
            <h3>📌 Perfis do Sistema</h3>
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-icon">👑</div>
                    <div class="profile-title">Administrador</div>
                    <div class="profile-desc">Acesso TOTAL. Pode gerenciar usuários, cartões, dispositivos, logs e todas as configurações do sistema.</div>
                </div>
                <div class="profile-item">
                    <div class="profile-icon">📋</div>
                    <div class="profile-title">Direção</div>
                    <div class="profile-desc">Gerencia cartões, dispositivos e visualiza logs. Não acessa o gerenciamento de usuários.</div>
                </div>
                <div class="profile-item">
                    <div class="profile-icon">💡</div>
                    <div class="profile-title">Recomendação</div>
                    <div class="profile-desc">Mantenha sempre pelo menos um usuário com perfil Administrador ativo para não perder o controle do sistema.</div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h3>🗑️ Confirmar exclusão</h3>
        <p id="deleteMsg">Deseja excluir este usuário?</p>
        <div class="modal-buttons">
            <button id="confirmDelete" class="modal-confirm">Excluir</button>
            <button id="cancelDelete" class="modal-cancel">Cancelar</button>
        </div>
    </div>
</div>

<script>

    let deleteId = null;
    const deleteModal = document.getElementById('deleteModal');
    const deleteMsg = document.getElementById('deleteMsg');

    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            deleteId = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            deleteMsg.innerText = `Excluir o usuário "${name}"? Esta ação não pode ser desfeita.`;
            deleteModal.classList.add('active');
        });
    });

    document.getElementById('confirmDelete').onclick = () => {
        if (deleteId) window.location.href = `manage_users.php?delete=${deleteId}`;
    };
    document.getElementById('cancelDelete').onclick = () => deleteModal.classList.remove('active');
    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) deleteModal.classList.remove('active');
    });

</script>
</body>
</html>