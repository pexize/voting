<?php
require_once 'data_handler.php';

$data = readData();
$activeEvent = getActiveEvent($data);
$categories = $activeEvent['categories'] ?? [];
$activeEventId = $data['active_event_id'];
$activeEventName = $activeEvent['name'] ?? 'Nenhuma votação ativa';
$messages = $data['messages'] ?? [];

// Lógica para adicionar novo recado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_message'])) {
    if (addMessage($data, $_POST['name'], $_POST['message'])) {
        header("Location: index.php?message_sent=true");
        exit;
    }
}

// Identifica as categorias já votadas
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
    <!-- Bootstrap e Ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- CSS Customizado -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <header class="text-center mb-5">
            <h1 class="display-4 fw-bold">Central de Pesquisa MR</h1>
            <p class="lead text-muted"><?= htmlspecialchars($activeEventName) ?></p>
        </header>

        <div id="feedback-message" class="mb-4" style="display:none;"></div>
        <?php if(isset($_GET['message_sent'])): ?>
            <div class="alert alert-success">Seu recado foi enviado com sucesso!</div>
        <?php endif; ?>

        <div class="row g-5">
            <!-- Coluna da Votação e Rankings -->
            <div class="col-lg-6 animate-fade-in-up">
                <div class="card shadow-sm mb-4 transition-transform duration-300 hover:scale-105">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><i class="bi bi-check2-square"></i> Vote Agora!</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <p class="text-muted">Nenhuma categoria de votação disponível.</p>
                        <?php else: ?>
                            <form id="vote-form">
                                <div class="mb-3">
                                    <label for="category_select" class="form-label">Categoria:</label>
                                    <select name="category" id="category_select" class="form-select" required>
                                        <?php
                                        $available_categories = 0;
                                        foreach (array_keys($categories) as $category) {
                                            if (!in_array($category, $voted_categories)) {
                                                echo '<option value="' . htmlspecialchars($category) . '">' . htmlspecialchars($category) . '</option>';
                                                $available_categories++;
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="person_select" class="form-label">Pessoa:</label>
                                    <select name="person" id="person_select" class="form-select" required></select>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="vote" class="btn btn-primary btn-glow">Votar</button>
                                </div>
                            </form>
                            <p id="already-voted-message" class="alert alert-info mt-3" style="display:<?= $available_categories > 0 ? 'none' : 'block' ?>;">
                                Você já votou em todas as categorias disponíveis.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card shadow-sm transition-transform duration-300 hover:scale-105">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><i class="bi bi-bar-chart-line-fill"></i> Rankings</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $category => $people): ?>
                                <div class="category-ranking mb-4" id="category-<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_]/', '', $category)) ?>">
                                    <h3 class="h6"><?= htmlspecialchars($category) ?></h3>
                                    <div class="chart-container mb-2" style="position: relative; height: 150px;">
                                        <canvas id="chart-<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_]/', '', $category)) ?>"></canvas>
                                    </div>
                                    <ul class="ranking-list list-group list-group-flush"></ul>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna do Mural de Recados -->
            <div class="col-lg-6 animate-fade-in-up" style="animation-delay: 150ms;">
                <div class="card shadow-sm transition-transform duration-300 hover:scale-105 h-100">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><i class="bi bi-chat-left-text"></i> Mural de Recados</h2>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <form method="POST" action="index.php" class="mb-4">
                            <div class="mb-3">
                                <label for="name" class="form-label">Seu Nome</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Seu Recado</label>
                                <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="add_message" class="btn btn-secondary">Enviar Recado</button>
                        </form>
                        <hr>
                        <div class="messages-list flex-grow-1" style="max-height: 450px; overflow-y: auto;">
                            <?php if (empty($messages)): ?>
                                <p class="text-muted">Nenhum recado ainda.</p>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <div class="border-bottom pb-2 mb-2 transition-transform duration-200 hover:bg-gray-100 p-2 rounded">
                                        <p class="mb-1"><?= htmlspecialchars($msg['message']) ?></p>
                                        <small class="text-muted">
                                            <strong><?= htmlspecialchars($msg['name']) ?></strong> - <?= $msg['date'] ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const categoriesData = <?php echo json_encode($categories); ?>;
            const chartInstances = {};

            const categorySelect = document.getElementById('category_select');
            const personSelect = document.getElementById('person_select');
            const voteForm = document.getElementById('vote-form');
            const feedbackMessage = document.getElementById('feedback-message');
            const alreadyVotedMessage = document.getElementById('already-voted-message');

            function renderOrUpdateChart(category, categoryData) {
                const sanitizedId = category.replace(/[^a-zA-Z0-9_]/g, '');
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
                                backgroundColor: ['#0d6efd', '#6c757d', '#198754', '#dc3545', '#ffc107', '#0dcaf0'],
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

            function updateRankingList(category, categoryData) {
                const sanitizedId = category.replace(/[^a-zA-Z0-9_]/g, '');
                const listElement = document.querySelector(`#category-${sanitizedId} .ranking-list`);
                if (!listElement) return;
                listElement.innerHTML = '';

                const sortedData = Object.entries(categoryData).sort(([, a], [, b]) => b - a);

                if (sortedData.length === 0) {
                    listElement.innerHTML = '<li class="list-group-item">Nenhum participante.</li>';
                } else {
                    sortedData.forEach(([person, votes]) => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        li.innerHTML = `<span>${person}</span> <span class="badge bg-primary rounded-pill">${votes}</span>`;
                        listElement.appendChild(li);
                    });
                }
            }

            function updatePersonOptions() {
                const selectedCategory = categorySelect.value;
                personSelect.innerHTML = '';
                if (!selectedCategory || !categoriesData[selectedCategory]) return;
                
                const people = Object.keys(categoriesData[selectedCategory]);
                if (people.length > 0) {
                    people.forEach(person => {
                        const option = document.createElement('option');
                        option.value = person;
                        option.textContent = person;
                        personSelect.appendChild(option);
                    });
                } else {
                    personSelect.innerHTML = '<option disabled>Nenhuma pessoa</option>';
                }
            }
            
            function showFeedback(message, isSuccess) {
                feedbackMessage.innerHTML = `<div class="alert alert-${isSuccess ? 'success' : 'danger'}">${message}</div>`;
                feedbackMessage.style.display = 'block';
                setTimeout(() => { feedbackMessage.style.display = 'none'; }, 4000);
            }

            // --- Lógica Principal ---
            for (const category in categoriesData) {
                renderOrUpdateChart(category, categoriesData[category]);
                updateRankingList(category, categoriesData[category]);
            }

            updatePersonOptions();
            if (categorySelect.options.length === 0) {
                voteForm.style.display = 'none';
            }

            categorySelect.addEventListener('change', updatePersonOptions);

            voteForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(voteForm);
                fetch('api.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showFeedback(result.message, true);
                        
                        for (const category in result.categories) {
                            renderOrUpdateChart(category, result.categories[category]);
                            updateRankingList(category, result.categories[category]);
                        }

                        const votedCategory = formData.get('category');
                        const optionToRemove = categorySelect.querySelector(`option[value="${votedCategory}"]`);
                        if (optionToRemove) optionToRemove.remove();
                        
                        updatePersonOptions();
                        if (categorySelect.options.length === 0) {
                            voteForm.style.display = 'none';
                            alreadyVotedMessage.style.display = 'block';
                        }
                    } else {
                        showFeedback(result.message, false);
                    }
                })
                .catch(() => showFeedback('Erro de comunicação.', false));
            });
        });
    </script>
</body>
</html>