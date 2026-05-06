<?php
// ============================================
// dashboard.php - Painel de Controle Principal
// ============================================

require_once 'includes/auth.php';
requireLogin();

// ============================================
// CONFIGURAÇÃO DE DATA/HORA DO BRASIL
// ============================================
date_default_timezone_set('America/Sao_Paulo');

// Array com dias da semana em português
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
    <title>Dashboard - Controle de Acesso Escolar</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Estilos específicos complementares (melhor contraste) */
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .device-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .device-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .device-name {
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .device-location {
            color: #6c757d;
            font-size: 0.85rem;
            margin: 8px 0;
        }
        .device-status {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 30px;
            text-transform: uppercase;
        }
        .status-online {
            background: #e0f2e9;
            color: #0c6b4b;
        }
        .status-offline {
            background: #ffe6e5;
            color: #b91c1c;
        }
        .overview-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        .overview-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            flex: 1;
            min-width: 200px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #2c3e90;
        }
        .info-card {
            background: #f8fafc;
        }
        @media (max-width: 768px) {
            .devices-grid {
                grid-template-columns: 1fr;
            }
        }
        /* Ajuste adicional para o banner do relógio */
        .datetime-banner {
            background: linear-gradient(105deg, #1e2a5e, #2a3f7e);
            font-weight: 500;
            letter-spacing: 0.3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        #liveClock {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- SIDEBAR PADRONIZADA (via include) -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- CONTEÚDO PRINCIPAL -->
        <main class="main-content">
            <!-- Banner com data e relógio interativo -->
            <div class="datetime-banner">
                <span>📅 <?php echo $data_atual_str; ?></span>
                <span>🕒 <span id="liveClock">--:--:--</span></span>
            </div>

            <!-- Saudação personalizada -->
            <div style="background: white; border-radius: 16px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h1 style="margin: 0; font-size: 24px;"><?php echo $saudacao; ?>, <?php echo htmlspecialchars($fullname); ?>!</h1>
                <p style="color: #6c757d; margin-top: 8px;">Perfil: <?php echo ($role == 'admin') ? 'Administrador' : 'Direção'; ?></p>
            </div>

            <!-- Cards de estatísticas principais -->
            <div class="cards-stats">
                <div class="stat-card">
                    <div class="stat-icon">💳</div>
                    <h3>Cartões Ativos</h3>
                    <div class="stat-number"><?php echo $total_cards; ?></div>
                    <div class="progress-bar-sim">
                        <div class="progress-fill" style="width: <?php echo min(100, ($total_cards / 100) * 100); ?>%"></div>
                    </div>
                    <small>meta: 100 cartões</small>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📡</div>
                    <h3>Dispositivos (ESP32)</h3>
                    <div class="stat-number"><?php echo $total_devices; ?></div>
                    <div class="progress-bar-sim">
                        <div class="progress-fill" style="width: <?php echo min(100, ($total_devices / 20) * 100); ?>%"></div>
                    </div>
                    <small>capacidade: 20</small>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <h3>Logs de Hoje</h3>
                    <div class="stat-number"><?php echo $logs_hoje; ?></div>
                    <div class="progress-bar-sim">
                        <div class="progress-fill" style="width: <?php echo min(100, ($logs_hoje / 500) * 100); ?>%"></div>
                    </div>
                    <small>eventos registrados</small>
                </div>
            </div>

            <!-- Gráfico de Cartões por Tipo -->
            <div class="chart-container">
                <h3>📊 Cartões por Tipo</h3>
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
            <div style="background: white; border-radius: 16px; padding: 20px; margin: 30px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h3 style="margin-bottom: 15px;">📡 Status dos Dispositivos (ESP32)</h3>
                <div class="devices-grid">
                    <?php if ($devices_result && $devices_result->num_rows > 0): ?>
                        <?php while ($device = $devices_result->fetch_assoc()): ?>
                            <div class="device-card">
                                <div class="device-name">
                                    <?php echo htmlspecialchars($device['device_name']); ?>
                                    <span class="device-status <?php echo $device['status'] == 'online' ? 'status-online' : 'status-offline'; ?>">
                                        <?php echo $device['status'] == 'online' ? '● ONLINE' : '● OFFLINE'; ?>
                                    </span>
                                </div>
                                <div class="device-location">📍 <?php echo htmlspecialchars($device['location'] ?: 'Local não definido'); ?></div>
                                <small>Última atualização: <?php echo $device['last_seen'] ? date('d/m H:i', strtotime($device['last_seen'])) : 'agora mesmo'; ?></small>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>Nenhum dispositivo cadastrado. Vá em <a href="manage_devices.php">Dispositivos</a> para adicionar.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimos Acessos Registrados (Timeline) -->
            <div class="timeline">
                <h3>🕒 Últimos Acessos Registrados</h3>
                <?php if ($ultimos_logs && $ultimos_logs->num_rows > 0): ?>
                    <?php while ($log = $ultimos_logs->fetch_assoc()): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <?php echo $log['access_granted'] ? '✅' : '❌'; ?>
                            </div>
                            <div class="timeline-content">
                                <strong>Cartão: <?php echo htmlspecialchars($log['card_uid']); ?></strong> - 
                                <?php echo htmlspecialchars($log['device_name']); ?>
                                <div class="timeline-time">
                                    <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                </div>
                            </div>
                            <div style="color: <?php echo $log['access_granted'] ? '#10b981' : '#ef4444'; ?>">
                                <?php echo $log['access_granted'] ? 'Autorizado' : 'Bloqueado'; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>Nenhum acesso registrado ainda. Quando o sistema estiver operando com o ESP32, os eventos aparecerão aqui.</p>
                <?php endif; ?>
            </div>

            <!-- Visão Geral do Sistema -->
            <div class="overview-cards">
                <div class="overview-card">
                    <h3>📌 Resumo Operacional</h3>
                    <ul style="margin-top: 12px; line-height: 1.6; padding-left: 20px;">
                        <li><strong><?php echo $total_cards; ?></strong> cartões ativos em circulação.</li>
                        <li><strong><?php echo $total_devices; ?></strong> pontos de acesso (ESP32) monitorando portas.</li>
                        <li>Comunidade escolar acessa áreas permitidas com cartão RFID.</li>
                        <li>Direção gerencia cartões, dispositivos e acompanha logs em tempo real.</li>
                        <li>Administrador possui controle total do sistema e usuários.</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script>
        // RELÓGIO INTERATIVO (atualiza a cada segundo)
        function updateClock() {
            const now = new Date();
            const options = { timeZone: 'America/Sao_Paulo', hour12: false };
            const timeString = now.toLocaleTimeString('pt-BR', options);
            const clockSpan = document.getElementById('liveClock');
            if (clockSpan) clockSpan.innerText = timeString;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Animação das barras de progresso
        document.addEventListener('DOMContentLoaded', function() {
            const fills = document.querySelectorAll('.progress-fill, .chart-fill');
            fills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0%';
                setTimeout(() => { fill.style.width = width; }, 100);
            });
        });
    </script>
</body>
</html>