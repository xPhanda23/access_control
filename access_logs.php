<?php

require_once 'includes/auth.php';
requireLogin();
require_once 'includes/icons.php';

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

// ============================================
// FILTROS (via GET)
// ============================================

$search_card = isset($_GET['search_card']) ? trim($_GET['search_card']) : '';
$device_filter = isset($_GET['device_filter']) ? intval($_GET['device_filter']) : 0;
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Montar query com filtros

$where = [];
$params = [];
$types = '';

if (!empty($search_card)) {
    $where[] = "card_uid LIKE ?";
    $params[] = "%$search_card%";
    $types .= 's';
}
if ($device_filter > 0) {
    $where[] = "device_id = ?";
    $params[] = $device_filter;
    $types .= 'i';
}
if (!empty($status_filter)) {
    $where[] = "access_granted = ?";
    $granted = ($status_filter == 'granted') ? 1 : 0;
    $params[] = $granted;
    $types .= 'i';
}
if (!empty($start_date)) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$sql = "SELECT * FROM access_logs";
if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY created_at DESC LIMIT 500"; // limite para performance

$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs_result = $stmt->get_result();

// Buscar dispositivos para o filtro

$devices_list = $conn->query("SELECT id, device_name FROM devices ORDER BY device_name");

// Contar total de registros (para informação)

$total_logs = $logs_result->num_rows;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AccessPoint - Logs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(__DIR__ . '/assets/style.css'); ?>">

    <style>
        h1 { display: flex; align-items: center; gap: 10px; font-size: 20px; margin-bottom: 20px; }
        h1 .icon { width: 22px; height: 22px; color: var(--n-400); }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end; }
        .filters-actions { display: flex; gap: 10px; }
        .status-badge.granted { background: var(--success-soft); color: #166534; }
        .status-badge.denied { background: var(--danger-soft); color: #991b1b; }
        @media (max-width: 768px) {
            .filters-grid { grid-template-columns: 1fr; }
            .filters-actions { width: 100%; }
            .filters-actions .btn { flex: 1; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <h1><?php echo icon('file-text'); ?>Logs de Acesso</h1>

        <div class="datetime-banner">
            <span><?php echo icon('search'); ?><strong><?php echo $total_logs; ?></strong>&nbsp;registros exibidos</span>
            <span><?php echo icon('clock'); ?>Atualizado em <?php echo date('d/m/Y H:i:s'); ?></span>
        </div>

        <!-- Barra de filtros -->

        <div class="card-form">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="input-group">
                        <label>Cartão (UID)</label>
                        <input type="text" name="search_card" placeholder="Código do cartão" value="<?php echo htmlspecialchars($search_card); ?>">
                    </div>
                    <div class="input-group">
                        <label>Dispositivo</label>
                        <select name="device_filter">
                            <option value="0">Todos</option>
                            <?php while($dev = $devices_list->fetch_assoc()): ?>
                                <option value="<?php echo $dev['id']; ?>" <?php echo ($device_filter == $dev['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dev['device_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Resultado</label>
                        <select name="status_filter">
                            <option value="">Todos</option>
                            <option value="granted" <?php echo ($status_filter == 'granted') ? 'selected' : ''; ?>>Permitido</option>
                            <option value="denied" <?php echo ($status_filter == 'denied') ? 'selected' : ''; ?>>Negado</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Data início</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="input-group">
                        <label>Data fim</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="filters-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icon('filter'); ?>Filtrar</button>
                        <a href="access_logs.php" class="btn btn-secondary">Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabela de logs -->

        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Cartão (UID)</th>
                        <th>Dispositivo</th>
                        <th>Acesso</th>
                        <th>Mensagem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                        <?php while ($log = $logs_result->fetch_assoc()): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><code><?php echo htmlspecialchars($log['card_uid']); ?></code></td>
                                <td><?php echo htmlspecialchars($log['device_name']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $log['access_granted'] ? 'granted' : 'denied'; ?>">
                                        <?php echo $log['access_granted'] ? icon('check') . 'Permitido' : icon('x') . 'Negado'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['message']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--n-500);">Nenhum registro de acesso encontrado com os filtros atuais.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
