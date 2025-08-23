<?php
// config.php - Configurações do sistema
class Config {
    const APACHE_LOG_PATH = '/var/log/apache2/access.log';
    const APACHE_ERROR_LOG = '/var/log/apache2/error.log';
    const APACHE_STATUS_URL = 'http://localhost/server-status?auto';
    const REFRESH_INTERVAL = 5; // segundos
}

// Classe para monitoramento do sistema
class SystemMonitor {
    
    public function getCpuUsage() {
        $load = sys_getloadavg();
        return [
            '1min' => round($load[0], 2),
            '5min' => round($load[1], 2),
            '15min' => round($load[2], 2)
        ];
    }
    
    public function getMemoryUsage() {
        $free = shell_exec('free');
        $free = (string)trim($free);
        $free_arr = explode("\n", $free);
        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);
        
        $total = $mem[1];
        $used = $mem[2];
        $free = $mem[3];
        
        return [
            'total' => round($total / 1024 / 1024, 2),
            'used' => round($used / 1024 / 1024, 2),
            'free' => round($free / 1024 / 1024, 2),
            'percentage' => round(($used / $total) * 100, 2)
        ];
    }
    
    public function getDiskUsage() {
        $bytes = disk_free_space(".");
        $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB');
        $base = 1024;
        $class = min((int)log($bytes, $base), count($si_prefix) - 1);
        $free = sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
        
        $total_bytes = disk_total_space(".");
        $total = sprintf('%1.2f', $total_bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
        
        $used_bytes = $total_bytes - $bytes;
        $used = sprintf('%1.2f', $used_bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
        
        $percentage = round(($used_bytes / $total_bytes) * 100, 2);
        
        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percentage' => $percentage
        ];
    }
    
    public function getUptime() {
        $uptime = shell_exec('uptime -p');
        return trim($uptime);
    }
}

// Classe para monitoramento do Apache
class ApacheMonitor {
    
    public function getApacheStatus() {
        $status_url = Config::APACHE_STATUS_URL;
        
        // Verificar se mod_status está habilitado
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $status = @file_get_contents($status_url, false, $context);
        
        if ($status === false) {
            return ['error' => 'Não foi possível acessar o status do Apache. Verifique se mod_status está habilitado.'];
        }
        
        $lines = explode("\n", $status);
        $data = [];
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $data[trim($key)] = trim($value);
            }
        }
        
        return $data;
    }
    
    public function isApacheRunning() {
        $output = shell_exec('ps aux | grep apache2 | grep -v grep');
        return !empty($output);
    }
    
    public function getApacheVersion() {
        $version = shell_exec('apache2 -v 2>/dev/null | head -n 1');
        return trim($version);
    }
    
    public function getActiveConnections() {
        $netstat = shell_exec('netstat -an | grep :80 | grep ESTABLISHED | wc -l');
        return (int)trim($netstat);
    }
}

// Classe para análise de logs
class LogAnalyzer {
    
    public function getLastAccessLogs($lines = 50) {
        $logFile = Config::APACHE_LOG_PATH;
        
        if (!file_exists($logFile)) {
            return ['error' => 'Arquivo de log não encontrado: ' . $logFile];
        }
        
        $logs = shell_exec("tail -n $lines $logFile");
        
        if (empty($logs)) {
            return ['logs' => []];
        }
        
        $logLines = explode("\n", trim($logs));
        $parsedLogs = [];
        
        foreach ($logLines as $line) {
            if (!empty($line)) {
                $parsedLogs[] = $this->parseLogLine($line);
            }
        }
        
        return ['logs' => array_reverse($parsedLogs)];
    }
    
    public function getLastErrorLogs($lines = 20) {
        $logFile = Config::APACHE_ERROR_LOG;
        
        if (!file_exists($logFile)) {
            return ['error' => 'Arquivo de log de erro não encontrado: ' . $logFile];
        }
        
        $logs = shell_exec("tail -n $lines $logFile");
        
        if (empty($logs)) {
            return ['logs' => []];
        }
        
        $logLines = explode("\n", trim($logs));
        return ['logs' => array_reverse(array_filter($logLines))];
    }
    
