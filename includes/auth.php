<?php
// Inicia a sessão apenas se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Verifica se o usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Verifica se o usuário tem uma determinada função (admin ou direcao)
function hasRole($role) {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role);
}

// Redireciona se não estiver logado
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Redireciona se não tiver permissão de admin
function requireAdmin() {
    requireLogin();
    if (!hasRole('admin')) {
        header('HTTP/1.0 403 Forbidden');
        echo "<h1>Acesso negado</h1><p>Apenas administradores podem acessar esta página.</p>";
        exit;
    }
}
?>