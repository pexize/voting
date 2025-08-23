<?php
/**
 * index.php
 *
 * Página principal do sistema de votação. Exibe as categorias,
 * rankings em tempo real e o mural de recados.
 * 
 * Versão com layout de "cards" individuais por categoria.
 */
require_once 'data_handler.php';

$data = readData();
$activeEvent = getActiveEvent($data);
$categories = $activeEvent['categories'] ?? [];
$activeEventId = $data['active_event_id'];
$activeEventName = $activeEvent['name'] ?? 'Nenhuma votação ativa';
$messages = $data['messages'] ?? [];

// Identifica as categorias em que o usuário já votou buscando por cookies
$voted_categories = [];
foreach (array_keys($categories) as $category) {
    $cookie_name = 'voted_' . $activeEventId . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $category);
    if (isset($_COOKIE[$cookie_name])) {
        $voted_categories[] = $category;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Pesquisa MR</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <!-- Bootstrap e Ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- CSS Customizado -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <header class="text-center mb-5">
            <h1 class="display-4 fw-bold d-flex align-items-center justify-content-center">
                <i class="bi bi-mic-fill me-3"></i>Central de Pesquisa MR
            </h1>
            <p class="lead text-muted"><?= htmlspecialchars($activeEventName) ?></p>
        </header>

        <div id="feedback-message" class="mb-4" style="display:none;"></div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Seção de Votação por Categoria -->
                <div class="row g-4">
                    <?php if (empty($categories)): ?>
                        <div class="col-12">
                            <p class="text-muted text-center">Nenhuma categoria de votação disponível no momento.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $category => $people): ?>
                            <?php 
                                $hasVoted = in_array($category, $voted_categories);
                                $sanitizedId = htmlspecialchars(preg_replace('/[^a-zA-Z0-9_]/', '', $category));
                            ?>
                            <div class="col-md-6 animate-fade-in-up">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h3 class="h5 mb-0"><?= htmlspecialchars($category) ?></h3>
                                    </div>
                                    <div class="card-body d-flex flex-column justify-content-center">
                                        <!-- Visualização de Votação -->
                                        <div class="vote-view" id="vote-view-<?= $sanitizedId ?>" style="<?= $hasVoted ? 'display: none;' : '' ?>">
                                            <form class="category-vote-form">
                                                <input type="hidden" name="action" value="vote">
                                                <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                                                <div class="mb-3">
                                                    <label for="person-select-<?= $sanitizedId ?>" class="form-label">Vote em:</label>
                                                    <select name="person" id="person-select-<?= $sanitizedId ?>" class="form-select" required>
                                                        <option value="" disabled selected>Selecione uma opção</option>
                                                        <?php foreach (array_keys($people) as $person): ?>
                                                            <option value="<?= htmlspecialchars($person) ?>"><?= htmlspecialchars($person) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-primary">Votar</button>
                                                </div>
                                            </form>
                                        </div>
                                        <!-- Visualização de Ranking -->
                                        <div class="ranking-view" id="ranking-view-<?= $sanitizedId ?>" style="<?= !$hasVoted ? 'display: none;' : '' ?>">
                                            <div class="chart-container" style="position: relative; height: <?= max(150, count($people) * 35) ?>px;">
                                                <canvas id="chart-<?= $sanitizedId ?>"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Seção do Mural de Recados (separada e alinhada) -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h2 class="h5 mb-0"><i class="bi bi-chat-left-text"></i> Mural de Recados</h2>
                            </div>
                            <div class="card-body">
                                <form id="message-form" class="mb-4">
                                    <input type="hidden" name="action" value="add_message">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Seu Nome</label>
                                        <input type="text" class="form-control" id="name" name="name" required maxlength="<?= MAX_USERNAME_LENGTH ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Seu Recado</label>
                                        <textarea class="form-control" id="message" name="message" rows="3" required maxlength="<?= MAX_MESSAGE_LENGTH ?>"></textarea>
                                        <small id="char-counter" class="form-text text-muted">280 caracteres restantes</small>
                                    </div>
                                    <button type="submit" class="btn btn-secondary">Enviar Recado</button>
                                </form>
                                <hr>
                                <div id="messages-list" class="flex-grow-1" style="max-height: 450px; overflow-y: auto;">
                                    <?php if (empty($messages)): ?>
                                        <p id="no-messages" class="text-muted text-center mt-3">Nenhum recado ainda. Seja o primeiro!</p>
                                    <?php else: ?>
                                        <?php foreach ($messages as $msg): ?>
                                            <div class="border-bottom pb-2 mb-2">
                                                <p class="mb-1" style="word-wrap: break-word;"><i class="bi bi-chat-right-text me-2 text-muted"></i><?= htmlspecialchars($msg['message']) ?></p>
                                                <small class="text-muted ms-4">
                                                    <strong><i class="bi bi-person-fill"></i> <?= htmlspecialchars($msg['name']) ?></strong> - <i class="bi bi-clock"></i> <?= $msg['date'] ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Variáveis e Constantes ---
            const categoriesData = <?php echo json_encode($categories); ?>;
            const votedCategories = <?php echo json_encode($voted_categories); ?>;
            const chartInstances = {};

            /**
             * Sanitiza um nome de categoria para ser usado como ID de elemento.
             */
            function sanitizeCategory(categoryName) {
                return categoryName.replace(/[^a-zA-Z0-9_]/g, '');
            }

            // --- Lógica de Votação ---
            const voteForms = document.querySelectorAll('.category-vote-form');
            voteForms.forEach(form => {
                form.addEventListener('submit', e => {
                    e.preventDefault();
                    const formData = new FormData(form);
                    const voteButton = form.querySelector('button[type="submit"]');

                    if (!formData.get('person')) {
                        showFeedback('Por favor, selecione uma opção para votar.', false);
                        return;
                    }

                    voteButton.disabled = true;
                    voteButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Votando...';

                    fetch('api.php', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                showFeedback(result.message, true);
                                const votedCategory = formData.get('category');
                                
                                // Atualiza os dados e renderiza o gráfico
                                for (const category in result.categories) {
                                    categoriesData[category] = result.categories[category];
                                }
                                renderOrUpdateChart(votedCategory, categoriesData[votedCategory]);

                                // Troca a visualização de voto pela de ranking
                                const sanitizedId = sanitizeCategory(votedCategory);
                                const voteView = document.getElementById(`vote-view-${sanitizedId}`);
                                const rankingView = document.getElementById(`ranking-view-${sanitizedId}`);

                                if (voteView && rankingView) {
                                    voteView.style.display = 'none';
                                    rankingView.style.display = 'block';
                                    rankingView.classList.add('ranking-visible');
                                    rankingView.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                }
                            } else {
                                showFeedback(result.message, false);
                            }
                        })
                        .catch(() => showFeedback('Erro de comunicação com o servidor.', false))
                        .finally(() => {
                            voteButton.disabled = false;
                            voteButton.innerHTML = 'Votar';
                        });
                });
            });

            // --- Lógica do Mural de Recados ---
            const messageForm = document.getElementById('message-form');
            if (messageForm) {
                // (código do mural de recados permanece o mesmo)
                const messageInput = document.getElementById('message');
                const charCounter = document.getElementById('char-counter');
                const MAX_MESSAGE_LENGTH = <?= MAX_MESSAGE_LENGTH ?>;

                messageInput.addEventListener('input', () => {
                    const remaining = MAX_MESSAGE_LENGTH - messageInput.value.length;
                    charCounter.textContent = `${remaining} caracteres restantes`;
                });

                messageForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const formData = new FormData(messageForm);
                    const submitButton = messageForm.querySelector('button[type="submit"]');
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';

                    fetch('api.php', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                addMessageToDOM(result.newMessage);
                                messageForm.reset();
                                charCounter.textContent = `${MAX_MESSAGE_LENGTH} caracteres restantes`;
                            } else {
                                showFeedback(result.message, false);
                            }
                        })
                        .catch(() => showFeedback('Erro de comunicação ao enviar recado.', false))
                        .finally(() => {
                            submitButton.disabled = false;
                            submitButton.innerHTML = 'Enviar Recado';
                        });
                });
            }

            // --- Funções Auxiliares ---
            function renderOrUpdateChart(category, categoryData) {
                const sanitizedId = sanitizeCategory(category);
                const ctx = document.getElementById(`chart-${sanitizedId}`)?.getContext('2d');
                if (!ctx) return;

                const sortedData = Object.entries(categoryData).sort(([, a], [, b]) => b - a);
                const labels = sortedData.map(item => item[0]);
                const votes = sortedData.map(item => item[1]);

                if (chartInstances[sanitizedId]) {
                    chartInstances[sanitizedId].data.labels = labels;
                    chartInstances[sanitizedId].data.datasets[0].data = votes;
                    chartInstances[sanitizedId].update();
                } else {
                    chartInstances[sanitizedId] = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Votos',
                                data: votes,
                                backgroundColor: '#FF5A5F',
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { 
                                x: { beginAtZero: true, ticks: { precision: 0 } },
                                y: { grid: { display: false } }
                            }
                        }
                    });
                }
            }

            function showFeedback(message, isSuccess) {
                const feedbackMessage = document.getElementById('feedback-message');
                feedbackMessage.innerHTML = `<div class="alert alert-${isSuccess ? 'success' : 'danger'}">${message}</div>`;
                feedbackMessage.style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(() => { feedbackMessage.style.display = 'none'; }, 5000);
            }

            function addMessageToDOM(msg) {
                const messagesList = document.getElementById('messages-list');
                const noMessagesEl = document.getElementById('no-messages');
                if (noMessagesEl) noMessagesEl.remove();

                const messageEl = document.createElement('div');
                messageEl.className = 'border-bottom pb-2 mb-2';
                messageEl.innerHTML = `
                    <p class="mb-1" style="word-wrap: break-word;"><i class="bi bi-chat-right-text me-2 text-muted"></i>${escapeHTML(msg.message)}</p>
                    <small class="text-muted ms-4">
                        <strong><i class="bi bi-person-fill"></i> ${escapeHTML(msg.name)}</strong> - <i class="bi bi-clock"></i> ${msg.date}
                    </small>
                `;
                messagesList.prepend(messageEl);
            }

            function escapeHTML(str) {
                const p = document.createElement('p');
                p.textContent = str;
                return p.innerHTML;
            }

            // Renderiza os gráficos para as categorias já votadas no carregamento da página
            votedCategories.forEach(category => {
                if (categoriesData[category]) {
                    renderOrUpdateChart(category, categoriesData[category]);
                }
            });
        });
    </script>
</body>
</html>