    private function parseLogLine($line) {
        // Parse básico do formato Common Log Format
        $pattern = '/^(\S+) \S+ \S+ \[(.*?)\] "([^"]*)" (\d+) (\S+)/';
        
        if (preg_match($pattern, $line, $matches)) {
            return [
                'ip' => $matches[1],
                'datetime' => $matches[2],
                'request' => $matches[3],
                'status' => $matches[4],
                'size' => $matches[5],
                'raw' => $line
            ];
        }
        
        return ['raw' => $line];
    }
    
    public function getTopIPs($lines = 1000) {
        $logFile = Config::APACHE_LOG_PATH;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $command = "tail -n $lines $logFile | awk '{print \$1}' | sort | uniq -c | sort -nr | head -10";
        $result = shell_exec($command);
        
        $ips = [];
        if ($result) {
            $lines = explode("\n", trim($result));
            foreach ($lines as $line) {
                if (preg_match('/^\s*(\d+)\s+(.+)$/', trim($line), $matches)) {
                    $ips[] = [
                        'count' => (int)$matches[1],
                        'ip' => $matches[2]
                    ];
                }
            }
        }
        
        return $ips;
    }
    
    public function getStatusCodes($lines = 1000) {
        $logFile = Config::APACHE_LOG_PATH;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $command = "tail -n $lines $logFile | awk '{print \$9}' | sort | uniq -c | sort -nr";
        $result = shell_exec($command);
        
        $codes = [];
        if ($result) {
            $lines = explode("\n", trim($result));
            foreach ($lines as $line) {
                if (preg_match('/^\s*(\d+)\s+(.+)$/', trim($line), $matches)) {
                    $codes[] = [
                        'count' => (int)$matches[1],
                        'code' => $matches[2]
                    ];
                }
            }
        }
        
        return $codes;
    }
}

