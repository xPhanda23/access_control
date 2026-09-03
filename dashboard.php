<?php

require_once 'includes/auth.php';
requireLogin();
require_once 'includes/device_helpers.php';
require_once 'includes/icons.php';

// CONFIGURAÇÃO DE DATA/HORA DO BRASIL
date_default_timezone_set('America/Sao_Paulo');

// Array com dias da semana

$dias_semana = [
    'Sunday' => 'Domingo',
    'Monday' => 'Segunda-feira',
    'Tuesday' => 'Terça-feira',
    'Wednesday' => 'Quarta-feira',
    'Thursday' => 'Quinta-feira',
    'Friday' => 'Sexta-feira',
    'Saturday' => 'Sábado'
];

$meses = [
    'January' => 'Janeiro',
    'February' => 'Fevereiro',
    'March' => 'Março',
    'April' => 'Abril',
    'May' => 'Maio',
    'June' => 'Junho',
    'July' => 'Julho',
    'August' => 'Agosto',
    'September' => 'Setembro',
    'October' => 'Outubro',
    'November' => 'Novembro',
    'December' => 'Dezembro'
];

$hoje_ingles = date('l');
$mes_ingles = date('F');
$dia_numero = date('d');
$ano = date('Y');
$hora_num = (int)date('H');

// Saudação baseada na hora

if ($hora_num < 12) {
    $saudacao = "Bom dia";
} elseif ($hora_num < 18) {
    $saudacao = "Boa tarde";
} else {
    $saudacao = "Boa noite";
}

$dia_semana_br = $dias_semana[$hoje_ingles];
$mes_br = $meses[$mes_ingles];
$data_atual_str = "$dia_semana_br, $dia_numero de $mes_br de $ano";

// ============================================
// DADOS DO BANCO
// ============================================

$conn = mysqli_connect('localhost', 'root', '', 'access_control');

// Totais gerais

$result = $conn->query("SELECT COUNT(*) AS total FROM cards WHERE status='active'");
$total_cards = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM devices");
$total_devices = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM access_logs WHERE DATE(created_at) = CURDATE()");
$logs_hoje = $result->fetch_assoc()['total'] ?? 0;

// Cartões por tipo

$tipos_result = $conn->query("SELECT holder_type, COUNT(*) as total FROM cards GROUP BY holder_type");
$cards_por_tipo = [
    'aluno' => 0,
    'professor' => 0,
    'funcionario' => 0,
    'visitante' => 0
];
while ($row = $tipos_result->fetch_assoc()) {
    $cards_por_tipo[$row['holder_type']] = $row['total'];
}

// Dispositivos com status

$devices_result = $conn->query("SELECT id, device_name, location, status, last_seen FROM devices ORDER BY id");

// Últimos 5 logs

$ultimos_logs = $conn->query("SELECT * FROM access_logs ORDER BY created_at DESC LIMIT 5");

