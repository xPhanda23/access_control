<?php
// ============================================
// ARQUIVO: manage_users.php
// FUNÇÃO: Gerenciar usuários do sistema (admin/direção)
// PERMISSÃO: Apenas ADMINISTRADOR pode acessar
// ============================================

require_once 'includes/auth.php';

// Só o Administrador pode gerenciar usuários
requireAdmin();

$conn = mysqli_connect('localhost', 'root', '', 'access_control');
$message = '';
$error = '';
$editUser = null;

// ============================================
// DELETAR USUÁRIO (não pode deletar a si mesmo)
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    if ($id == $_SESSION['user_id']) {
        $error = "❌ Você não pode deletar seu próprio usuário!";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "✅ Usuário deletado com sucesso!";
        } else {
            $error = "❌ Erro ao deletar usuário.";
        }
        $stmt->close();
    }
    header("Location: manage_users.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// ============================================
// BUSCAR USUÁRIO PARA EDIÇÃO
// ============================================
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM users WHERE id = $id");
    if ($result->num_rows > 0) {
        $editUser = $result->fetch_assoc();
    }
}

// ============================================
// CRIAR OU ATUALIZAR USUÁRIO (COM VALIDAÇÃO DE DUPLICIDADE)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validações básicas
    if (empty($username) || empty($fullname) || empty($role)) {
        $error = "❌ Todos os campos obrigatórios devem ser preenchidos!";
    } elseif ($action == 'create' && empty($password)) {
        $error = "❌ A senha é obrigatória para novos usuários!";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "❌ As senhas não coincidem!";
    } else {
        
        if ($action == 'create') {
            // VERIFICA SE O USERNAME JÁ EXISTE ANTES DE INSERIR
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->bind_param("s", $username);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = "❌ O nome de usuário '$username' já está em uso. Escolha outro.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $hashed_password, $fullname, $role);
                
                if ($stmt->execute()) {
                    $message = "✅ Usuário criado com sucesso!";
                } else {
                    $error = "❌ Erro ao criar usuário: " . $conn->error;
                }
                $stmt->close();
            }
            $checkStmt->close();
            
        } elseif ($action == 'update') {
            // VERIFICA SE O USERNAME JÁ EXISTE PARA OUTRO ID (DIFERENTE DO ATUAL)
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $checkStmt->bind_param("si", $username, $id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = "❌ O nome de usuário '$username' já está em uso por outro usuário.";
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username=?, password=?, fullname=?, role=? WHERE id=?");
                    $stmt->bind_param("ssssi", $username, $hashed_password, $fullname, $role, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username=?, fullname=?, role=? WHERE id=?");
                    $stmt->bind_param("sssi", $username, $fullname, $role, $id);
                }
                
                if ($stmt->execute()) {
                    $message = "✅ Usuário atualizado com sucesso!";
                    // Se atualizou o próprio usuário, atualiza a sessão
                    if ($id == $_SESSION['user_id']) {
                        $_SESSION['username'] = $username;
                        $_SESSION['user_fullname'] = $fullname;
                        $_SESSION['user_role'] = $role;
                    }
                } else {
                    $error = "❌ Erro ao atualizar usuário: " . $conn->error;
                }
                $stmt->close();
            }
            $checkStmt->close();
            $editUser = null; // Limpa edição após salvar
        }
    }
    
    // Redireciona com mensagens
    if ($message || $error) {
        header("Location: manage_users.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
        exit;
    }
}

// ============================================
// PEGAR MENSAGENS DA URL (após redirect)
// ============================================
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
}
if (isset($_GET['err'])) {
    $error = urldecode($_GET['err']);
}

