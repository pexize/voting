<?php

/**
 * data_handler.php
 * 
 * Este arquivo centraliza toda a manipulação de dados do sistema de votação.
 * Ele é responsável por ler, escrever e modificar o arquivo data.json,
 * garantindo a consistência e a segurança dos dados.
 */

// --- Constantes e Configurações ---
define('DATA_FILE_PATH', __DIR__ . '/data.json');
define('MAX_NAME_LENGTH', 50);
define('MAX_CATEGORY_LENGTH', 100);
define('MAX_EVENT_NAME_LENGTH', 100);
define('MAX_MESSAGE_LENGTH', 280);
define('MAX_USERNAME_LENGTH', 50);

// --- Funções Básicas de Leitura/Escrita ---

/**
 * Lê os dados do arquivo JSON.
 * Se o arquivo não existir ou estiver corrompido, inicializa com dados padrão.
 * @return array Os dados decodificados do arquivo JSON.
 */
function readData() {
    if (!file_exists(DATA_FILE_PATH)) {
        return initializeData();
    }
    $jsonContent = file_get_contents(DATA_FILE_PATH);
    $data = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !isset($data['events'])) {
        return initializeData();
    }
    return $data;
}

/**
 * Escreve os dados no arquivo JSON.
 * Utiliza LOCK_EX para prevenir condições de corrida durante a escrita.
 * @param array $data Os dados a serem escritos no arquivo.
 */
function writeData($data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents(DATA_FILE_PATH, $json, LOCK_EX);
}

/**
 * Inicializa o arquivo de dados com uma estrutura padrão.
 * @return array A estrutura de dados inicial.
 */
function initializeData() {
    $defaultEventId = 'evt_' . time();
    $initialData = [
        'password' => 'admin123',
        'active_event_id' => $defaultEventId,
        'events' => [
            $defaultEventId => [
                'name' => 'Votação de Exemplo',
                'categories' => [
                    'Melhor Funcionalidade' => ['Mural de Recados' => 0, 'Rankings Dinâmicos' => 0]
                ]
            ]
        ],
        'messages' => []
    ];
    writeData($initialData);
    return $initialData;
}

// --- Funções de Gerenciamento de Eventos ---

/**
 * Cria um novo evento de votação.
 * @param array &$data Referência para o array de dados principal.
 * @param string $eventName Nome do novo evento.
 * @return bool True em sucesso, false em falha.
 */
function createEvent(&$data, $eventName) {
    $eventName = htmlspecialchars(trim($eventName), ENT_QUOTES, 'UTF-8');
    if (empty($eventName) || strlen($eventName) > MAX_EVENT_NAME_LENGTH) {
        return false;
    }

    $newEventId = 'evt_' . time() . '_' . uniqid();
    $data['events'][$newEventId] = [
        'name' => $eventName,
        'categories' => []
    ];
    $data['active_event_id'] = $newEventId; // O novo evento se torna o ativo
    writeData($data);
    return true;
}

/**
 * Define qual evento de votação está ativo.
 * @param array &$data Referência para o array de dados principal.
 * @param string $eventId ID do evento a ser ativado.
 * @return bool True em sucesso, false se o evento não existir.
 */
function setActiveEvent(&$data, $eventId) {
    if (!isset($data['events'][$eventId])) {
        return false;
    }
    $data['active_event_id'] = $eventId;
    writeData($data);
    return true;
}

/**
 * Deleta um evento de votação.
 * Não permite deletar o último evento existente.
 * @param array &$data Referência para o array de dados principal.
 * @param string $eventId ID do evento a ser deletado.
 * @return bool True em sucesso, false em falha.
 */
function deleteEvent(&$data, $eventId) {
    if (count($data['events']) <= 1 || !isset($data['events'][$eventId])) {
        return false;
    }
    unset($data['events'][$eventId]);

    if ($data['active_event_id'] === $eventId) {
        reset($data['events']);
        $data['active_event_id'] = key($data['events']);
    }
    writeData($data);
    return true;
}

/**
 * Retorna o evento de votação ativo no momento.
 * @param array $data O array de dados principal.
 * @return array|null O array do evento ativo ou null se não houver.
 */
