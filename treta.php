<?php
require_once 'data_handler.php';

session_start();

// Lógica de login
if (isset($_POST['login'])) {
    if (verifyPassword($_POST['password'])) {
        $_SESSION['loggedin'] = true;
        header('Location: treta.php');
        exit;
    } else {
        $loginError = 'Senha incorreta!';
    }
}

// Lógica de logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Se não estiver logado, exibe a página de login com Bootstrap
if (empty($_SESSION['loggedin'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin - Central de Pesquisa MR</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                background-color: #f8f9fa;
            }
            .login-card {
                width: 100%;
                max-width: 400px;
            }
        </style>
    </head>
    <body>
        <div class="card login-card shadow-sm">
            <div class="card-body p-5">
                <h1 class="card-title text-center mb-4">Admin Login</h1>
                <form method="POST" action="treta.php">
                    <div class="mb-3">
                        <label for="password" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="login" class="btn btn-primary">Entrar</button>
                    </div>
                    <?php if (isset($loginError)) { echo '<div class="alert alert-danger mt-3">' . htmlspecialchars($loginError) . '</div>'; } ?>
                </form>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// --- Lógica de Manipulação de Dados ---
$data = readData();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login'])) {
    // Gerenciamento de Eventos
    if (isset($_POST['create_event'])) {
        if (createEvent($data, $_POST['event_name'])) {
            $_SESSION['message'] = 'Nova votação criada com sucesso!';
        } else {
            $_SESSION['error'] = 'Nome da votação não pode ser vazio.';
        }
    }
    if (isset($_POST['set_active_event'])) {
        if (setActiveEvent($data, $_POST['event_id'])) {
            $_SESSION['message'] = 'Votação ativa alterada com sucesso!';
        }
    }
    if (isset($_POST['delete_event'])) {
        if (deleteEvent($data, $_POST['event_id'])) {
            $_SESSION['message'] = 'Votação deletada com sucesso!';
        } else {
            $_SESSION['error'] = 'Não foi possível deletar a votação (não pode ser a última).';
        }
    }

    // Gerenciamento de Categorias e Nomes
    if (isset($_POST['add_category'])) {
        if (addCategory($data, $_POST['category_name'])) {
            $_SESSION['message'] = 'Categoria adicionada!';
        } else {
            $_SESSION['error'] = 'Erro ao adicionar categoria.';
        }
    }
    if (isset($_POST['remove_category'])) {
        if (removeCategory($data, $_POST['remove_category_name'])) {
            $_SESSION['message'] = 'Categoria removida!';
        }
    }
    if (isset($_POST['add_name'])) {
        if (addName($data, $_POST['add_name_category'], $_POST['person_name'])) {
            $_SESSION['message'] = 'Nome adicionado!';
        } else {
            $_SESSION['error'] = 'Erro ao adicionar nome.';
        }
    }
    if (isset($_POST['remove_name'])) {
        if (removeName($data, $_POST['remove_name_category'], $_POST['remove_person_name'])) {
            $_SESSION['message'] = 'Nome removido!';
        }
    }
    
    // Gerenciamento de Mensagens
    if (isset($_POST['delete_message'])) {
        if (deleteMessage($data, $_POST['message_id'])) {
            $_SESSION['message'] = 'Recado deletado com sucesso!';
        } else {
            $_SESSION['error'] = 'Erro ao deletar o recado.';
        }
    }
    
    header('Location: treta.php'); // Redireciona para evitar reenvio de formulário
    exit;
}

$activeEvent = getActiveEvent($data);
$activeEventId = $data['active_event_id'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Painel Administrativo</h1>
            <a href="treta.php?logout=true" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> Sair</a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2><i class="bi bi-gear-fill"></i> Gerenciar Votações</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="treta.php" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="event_name" class="form-control" placeholder="Nome da nova votação" required>
                        <button type="submit" name="create_event" class="btn btn-primary">Criar Nova</button>
                    </div>
                </form>
                <form method="POST" action="treta.php" class="row g-2 align-items-center">
                    <div class="col-auto flex-grow-1">
                        <select name="event_id" class="form-select">
                            <?php foreach ($data['events'] as $id => $event): ?>
                                <option value="<?= $id ?>" <?= ($id === $activeEventId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($event['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" name="set_active_event" class="btn btn-success">Ativar</button>
                    </div>
                    <div class="col-auto">
                        <button type="submit" name="delete_event" class="btn btn-danger" onclick="return confirm('Tem certeza?');">Deletar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <h2><i class="bi bi-pencil-square"></i> Editando: <span class="text-primary"><?= htmlspecialchars($activeEvent['name']) ?></span></h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4>Gerenciar Categorias</h4>
                        <form method="POST" action="treta.php" class="mb-3">
                            <div class="input-group">
                                <input type="text" name="category_name" class="form-control" placeholder="Nova categoria" required>
                                <button type="submit" name="add_category" class="btn btn-secondary">Adicionar</button>
                            </div>
                        </form>
                        <ul class="list-group">
                            <?php if (!empty($activeEvent['categories'])): ?>
                                <?php foreach (array_keys($activeEvent['categories']) as $category): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($category) ?>
                                        <form method="POST" action="treta.php">
                                            <input type="hidden" name="remove_category_name" value="<?= htmlspecialchars($category) ?>">
                                            <button type="submit" name="remove_category" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item">Nenhuma categoria cadastrada.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h4>Gerenciar Nomes</h4>
                        <?php if (!empty($activeEvent['categories'])): ?>
                            <form method="POST" action="treta.php" class="mb-3">
                                <div class="mb-2">
                                    <input type="text" name="person_name" class="form-control" placeholder="Nome da pessoa" required>
                                </div>
                                <div class="input-group">
                                    <select name="add_name_category" class="form-select">
                                        <?php foreach (array_keys($activeEvent['categories']) as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="add_name" class="btn btn-secondary">Adicionar</button>
                                </div>
                            </form>
                            <?php foreach ($activeEvent['categories'] as $category => $people): ?>
                                <h5 class="mt-3"><?= htmlspecialchars($category) ?></h5>
                                <ul class="list-group">
                                    <?php if (!empty($people)): ?>
                                        <?php foreach (array_keys($people) as $person): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?= htmlspecialchars($person) ?>
                                                <form method="POST" action="treta.php">
                                                    <input type="hidden" name="remove_name_category" value="<?= htmlspecialchars($category) ?>">
                                                    <input type="hidden" name="remove_person_name" value="<?= htmlspecialchars($person) ?>">
                                                    <button type="submit" name="remove_name" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item">Nenhum nome nesta categoria.</li>
                                    <?php endif; ?>
                                </ul>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Crie uma categoria primeiro.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <h2><i class="bi bi-chat-dots-fill"></i> Gerenciar Mural de Recados</h2>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <?php if (!empty($data['messages'])): ?>
                        <?php foreach ($data['messages'] as $msg): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="mb-1"><?= htmlspecialchars($msg['message']) ?></p>
                                        <small class="text-muted">
                                            <strong><?= htmlspecialchars($msg['name']) ?></strong> - <?= $msg['date'] ?>
                                        </small>
                                    </div>
                                    <form method="POST" action="treta.php">
                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                        <button type="submit" name="delete_message" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-muted">Nenhum recado no mural.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
