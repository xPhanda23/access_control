<?php

require_once 'includes/auth.php';
requireAdmin();
require_once 'includes/icons.php';

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

$message = '';
$error = '';
$editUser = null;
$isEditing = false;

// Excluir usuário (não permite excluir a si mesmo)

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id == $_SESSION['user_id']) {
        $error = "Você não pode excluir seu próprio usuário.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Usuário excluído com sucesso.";
        } else {
            $error = "Erro ao excluir usuário.";
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
        $error = "Usuário não encontrado.";
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
                $error = "Nome de usuário já existe. Escolha outro.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $hashed_password, $fullname, $role);
                if ($stmt->execute()) {
                    $message = "Usuário criado com sucesso.";
                } else {
                    $error = "Erro ao criar: " . $conn->error;
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
                $error = "Nome de usuário já está em uso por outro usuário.";
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
                    $message = "Usuário atualizado com sucesso.";
                    if ($user_id == $_SESSION['user_id']) {
                        $_SESSION['username'] = $username;
                        $_SESSION['user_fullname'] = $fullname;
                        $_SESSION['user_role'] = $role;
                    }
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
    <title>AccessPoint - Usuários</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(__DIR__ . '/assets/style.css'); ?>">

    <style>
        h1 { display: flex; align-items: center; gap: 10px; font-size: 20px; }
        h1 .icon { width: 22px; height: 22px; color: var(--n-400); }
        h2.section-title { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; margin: 28px 0 14px; color: var(--n-900); }
        h2.section-title .icon { width: 16px; height: 16px; color: var(--n-400); }
        .header-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; }
        .session-pill { background: var(--n-100); color: var(--n-600); padding: 6px 14px; border-radius: var(--radius-pill); font-size: 12.5px; border: 1px solid var(--n-200); }
        .btn-disabled { display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; background: var(--n-100); color: var(--n-400); border-radius: var(--radius-sm); font-size: 12px; cursor: not-allowed; }
        .btn-disabled .icon { width: 13px; height: 13px; }
    </style>
</head>
<body>
<div class="app-container">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="header-row">
            <h1><?php echo icon('users'); ?>Usuários do Sistema</h1>
            <span class="session-pill">Logado como <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo icon('check'); ?><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo icon('alert'); ?><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card-form">
            <h2><?php echo $isEditing ? icon('edit') . 'Editar Usuário' : icon('plus') . 'Criar Novo Usuário'; ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $isEditing ? 'update' : 'create'; ?>">
                <?php if ($isEditing): ?>
                    <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div class="input-group">
                        <label>Nome de Usuário *</label>
                        <input type="text" name="username" value="<?php echo $isEditing ? htmlspecialchars($editUser['username']) : ''; ?>" required placeholder="ex: diretor_tafarel">
                    </div>
                    <div class="input-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="fullname" value="<?php echo $isEditing ? htmlspecialchars($editUser['fullname']) : ''; ?>" required placeholder="ex: Tafarel Cantuária">
                    </div>
                    <div class="input-group">
                        <label>Perfil *</label>
                        <select name="role" required>
                            <option value="admin" <?php echo ($isEditing && $editUser['role'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            <option value="direcao" <?php echo ($isEditing && $editUser['role'] == 'direcao') ? 'selected' : ''; ?>>Direção</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Senha <?php echo $isEditing ? '(deixe em branco para manter)' : '*'; ?></label>
                        <input type="password" name="password" placeholder="<?php echo $isEditing ? 'Nova senha (opcional)' : 'Digite uma senha'; ?>">
                    </div>
                    <div class="input-group">
                        <label>Confirmar Senha</label>
                        <input type="password" name="confirm_password" placeholder="Digite a senha novamente">
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary"><?php echo $isEditing ? icon('save') . 'Salvar Alterações' : icon('plus') . 'Criar Usuário'; ?></button>
                    <?php if ($isEditing): ?>
                        <a href="manage_users.php" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h2 class="section-title"><?php echo icon('file-text'); ?>Usuários Cadastrados</h2>
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
                                        <?php echo $user['role'] == 'admin' ? 'Administrador' : 'Direção'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                <td class="actions-cell">
                                    <a href="?edit=<?php echo $user['id']; ?>" class="btn-small btn-edit"><?php echo icon('edit'); ?>Editar</a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button type="button" class="btn-small btn-danger" data-delete-url="manage_users.php?delete=<?php echo $user['id']; ?>" data-name="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"><?php echo icon('trash'); ?>Excluir</button>
                                    <?php else: ?>
                                        <span class="btn-disabled" title="Não é possível excluir seu próprio usuário"><?php echo icon('ban'); ?>Excluir</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--n-500);">Nenhum usuário cadastrado ainda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script src="assets/script.js"></script>
</body>
</html>