function getActiveEvent($data) {
    $activeId = $data['active_event_id'] ?? null;
    if ($activeId && isset($data['events'][$activeId])) {
        return $data['events'][$activeId];
    }
    // Fallback se o ID ativo for inválido ou não existir
    return reset($data['events']) ?: null;
}

// --- Funções de Votação (operam no evento ativo) ---

/**
 * Adiciona uma nova categoria ao evento ativo.
 * @param array &$data Referência para o array de dados principal.
 * @param string $categoryName Nome da nova categoria.
 * @return bool True em sucesso, false em falha.
 */
function addCategory(&$data, $categoryName) {
    $categoryName = htmlspecialchars(trim($categoryName), ENT_QUOTES, 'UTF-8');
    $activeId = $data['active_event_id'];
    if (empty($categoryName) || strlen($categoryName) > MAX_CATEGORY_LENGTH || isset($data['events'][$activeId]['categories'][$categoryName])) {
        return false;
    }
    $data['events'][$activeId]['categories'][$categoryName] = [];
    writeData($data);
    return true;
}

/**
 * Remove uma categoria do evento ativo.
 * @param array &$data Referência para o array de dados principal.
 * @param string $categoryName Nome da categoria a ser removida.
 * @return bool True em sucesso, false se a categoria não existir.
 */
function removeCategory(&$data, $categoryName) {
    $activeId = $data['active_event_id'];
    if (!isset($data['events'][$activeId]['categories'][$categoryName])) {
        return false;
    }
    unset($data['events'][$activeId]['categories'][$categoryName]);
    writeData($data);
    return true;
}

/**
 * Adiciona um novo nome (pessoa/opção) a uma categoria do evento ativo.
 * @param array &$data Referência para o array de dados principal.
 * @param string $categoryName Categoria onde o nome será adicionado.
 * @param string $personName Nome a ser adicionado.
 * @return bool True em sucesso, false em falha.
 */
function addName(&$data, $categoryName, $personName) {
    $personName = htmlspecialchars(trim($personName), ENT_QUOTES, 'UTF-8');
    $activeId = $data['active_event_id'];
    if (empty($personName) || strlen($personName) > MAX_NAME_LENGTH || !isset($data['events'][$activeId]['categories'][$categoryName]) || isset($data['events'][$activeId]['categories'][$categoryName][$personName])) {
        return false;
    }
    $data['events'][$activeId]['categories'][$categoryName][$personName] = 0;
    writeData($data);
    return true;
}

/**
 * Remove um nome de uma categoria do evento ativo.
 * @param array &$data Referência para o array de dados principal.
 * @param string $categoryName Categoria da qual o nome será removido.
 * @param string $personName Nome a ser removido.
 * @return bool True em sucesso, false se o nome não existir.
 */
function removeName(&$data, $categoryName, $personName) {
    $activeId = $data['active_event_id'];
    if (!isset($data['events'][$activeId]['categories'][$categoryName][$personName])) {
        return false;
    }
    unset($data['events'][$activeId]['categories'][$categoryName][$personName]);
    writeData($data);
    return true;
}

/**
 * Registra um voto para um nome em uma categoria do evento ativo.
 * @param array &$data Referência para o array de dados principal.
 * @param string $categoryName Categoria do voto.
 * @param string $personName Nome que recebeu o voto.
 * @return bool True em sucesso, false se a opção de voto for inválida.
 */
function recordVote(&$data, $categoryName, $personName) {
    $activeId = $data['active_event_id'];
    if (!isset($data['events'][$activeId]['categories'][$categoryName][$personName])) {
        return false;
    }
    $data['events'][$activeId]['categories'][$categoryName][$personName]++;
    writeData($data);
    return true;
}

// --- Funções de Autenticação ---

/**
 * Verifica se a senha fornecida corresponde à senha no arquivo de dados.
 * @param string $password A senha a ser verificada.
 * @return bool True se a senha for correta, false caso contrário.
 */
function verifyPassword($password) {
    $data = readData();
    return isset($data['password']) && hash_equals($data['password'], $password);
}

