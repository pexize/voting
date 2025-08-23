<?php

/**
 * api.php
 *
 * Endpoint da API para receber e processar votos e mensagens.
 * Responde em formato JSON.
 * 
 * VERSÃO CORRIGIDA para ler dados brutos do POST de forma confiável.
 */

header('Content-Type: application/json');
require_once 'data_handler.php';

// --- Início da Leitura Manual do POST (Versão Corrigida) ---
$postData = [];
// Se $_POST estiver vazio, mas a requisição for POST, tentamos o parse manual.
if (empty($_POST) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_post = file_get_contents('php://input');
    
    // Extrai o boundary do cabeçalho Content-Type
    preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
    if (isset($matches[1])) {
        $boundary = $matches[1];
        $blocks = preg_split("/-+" . $boundary, $raw_post);
        array_pop($blocks); // Remove o último elemento vazio

        foreach ($blocks as $block) {
            if (empty($block)) continue;

            // Extrai o nome do campo e o valor
            if (preg_match('/name=\"([^\"]*)\"\r\n\r\n([^\r\n].*)/s', $block, $matches)) {
                $postData[$matches[1]] = trim($matches[2]);
            }
        }
    }
} else {
    // Se $_POST não estiver vazio, usamos ele.
    $postData = $_POST;
}
// --- Fim da Leitura Manual do POST ---


$action = $postData['action'] ?? '';
$data = readData();

switch ($action) {
    case 'vote':
        handleVote($data, $postData);
        break;
    case 'add_message':
        handleAddMessage($data, $postData);
        break;
    default:
        // Adiciona mais detalhes ao erro para futura depuração, se necessário.
        echo json_encode(['success' => false, 'message' => 'Ação inválida ou dados não recebidos.', 'received_action' => $action]);
        break;
}

/**
 * Processa uma requisição de voto.
 * @param array $data Os dados atuais do sistema.
 * @param array $postData Os dados da requisição.
 */
function handleVote($data, $postData) {
    if (!isset($postData['category'], $postData['person'])) {
        echo json_encode(['success' => false, 'message' => 'Dados de votação inválidos.']);
        exit;
    }

    $category = $postData['category'];
    $person = $postData['person'];
    $activeEventId = $data['active_event_id'];

    $cookie_name = 'voted_' . $activeEventId . '_' . preg_replace('/[^a-zA-Z0-n_]/', '', $category);

    if (isset($_COOKIE[$cookie_name])) {
        echo json_encode(['success' => false, 'message' => 'Você já votou nesta categoria.']);
        exit;
    }

    if (recordVote($data, $category, $person)) {
        setcookie($cookie_name, 'true', time() + (86400 * 30), "/");

        $activeEventForLog = getActiveEvent($data);
        $ipAddress = getIpAddress();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        logVoteToFile($activeEventId, $activeEventForLog['name'], $category, $person, $ipAddress, $userAgent);

        $updatedData = readData();
        $activeEvent = getActiveEvent($updatedData);

        echo json_encode([
            'success' => true,
            'message' => 'Voto registrado com sucesso!',
            'categories' => $activeEvent['categories']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro: Categoria ou pessoa não encontrada.']);
    }
}

/**
 * Processa uma requisição para adicionar uma nova mensagem ao mural.
 * @param array $data Os dados atuais do sistema.
 * @param array $postData Os dados da requisição.
 */
function handleAddMessage($data, $postData) {
    if (!isset($postData['name'], $postData['message'])) {
        echo json_encode(['success' => false, 'message' => 'Dados de mensagem inválidos.']);
        exit;
    }

    $name = $postData['name'];
    $message = $postData['message'];

    $newMessage = addMessage($data, $name, $message);

    if ($newMessage) {
        // Loga a mensagem no arquivo de auditoria
        $ipAddress = getIpAddress();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        logMessageToFile($name, $message, $ipAddress, $userAgent);

        echo json_encode([
            'success' => true,
            'message' => 'Recado enviado com sucesso!',
            'newMessage' => $newMessage
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao enviar recado. Verifique os campos.']);
    }
}
