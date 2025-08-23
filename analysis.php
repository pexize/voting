<?php
/**
 * analysis.php
 *
 * Analisa a intersecção de IPs entre o log de votos e o log de mensagens.
 * Exibe os votos que foram realizados por IPs que também postaram mensagens.
 */

// --- Funções de Leitura ---

function readJsonLog($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    $jsonContent = file_get_contents($filePath);
    $data = json_decode($jsonContent, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : [];
}

// --- Lógica Principal ---

$voteLog = readJsonLog(__DIR__ . '/vote_log.json');
$messageLog = readJsonLog(__DIR__ . '/message_log.json');

// 1. Criar um mapa de IPs que postaram mensagens para fácil consulta.
$messageIps = [];
foreach ($messageLog as $message) {
    $ip = $message['ip_address'] ?? null;
    if ($ip) {
        if (!isset($messageIps[$ip])) {
            $messageIps[$ip] = [];
        }
        // Adiciona a mensagem formatada ao IP
        $messageIps[$ip][] = [
            'name' => htmlspecialchars($message['name']),
            'message' => htmlspecialchars($message['message']),
            'timestamp' => htmlspecialchars($message['timestamp'])
        ];
    }
}

// 2. Filtrar os votos para encontrar apenas aqueles cujos IPs estão no mapa de mensagens.
$intersectingVotes = [];
foreach ($voteLog as $vote) {
    $ip = $vote['ip_address'] ?? null;
    if ($ip && isset($messageIps[$ip])) {
        // Adiciona o voto e as mensagens associadas para exibição
        $intersectingVotes[] = [
            'vote' => $vote,
            'messages' => $messageIps[$ip]
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🕵️ Análise de Votos e Mensagens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
        }
        .card-header {
            background-color: #e9ecef;
        }
        .message-block {
            background-color: #fff;
            border-left: 4px solid #0d6efd;
            padding: 0.75rem 1rem;
            margin-top: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .table-dark {
            --bs-table-bg: #212529;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <header class="text-center mb-4">
            <h1 class="display-5">🕵️ Análise Cruzada de Dados</h1>
            <p class="lead text-muted">Votos de IPs que também deixaram recados no mural  वोट</p>
        </header>

        <div class="card shadow-sm">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-bar-chart-line-fill"></i> Resultados da Análise</h4>
            </div>
            <div class="card-body">
                <?php if (empty($intersectingVotes)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        <h4>🤔 Nenhum resultado encontrado!</h4>
                        <p class="mb-0">Não há votos de IPs que também tenham postado mensagens no mural.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="bi bi-globe"></i> Endereço IP</th>
                                    <th><i class="bi bi-patch-check-fill"></i> Detalhes do Voto</th>
                                    <th><i class="bi bi-chat-quote-fill"></i> Recados Associados</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($intersectingVotes as $item):
                                    $vote = $item['vote'];
                                    $messages = $item['messages'];
                                ?>
                                    <tr>
                                        <td class="fw-bold">
                                            <code><i class="bi bi-pc-display-horizontal"></i> <?= htmlspecialchars($vote['ip_address']) ?></code>
                                        </td>
                                        <td>
                                            <p class="mb-1"><i class="bi bi-calendar-event"></i> <strong>Evento:</strong> <?= htmlspecialchars($vote['event_name']) ?></p>
                                            <p class="mb-1"><i class="bi bi-tags-fill"></i> <strong>Categoria:</strong> <?= htmlspecialchars($vote['category']) ?></p>
                                            <p class="mb-1">🗳️ <strong>Votou em:</strong> <?= htmlspecialchars($vote['person']) ?></p>
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i> <?= date('d/m/Y H:i:s', strtotime($vote['timestamp'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php foreach ($messages as $msg): ?>
                                                <div class="message-block">
                                                    <p class="mb-1">
                                                        <i class="bi bi-person-circle"></i> <strong><?= $msg['name'] ?>:</strong>
                                                        <span class="fst-italic">"<?= $msg['message'] ?>"</span>
                                                    </p>
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock"></i> <?= date('d/m/Y H:i:s', strtotime($msg['timestamp'])) ?>
                                                    </small>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-muted text-center">
                ✨ Análise gerada em: <?= date('d/m/Y H:i:s') ?> ✨
            </div>
        </div>
    </div>
</body>
</html>