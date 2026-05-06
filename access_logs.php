<?php
// ============================================
// access_logs.php - Logs de Acesso do Sistema
// ============================================

require_once 'includes/auth.php';
requireLogin();

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
    <title>Logs de Acesso - AccessControl</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Estilos específicos para esta página */
        .filters-bar {
            background: white;
            border-radius: 24px;
            padding: 20px 24px;
            margin-bottom: 28px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
        }
        .filter-group input, .filter-group select {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .filter-group input:focus, .filter-group select:focus {
            border-color: #4361ee;
            outline: none;
            box-shadow: 0 0 0 2px rgba(67,97,238,0.1);
        }
        .btn-filter {
            background: linear-gradient(105deg, #4361ee, #3a56d4);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            height: 42px;
        }
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(67,97,238,0.3);
        }
        .btn-clear {
            background: #e2e8f0;
            color: #1e293b;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 42px;
        }
        .access-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .access-granted {
            background: #d1fae5;
            color: #065f46;
        }
        .access-denied {
            background: #fee2e2;
            color: #991b1b;
        }
        .stats-card {
            background: white;
            border-radius: 20px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .stats-number {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4361ee, #7209b7);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            .btn-filter, .btn-clear {
                width: 100%;
                justify-content: center;
            }
            .stats-card {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 24px;">
            <h1 style="margin: 0;">📜 Logs de Acesso</h1>
            <span style="background: #e2e8f0; padding: 6px 14px; border-radius: 40px; font-size: 0.85rem;">
                Registros em tempo real
            </span>
        </div>

        <!-- Card com estatísticas rápidas -->
        <div class="stats-card">
            <div>🔍 <strong>Total de registros exibidos:</strong> <span class="stats-number"><?php echo $total_logs; ?></span></div>
            <div>📅 Última atualização: <?php echo date('d/m/Y H:i:s'); ?></div>
        </div>

        <!-- Barra de filtros -->
        <div class="filters-bar">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>🔎 Cartão (UID)</label>
                        <input type="text" name="search_card" placeholder="Código do cartão" value="<?php echo htmlspecialchars($search_card); ?>">
                    </div>
                    <div class="filter-group">
                        <label>📡 Dispositivo</label>
                        <select name="device_filter">
                            <option value="0">Todos</option>
                            <?php while($dev = $devices_list->fetch_assoc()): ?>
                                <option value="<?php echo $dev['id']; ?>" <?php echo ($device_filter == $dev['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dev['device_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>🚪 Resultado</label>
                        <select name="status_filter">
                            <option value="">Todos</option>
                            <option value="granted" <?php echo ($status_filter == 'granted') ? 'selected' : ''; ?>>Permitido</option>
                            <option value="denied" <?php echo ($status_filter == 'denied') ? 'selected' : ''; ?>>Negado</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>📅 Data início</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="filter-group">
                        <label>📅 Data fim</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="filter-group" style="display: flex; flex-direction: row; gap: 8px;">
                        <button type="submit" class="btn-filter">Filtrar</button>
                        <a href="access_logs.php" class="btn-clear">Limpar</a>
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
                                    <span class="access-badge <?php echo $log['access_granted'] ? 'access-granted' : 'access-denied'; ?>">
                                        <?php echo $log['access_granted'] ? '✅ Permitido' : '❌ Negado'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['message']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Nenhum registro de acesso encontrado com os filtros atuais.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Informação adicional -->
        <div style="background: #f1f5f9; border-radius: 20px; padding: 16px; margin-top: 28px; font-size: 0.85rem; text-align: center;">
            💡 Os logs mostram todas as tentativas de acesso (sucesso ou falha). Use os filtros para refinar a busca.
        </div>
    </main>
</div>
</body>
</html>