/**
 * Obtém o endereço IP real do visitante, mesmo atrás de um proxy.
 * @return string O endereço IP.
 */
function getIpAddress() {
    // Verifica se o IP é de um proxy
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Pode ser uma lista de IPs, o primeiro é o original
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    // Verifica se o IP é de um cliente de internet compartilhada
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    // Retorna o IP do host remoto (padrão)
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

// --- Funções do Mural de Recados ---

/**
 * Adiciona uma nova mensagem ao mural de recados.
 * @param array &$data Referência para o array de dados principal.
 * @param string $name Nome do autor da mensagem.
 * @param string $message Conteúdo da mensagem.
 * @return array|bool O array da nova mensagem em sucesso, false em falha.
 */
function addMessage(&$data, $name, $message) {
    $name = htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(trim($message), ENT_QUOTES, 'UTF-8');

    if (empty($name) || empty($message) || strlen($name) > MAX_USERNAME_LENGTH || strlen($message) > MAX_MESSAGE_LENGTH) {
        return false;
    }

    $newMessage = [
        'id' => 'msg_' . time() . '_' . uniqid(),
        'name' => $name,
        'message' => $message,
        'date' => date('d/m/Y H:i')
    ];
    
    array_unshift($data['messages'], $newMessage);
    writeData($data);
    return $newMessage;
}

/**
 * Deleta uma mensagem do mural de recados.
 * @param array &$data Referência para o array de dados principal.
 * @param string $messageId ID da mensagem a ser deletada.
 * @return bool True em sucesso, false se a mensagem não for encontrada.
 */
function deleteMessage(&$data, $messageId) {
    $initialCount = count($data['messages']);
    $data['messages'] = array_filter($data['messages'], function($msg) use ($messageId) {
        return $msg['id'] !== $messageId;
    });
    
    $data['messages'] = array_values($data['messages']); // Reindexa o array
    
    if (count($data['messages']) < $initialCount) {
        writeData($data);
        return true;
    }
    return false;
}

// --- Funções de Logging ---

/**
 * Registra um voto individual em um arquivo de log separado para auditoria.
 * @param string $eventId ID do evento.
 * @param string $eventName Nome do evento.
 * @param string $categoryName Categoria votada.
 * @param string $personName Pessoa votada.
 * @param string $ipAddress Endereço IP do votante.
 * @param string $userAgent User agent do votante.
 */
function logVoteToFile($eventId, $eventName, $categoryName, $personName, $ipAddress, $userAgent) {
    $logFilePath = __DIR__ . '/vote_log.json';
    
    $logData = [];
    if (file_exists($logFilePath)) {
        $logContent = file_get_contents($logFilePath);
        $logData = json_decode($logContent, true);
        if (!is_array($logData)) {
            $logData = []; // Reseta se o JSON for inválido
        }
    }

    $newLogEntry = [
        'event_id' => $eventId,
        'event_name' => $eventName,
        'category' => $categoryName,
        'person' => $personName,
        'ip_address' => $ipAddress,
        'timestamp' => date('c'),
        'user_agent' => $userAgent
    ];

    $logData[] = $newLogEntry;

    file_put_contents($logFilePath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Registra uma mensagem do mural em um arquivo de log separado.
 * @param string $name Nome do autor.
 * @param string $message Conteúdo da mensagem.
 * @param string $ipAddress Endereço IP do autor.
 * @param string $userAgent User agent do autor.
 */
function logMessageToFile($name, $message, $ipAddress, $userAgent) {
    $logFilePath = __DIR__ . '/message_log.json';
    
    $logData = [];
    if (file_exists($logFilePath)) {
        $logContent = file_get_contents($logFilePath);
        $logData = json_decode($logContent, true);
        if (!is_array($logData)) {
            $logData = []; // Reseta se o JSON for inválido
        }
    }

    $newLogEntry = [
        'name' => $name,
        'message' => $message,
        'ip_address' => $ipAddress,
        'timestamp' => date('c'), // Formato ISO 8601
        'user_agent' => $userAgent
    ];

    $logData[] = $newLogEntry;

    file_put_contents($logFilePath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
