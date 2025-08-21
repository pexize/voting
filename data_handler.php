<?php

// --- Funções Básicas de Leitura/Escrita ---

function get_data_file_path() {
    return __DIR__ . '/data.json';
}

function readData() {
    $filePath = get_data_file_path();
    if (!file_exists($filePath)) {
        return initializeData();
    }
    $jsonContent = file_get_contents($filePath);
    $data = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['events'])) {
        return initializeData();
    }
    return $data;
}

function writeData($data) {
    $filePath = get_data_file_path();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($filePath, $json, LOCK_EX);
}

function initializeData() {
    $defaultEventId = 'evt_' . time();
    $initialData = [
        'password' => 'admin123',
        'active_event_id' => $defaultEventId,
        'events' => [
            $defaultEventId => [
                'name' => 'Votação Principal',
                'categories' => [
                    'Melhor Categoria' => ['Pessoa 1' => 0, 'Pessoa 2' => 0]
                ]
            ]
        ],
        'messages' => []
    ];
    writeData($initialData);
    return $initialData;
}

// --- Funções de Gerenciamento de Eventos ---

function createEvent(&$data, $eventName) {
    $eventName = trim($eventName);
    if (empty($eventName)) return false;

    $newEventId = 'evt_' . time();
    $data['events'][$newEventId] = [
        'name' => $eventName,
        'categories' => []
    ];
    $data['active_event_id'] = $newEventId; // O novo evento se torna o ativo
    writeData($data);
    return true;
}

function setActiveEvent(&$data, $eventId) {
    if (!isset($data['events'][$eventId])) return false;
    $data['active_event_id'] = $eventId;
    writeData($data);
    return true;
}

function deleteEvent(&$data, $eventId) {
    // Não permite deletar o último evento
    if (count($data['events']) <= 1 || !isset($data['events'][$eventId])) {
        return false;
    }
    unset($data['events'][$eventId]);
    // Se o evento deletado era o ativo, torna o primeiro da lista o novo ativo
    if ($data['active_event_id'] === $eventId) {
        reset($data['events']);
        $data['active_event_id'] = key($data['events']);
    }
    writeData($data);
    return true;
}

function getActiveEvent($data) {
    $activeId = $data['active_event_id'] ?? null;
    if ($activeId && isset($data['events'][$activeId])) {
        return $data['events'][$activeId];
    }
    // Fallback se o ID ativo for inválido
    return reset($data['events']) ?: null;
}

// --- Funções de Votação (operam no evento ativo) ---

function addCategory(&$data, $categoryName) {
    $categoryName = trim($categoryName);
    $activeId = $data['active_event_id'];
    if (empty($categoryName) || isset($data['events'][$activeId]['categories'][$categoryName])) {
        return false;
    }
    $data['events'][$activeId]['categories'][$categoryName] = [];
    writeData($data);
    return true;
}

function removeCategory(&$data, $categoryName) {
    $activeId = $data['active_event_id'];
    if (!isset($data['events'][$activeId]['categories'][$categoryName])) {
        return false;
    }
    unset($data['events'][$activeId]['categories'][$categoryName]);
    writeData($data);
    return true;
}

function addName(&$data, $categoryName, $personName) {
    $personName = trim($personName);
    $activeId = $data['active_event_id'];
    if (empty($personName) || !isset($data['events'][$activeId]['categories'][$categoryName]) || isset($data['events'][$activeId]['categories'][$categoryName][$personName])) {
        return false;
    }
    $data['events'][$activeId]['categories'][$categoryName][$personName] = 0;
    writeData($data);
    return true;
}

function removeName(&$data, $categoryName, $personName) {
    $activeId = $data['active_event_id'];
    if (!isset($data['events'][$activeId]['categories'][$categoryName][$personName])) {
        return false;
    }
    unset($data['events'][$activeId]['categories'][$categoryName][$personName]);
    writeData($data);
    return true;
}

function recordVote(&$data, $categoryName, $personName) {
    $activeId = $data['active_event_id'];
    if (!isset($data['events'][$activeId]['categories'][$categoryName][$personName])) {
        return false;
    }
    $data['events'][$activeId]['categories'][$categoryName][$personName]++;
    writeData($data);
    return true;
}

function verifyPassword($password) {
    $data = readData();
    return isset($data['password']) && $password === $data['password'];
}

// --- Funções do Mural de Recados ---

function addMessage(&$data, $name, $message) {
    $name = trim($name);
    $message = trim($message);
    if (empty($name) || empty($message)) {
        return false;
    }
    $newMessage = [
        'id' => 'msg_' . time() . '_' . uniqid(),
        'name' => $name,
        'message' => $message,
        'date' => date('d/m/Y H:i')
    ];
    // Adiciona a nova mensagem no início do array
    array_unshift($data['messages'], $newMessage);
    writeData($data);
    return true;
}

function deleteMessage(&$data, $messageId) {
    $initialCount = count($data['messages']);
    $data['messages'] = array_filter($data['messages'], function($message) use ($messageId) {
        return $message['id'] !== $messageId;
    });
    // Reindexa o array para evitar problemas com JSON
    $data['messages'] = array_values($data['messages']);
    if (count($data['messages']) < $initialCount) {
        writeData($data);
        return true;
    }
    return false;
}