// Processamento AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $systemMonitor = new SystemMonitor();
    $apacheMonitor = new ApacheMonitor();
    $logAnalyzer = new LogAnalyzer();
    
    switch ($_GET['action']) {
        case 'system':
            echo json_encode([
                'cpu' => $systemMonitor->getCpuUsage(),
                'memory' => $systemMonitor->getMemoryUsage(),
                'disk' => $systemMonitor->getDiskUsage(),
                'uptime' => $systemMonitor->getUptime()
            ]);
            break;
            
        case 'apache':
            echo json_encode([
                'status' => $apacheMonitor->getApacheStatus(),
                'running' => $apacheMonitor->isApacheRunning(),
                'version' => $apacheMonitor->getApacheVersion(),
                'connections' => $apacheMonitor->getActiveConnections()
            ]);
            break;
            
        case 'logs':
            echo json_encode([
                'access' => $logAnalyzer->getLastAccessLogs(30),
                'error' => $logAnalyzer->getLastErrorLogs(10)
            ]);
            break;
            
        case 'stats':
            echo json_encode([
                'top_ips' => $logAnalyzer->getTopIPs(),
                'status_codes' => $logAnalyzer->getStatusCodes()
            ]);
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Apache</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 10px;
        }

        .status-running {
            background: #4CAF50;
            box-shadow: 0 0 10px #4CAF50;
        }

        .status-stopped {
            background: #f44336;
            box-shadow: 0 0 10px #f44336;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card h3 {
            color: #5a67d8;
            margin-bottom: 15px;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .metric:last-child {
            border-bottom: none;
        }

        .metric-value {
            font-weight: 600;
            color: #2d3748;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }

        .progress-low { background: #4CAF50; }
        .progress-medium { background: #ff9800; }
        .progress-high { background: #f44336; }

        .log-container {
            max-height: 400px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }

        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            margin-bottom: 5px;
            padding: 5px;
            border-left: 3px solid #5a67d8;
            background: white;
            border-radius: 3px;
        }

        .log-error {
            border-left-color: #f44336;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .icon {
            width: 20px;
            height: 20px;
        }

        .refresh-info {
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 20px;
            font-size: 0.9em;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .loading {
            animation: pulse 1.5s infinite;
        }

        .wide-card {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Monitor Apache <span id="apache-status" class="status-indicator"></span></h1>
            <p>Monitoramento em tempo real do servidor Apache</p>
        </div>

        <div class="grid">
            <!-- Recursos do Sistema -->
            <div class="card">
                <h3>
                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                    </svg>
                    Recursos do Sistema
                </h3>
                <div id="system-info">
                    <div class="metric">
                        <span>Carregando...</span>
                        <span class="loading">⚡</span>
                    </div>
                </div>
            </div>

            <!-- Status do Apache -->
            <div class="card">
                <h3>
                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12,3L2,12H5V20H19V12H22L12,3M12,8.75A2.25,2.25 0 0,1 14.25,11A2.25,2.25 0 0,1 12,13.25A2.25,2.25 0 0,1 9.75,11A2.25,2.25 0 0,1 12,8.75Z"/>
                    </svg>
                    Status Apache
                </h3>
                <div id="apache-info">
                    <div class="metric">
                        <span>Carregando...</span>
                        <span class="loading">🔄</span>
                    </div>
                </div>
            </div>

            <!-- Estatísticas de Logs -->
            <div class="card">
                <h3>
                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    Estatísticas
                </h3>
                <div id="stats-info">
                    <div class="metric">
                        <span>Carregando estatísticas...</span>
                        <span class="loading">📊</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid">
            <!-- Logs de Acesso -->
            <div class="card wide-card">
                <h3>
                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3M19,19H5V5H19V19Z"/>
                    </svg>
                    Logs de Acesso (Últimas 30 entradas)
                </h3>
                <div class="log-container" id="access-logs">
                    <div class="log-entry">Carregando logs...</div>
                </div>
            </div>

            <!-- Logs de Erro -->
            <div class="card wide-card">
                <h3>
                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M13,13H11V7H13M13,17H11V15H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                    </svg>
                    Logs de Erro (Últimas 10 entradas)
                </h3>
                <div class="log-container" id="error-logs">
                    <div class="log-entry log-error">Carregando logs de erro...</div>
                </div>
            </div>
        </div>

        <div class="refresh-info">
            <p>Atualização automática a cada <?php echo Config::REFRESH_INTERVAL; ?> segundos</p>
        </div>
    </div>

    <script>
        function updateSystemInfo() {
            fetch('?action=system')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('system-info');
                    
                    let html = `
                        <div class="metric">
                            <span>CPU Load (1m/5m/15m)</span>
                            <span class="metric-value">${data.cpu['1min']} / ${data.cpu['5min']} / ${data.cpu['15min']}</span>
                        </div>
                        <div class="metric">
                            <span>Memória</span>
                            <span class="metric-value">${data.memory.used} GB / ${data.memory.total} GB</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill ${getProgressClass(data.memory.percentage)}" 
                                 style="width: ${data.memory.percentage}%"></div>
                        </div>
                        <div class="metric">
                            <span>Disco</span>
                            <span class="metric-value">${data.disk.used} / ${data.disk.total}</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill ${getProgressClass(data.disk.percentage)}" 
                                 style="width: ${data.disk.percentage}%"></div>
                        </div>
                        <div class="metric">
                            <span>Uptime</span>
                            <span class="metric-value">${data.uptime}</span>
                        </div>
                    `;
                    
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Erro ao atualizar informações do sistema:', error);
                });
        }

        function updateApacheInfo() {
            fetch('?action=apache')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('apache-info');
                    const statusIndicator = document.getElementById('apache-status');
                    
                    // Atualizar indicador de status
                    if (data.running) {
                        statusIndicator.className = 'status-indicator status-running';
                    } else {
                        statusIndicator.className = 'status-indicator status-stopped';
                    }
                    
                    let html = `
                        <div class="metric">
                            <span>Status</span>
                            <span class="metric-value" style="color: ${data.running ? '#4CAF50' : '#f44336'}">
                                ${data.running ? 'Executando' : 'Parado'}
                            </span>
                        </div>
                        <div class="metric">
                            <span>Versão</span>
                            <span class="metric-value">${data.version || 'N/A'}</span>
                        </div>
                        <div class="metric">
                            <span>Conexões Ativas</span>
                            <span class="metric-value">${data.connections}</span>
                        </div>
                    `;
                    
                    if (data.status && !data.status.error) {
                        html += `
                            <div class="metric">
                                <span>Requests Total</span>
                                <span class="metric-value">${data.status['Total Accesses'] || 'N/A'}</span>
                            </div>
                            <div class="metric">
                                <span>Requests/seg</span>
                                <span class="metric-value">${data.status['ReqPerSec'] || 'N/A'}</span>
                            </div>
                        `;
                    }
                    
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Erro ao atualizar informações do Apache:', error);
                });
        }

        function updateLogs() {
            fetch('?action=logs')
                .then(response => response.json())
                .then(data => {
                    // Logs de acesso
                    const accessContainer = document.getElementById('access-logs');
                    if (data.access.error) {
                        accessContainer.innerHTML = `<div class="log-entry">${data.access.error}</div>`;
                    } else {
                        const accessHTML = data.access.logs.map(log => 
                            `<div class="log-entry">${log.raw}</div>`
                        ).join('');
                        accessContainer.innerHTML = accessHTML || '<div class="log-entry">Nenhum log encontrado</div>';
                    }
                    
                    // Logs de erro
                    const errorContainer = document.getElementById('error-logs');
                    if (data.error.error) {
                        errorContainer.innerHTML = `<div class="log-entry log-error">${data.error.error}</div>`;
                    } else {
                        const errorHTML = data.error.logs.map(log => 
                            `<div class="log-entry log-error">${log}</div>`
                        ).join('');
                        errorContainer.innerHTML = errorHTML || '<div class="log-entry">Nenhum erro encontrado</div>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar logs:', error);
                });
        }

        function updateStats() {
            fetch('?action=stats')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('stats-info');
                    
                    let html = '<h4 style="margin-bottom: 10px;">Top IPs</h4>';
                    html += '<div class="stats-grid">';
                    
                    if (data.top_ips.length > 0) {
                        data.top_ips.slice(0, 5).forEach(ip => {
                            html += `
                                <div class="stat-item">
                                    <span>${ip.ip}</span>
                                    <span>${ip.count} hits</span>
                                </div>
                            `;
                        });
                    } else {
                        html += '<div class="stat-item">Nenhum dado disponível</div>';
                    }
                    
                    html += '</div><h4 style="margin: 15px 0 10px 0;">Códigos de Status</h4>';
                    html += '<div class="stats-grid">';
                    
                    if (data.status_codes.length > 0) {
                        data.status_codes.forEach(code => {
                            html += `
                                <div class="stat-item">
                                    <span>HTTP ${code.code}</span>
                                    <span>${code.count}</span>
                                </div>
                            `;
                        });
                    } else {
                        html += '<div class="stat-item">Nenhum dado disponível</div>';
                    }
                    
                    html += '</div>';
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Erro ao atualizar estatísticas:', error);
                });
        }

        function getProgressClass(percentage) {
            if (percentage < 60) return 'progress-low';
            if (percentage < 80) return 'progress-medium';
            return 'progress-high';
        }

        function updateAll() {
            updateSystemInfo();
            updateApacheInfo();
            updateLogs();
            updateStats();
        }

        // Atualização inicial
        updateAll();

        // Atualização automática
        setInterval(updateAll, <?php echo Config::REFRESH_INTERVAL * 1000; ?>);
    </script>
</body>
</html>