$role = $_SESSION['user_role'];
$fullname = $_SESSION['user_fullname'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>AccessPoint - Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(__DIR__ . '/assets/style.css'); ?>">

    <style>
        .greeting-card {
            background: white;
            border: 1px solid var(--n-200);
            border-radius: var(--radius);
            padding: 18px 22px;
            margin-bottom: 22px;
            box-shadow: var(--shadow);
        }
        .greeting-card h1 { font-size: 18px; font-weight: 700; color: var(--n-900); }
        .greeting-card p { color: var(--n-500); font-size: 13px; margin-top: 4px; }
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 14px;
            margin-top: 16px;
        }
        .device-card {
            background: var(--n-50);
            border: 1px solid var(--n-200);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
        }
        .device-name {
            font-weight: 600;
            font-size: 13.5px;
            color: var(--n-900);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }
        .device-location {
            color: var(--n-500);
            font-size: 12.5px;
            margin: 8px 0 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .device-location .icon { width: 13px; height: 13px; }
        .device-card small { color: var(--n-400); font-size: 11.5px; }
        @media (max-width: 768px) {
            .devices-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-container">

        <?php include 'includes/sidebar.php'; ?>


        <main class="main-content">
            <div class="datetime-banner">
                <span><?php echo icon('calendar'); ?><?php echo $data_atual_str; ?></span>
                <span><?php echo icon('clock'); ?><span id="liveClock">--:--:--</span></span>
            </div>

            <div class="greeting-card">
                <h1><?php echo $saudacao; ?>, <?php echo htmlspecialchars($fullname); ?></h1>
                <p><?php echo ($role == 'admin') ? 'Administrador' : 'Direção'; ?></p>
            </div>

            <div class="cards-stats">
                <div class="stat-card">
                    <div class="stat-icon"><?php echo icon('credit-card'); ?></div>
                    <h3>Cartões Ativos</h3>
                    <div class="stat-number"><?php echo $total_cards; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><?php echo icon('wifi'); ?></div>
                    <h3>Dispositivos (ESP32)</h3>
                    <div class="stat-number"><?php echo $total_devices; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><?php echo icon('file-text'); ?></div>
                    <h3>Logs de Hoje</h3>
                    <div class="stat-number"><?php echo $logs_hoje; ?></div>
                </div>
            </div>

            <!-- Gráfico de Cartões por Tipo -->

            <div class="chart-container">
                <h3><?php echo icon('bar-chart'); ?>Cartões por Tipo</h3>
                <?php
                $max_total = max(array_values($cards_por_tipo)) ?: 1;
                $tipos_nomes = [
                    'aluno' => 'Alunos',
                    'professor' => 'Professores',
                    'funcionario' => 'Funcionários',
                    'visitante' => 'Visitantes'
                ];
                foreach ($tipos_nomes as $tipo => $label):
                    $quantidade = $cards_por_tipo[$tipo];
                    $percentual = ($quantidade / $max_total) * 100;
                ?>
                <div class="chart-bar-item">
                    <div class="chart-label"><?php echo $label; ?></div>
                    <div class="chart-bar">
                        <div class="chart-fill" style="width: <?php echo $percentual; ?>%;">
                            <?php echo $quantidade; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Seção: Status dos Dispositivos (ESP32) -->

            <div class="card-form">
                <h2><?php echo icon('wifi'); ?>Status dos Dispositivos</h2>
                <div class="devices-grid">
                    <?php if ($devices_result && $devices_result->num_rows > 0): ?>
                        <?php while ($device = $devices_result->fetch_assoc()): ?>
                            <?php $realmenteOnline = isDeviceOnline($device); ?>
                            <div class="device-card">
                                <div class="device-name">
                                    <?php echo htmlspecialchars($device['device_name']); ?>
                                    <span class="status-badge <?php echo $realmenteOnline ? 'online' : 'offline'; ?>">
                                        <?php echo icon('dot'); ?><?php echo $realmenteOnline ? 'Online' : 'Offline'; ?>
                                    </span>
                                </div>
                                <div class="device-location"><?php echo icon('map-pin'); ?><?php echo htmlspecialchars($device['location'] ?: 'Local não definido'); ?></div>
                                <small>Última atualização: <?php echo $device['last_seen'] ? date('d/m H:i', strtotime($device['last_seen'])) : 'nunca'; ?></small>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>Nenhum dispositivo cadastrado. Vá em <a href="manage_devices.php">Dispositivos</a> para adicionar.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimos Acessos Registrados (Timeline) -->

            <div class="timeline">
                <h3><?php echo icon('clock'); ?>Últimos Acessos</h3>
                <?php if ($ultimos_logs && $ultimos_logs->num_rows > 0): ?>
                    <?php while ($log = $ultimos_logs->fetch_assoc()): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon <?php echo $log['access_granted'] ? 'granted' : 'denied'; ?>">
                                <?php echo $log['access_granted'] ? icon('check') : icon('x'); ?>
                            </div>
                            <div class="timeline-content">
                                <strong>Cartão: <?php echo htmlspecialchars($log['card_uid']); ?></strong> -
                                <?php echo htmlspecialchars($log['device_name']); ?>
                                <div class="timeline-time">
                                    <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                </div>
                            </div>
                            <div style="color: <?php echo $log['access_granted'] ? 'var(--success)' : 'var(--danger)'; ?>; font-size: 13px; font-weight: 600;">
                                <?php echo $log['access_granted'] ? 'Autorizado' : 'Bloqueado'; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>Nenhum acesso registrado ainda.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>

        // RELÓGIO INTERATIVO

        function updateClock() {
            const now = new Date();
            const options = { timeZone: 'America/Sao_Paulo', hour12: false };
            const timeString = now.toLocaleTimeString('pt-BR', options);
            const clockSpan = document.getElementById('liveClock');
            if (clockSpan) clockSpan.innerText = timeString;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Animação das barras do gráfico

        document.addEventListener('DOMContentLoaded', function() {
            const fills = document.querySelectorAll('.chart-fill');
            fills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0%';
                setTimeout(() => { fill.style.width = width; }, 100);
            });
        });
    </script>
</body>
</html>