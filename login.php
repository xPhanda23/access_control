<?php
// NÃO precisa de session_start() aqui, pois auth.php já cuida disso
require_once 'includes/auth.php';

// Se já estiver logado, vai direto para o dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, username, password, fullname, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_fullname'] = $user['fullname'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Senha incorreta.';
            }
        } else {
            $error = 'Usuário não encontrado.';
        }
        $stmt->close();
    } else {
        $error = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Controle de Acesso</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="icon">🔐</div>
                <h1>Controle de Acesso Escolar</h1>
                <p>Gestão de cartões e dispositivos</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="" class="login-form">
                <div class="input-group">
                    <label for="username">Usuário</label>
                    <input type="text" id="username" name="username" required autofocus placeholder="Digite seu usuário">
                </div>
                <div class="input-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required placeholder="Digite sua senha">
                </div>
                <button type="submit" class="btn btn-primary">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>