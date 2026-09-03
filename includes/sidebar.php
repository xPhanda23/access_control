<?php
require_once __DIR__ . '/icons.php';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <?php echo icon('shield'); ?>
        <h2>AccessPoint</h2>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><?php echo icon('home'); ?> Dashboard</a></li>
        <li><a href="manage_cards.php" class="<?php echo $current_page == 'manage_cards.php' ? 'active' : ''; ?>"><?php echo icon('credit-card'); ?> Cartões de Acesso</a></li>
        <li><a href="manage_devices.php" class="<?php echo $current_page == 'manage_devices.php' ? 'active' : ''; ?>"><?php echo icon('wifi'); ?> Dispositivos (ESP32)</a></li>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
            <li><a href="manage_users.php" class="<?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>"><?php echo icon('users'); ?> Usuários do Sistema</a></li>
        <?php endif; ?>
        <li><a href="access_logs.php" class="<?php echo $current_page == 'access_logs.php' ? 'active' : ''; ?>"><?php echo icon('file-text'); ?> Logs de Acesso</a></li>
        <li><a href="simulator.php" class="<?php echo $current_page == 'simulator.php' ? 'active' : ''; ?>"><?php echo icon('terminal'); ?> Simulador ESP32</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn"><?php echo icon('log-out'); ?> Sair</a>
    </div>
</aside>
