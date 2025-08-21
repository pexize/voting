<?php
header('Content-Type: application/json');
require_once 'data_handler.php';

$data = readData();
$activeEventId = $data['active_event_id'];

if (!isset($_POST['category'], $_POST['person'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$category = $_POST['category'];
$person = $_POST['person'];

$cookie_name = 'voted_' . $activeEventId . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $category);

if (isset($_COOKIE[$cookie_name])) {
    echo json_encode(['success' => false, 'message' => 'Você já votou nesta categoria.']);
    exit;
}

if (recordVote($data, $category, $person)) {
    setcookie($cookie_name, 'true', time() + (86400 * 30), "/"); // Cookie de 30 dias

    // Retorna os dados atualizados do evento que acabou de ser modificado
    $updatedData = readData();
    $activeEvent = getActiveEvent($updatedData);

    echo json_encode([
        'success' => true,
        'message' => 'Voto registrado com sucesso!',
        'categories' => $activeEvent['categories']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar o voto.']);
}
