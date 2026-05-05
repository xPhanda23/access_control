<?php
require_once 'includes/db.php';

$users = [
    ['admin', 'admin123', 'Administrador do Sistema', 'admin'],
    ['direcao', 'direcao123', 'Equipe Diretiva', 'direcao']
];

echo "<h2>Corrigindo usuários do sistema...</h2>";

foreach ($users as $user) {
    $username = $user[0];
    $plain_password = $user[1];
    $fullname = $user[2];
    $role = $user[3];
    
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    
    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE users SET password = '$hashed_password', fullname = '$fullname', role = '$role' WHERE username = '$username'");
        echo "<p>✅ Usuário <strong>$username</strong> atualizado com a senha <code>$plain_password</code></p>";
    } else {
        $conn->query("INSERT INTO users (username, password, fullname, role) VALUES ('$username', '$hashed_password', '$fullname', '$role')");
        echo "<p>➕ Usuário <strong>$username</strong> criado com a senha <code>$plain_password</code></p>";
    }
}

echo "<hr><a href='login.php'>Ir para a página de login</a>";
?>