// ============================================
// LISTAR TODOS OS USUÁRIOS
// ============================================
$users_result = $conn->query("SELECT * FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Controle de Acesso</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .user-form-container {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .user-form-container h2 {
            margin-bottom: 20px;
            font-size: 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            grid-column: 1 / -1;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .role-admin {
            background: #fef3c7;
            color: #92400e;
        }
        .role-direcao {
            background: #dbeafe;
            color: #1e40af;
        }
        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .current-user-badge {
            background: #10b981;
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
        }
        .info-note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <h2>Menu</h2>
            <ul>
                <li><a href="dashboard.php">🏠 Dashboard</a></li>
                <li><a href="manage_cards.php">💳 Cartões de Acesso</a></li>
                <li><a href="manage_devices.php">📡 Dispositivos (ESP32)</a></li>
                <li><a href="manage_users.php" class="active">👥 Usuários do Sistema</a></li>
                <li><a href="access_logs.php">📜 Logs de Acesso</a></li>
                <li><a href="simulator.php">🔧 Simulador ESP32</a></li>
                <li><a href="logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h1 style="margin: 0;">👥 Gerenciar Usuários</h1>
                <span style="background: #e2e8f0; padding: 6px 12px; border-radius: 20px; font-size: 14px;">
                    Logado como: <strong><?php echo $_SESSION['username']; ?></strong>
                </span>
            </div>
            
            <div class="info-note">
                ⚠️ <strong>Apenas Administradores</strong> podem acessar esta página. Aqui você pode criar, editar e excluir usuários do sistema (direção e outros administradores).
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- FORMULÁRIO PARA CRIAR/EDITAR USUÁRIO -->
            <div class="user-form-container">
                <h2>
                    <?php if ($editUser): ?>
                        ✏️ Editando Usuário
                    <?php else: ?>
                        ➕ Criar Novo Usuário
                    <?php endif; ?>
                </h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'create'; ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="id" value="<?php echo $editUser['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="input-group">
                            <label for="username">👤 Nome de Usuário *</label>
                            <input type="text" id="username" name="username" required 
                                   value="<?php echo $editUser ? htmlspecialchars($editUser['username']) : ''; ?>"
                                   placeholder="ex: diretor_carlos">
                        </div>
                        
                        <div class="input-group">
                            <label for="fullname">📛 Nome Completo *</label>
                            <input type="text" id="fullname" name="fullname" required 
                                   value="<?php echo $editUser ? htmlspecialchars($editUser['fullname']) : ''; ?>"
                                   placeholder="ex: Carlos Silva Santos">
                        </div>
                        
                        <div class="input-group">
                            <label for="role">🎭 Perfil *</label>
                            <select id="role" name="role" required>
                                <option value="admin" <?php echo ($editUser && $editUser['role'] == 'admin') ? 'selected' : ''; ?>>👑 Administrador (Controle Total)</option>
                                <option value="direcao" <?php echo ($editUser && $editUser['role'] == 'direcao') ? 'selected' : ''; ?>>📋 Direção (Gerenciamento)</option>
                            </select>
                            <small style="color: #666;">Admin = tudo | Direção = cartões, dispositivos, logs</small>
                        </div>
                        
                        <div class="input-group">
                            <label for="password">🔒 Senha <?php echo $editUser ? '(deixe em branco para manter)' : '*'; ?></label>
                            <input type="password" id="password" name="password" 
                                   placeholder="<?php echo $editUser ? 'Nova senha (opcional)' : 'Digite uma senha'; ?>">
                        </div>
                        
                        <div class="input-group">
                            <label for="confirm_password">✅ Confirmar Senha</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Digite a senha novamente">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $editUser ? '💾 Salvar Alterações' : '➕ Criar Usuário'; ?>
                            </button>
                            
                            <?php if ($editUser): ?>
                                <a href="manage_users.php" class="btn btn-secondary">❌ Cancelar Edição</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- LISTA DE USUÁRIOS -->
            <h2 style="margin-top: 30px; margin-bottom: 15px;">📋 Usuários Cadastrados</h2>
            
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
                    <?php if ($users_result->num_rows == 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Nenhum usuário cadastrado ainda.</td>
                        </tr>
                    <?php else: ?>
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
                                    <span class="role-badge <?php echo $user['role'] == 'admin' ? 'role-admin' : 'role-direcao'; ?>">
                                        <?php echo $user['role'] == 'admin' ? '👑 Administrador' : '📋 Direção'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                <td class="actions-cell">
                                    <a href="?edit=<?php echo $user['id']; ?>" class="btn-small" title="Editar">✏️ Editar</a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?php echo $user['id']; ?>" class="btn-small btn-danger" 
                                           onclick="return confirm('Tem certeza que deseja excluir o usuário <?php echo addslashes($user['username']); ?>?')" 
                                           title="Excluir">🗑️ Excluir</a>
                                    <?php else: ?>
                                        <span class="btn-small" style="background: #cbd5e1; cursor: not-allowed;" title="Não pode excluir a si mesmo">🚫 Auto-exclusão</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="background: #f1f5f9; padding: 16px; border-radius: 12px; margin-top: 24px;">
                <h3 style="margin-bottom: 10px;">📌 Sobre os Perfis:</h3>
                <ul style="margin-left: 20px; line-height: 1.6;">
                    <li><strong>👑 Administrador:</strong> Tem acesso TOTAL. Pode gerenciar usuários, cartões, dispositivos, logs e tudo mais.</li>
                    <li><strong>📋 Direção:</strong> Pode gerenciar cartões, dispositivos e ver logs, mas NÃO pode acessar o gerenciamento de usuários.</li>
                    <li><strong>💡 Dica:</strong> Sempre mantenha pelo menos UM administrador ativo no sistema!</li>
                </ul>
            </div>
        </main>
    </div>
    <script src="assets/script.js"></script>
</body>
</html>