<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$token = $_SERVER['HTTP_X_DATAVERSE_KEY'] ?? '';

header('Content-Type: application/json');

if ($path === '/health') {
    echo json_encode(['status' => 'OK']);
    return;
}

if ($token === 'expired-token') {
    http_response_code(401);
    echo json_encode(['status' => 'ERROR', 'message' => 'API token has expired']);
    return;
}

if ($token === 'unavailable-token') {
    http_response_code(503);
    echo json_encode(['status' => 'ERROR', 'message' => 'Dataverse temporarily unavailable']);
    return;
}

if ($token !== 'valid-token') {
    http_response_code(403);
    echo json_encode(['status' => 'ERROR', 'message' => 'Bad API key']);
    return;
}

if ($path === '/api/dataverses/testDataverse') {
    echo json_encode([
        'status' => 'OK',
        'data' => [
            'id' => 1,
            'alias' => 'testDataverse',
            'name' => 'Controlled Dataverse',
        ],
    ]);
    return;
}

http_response_code(404);
echo json_encode(['status' => 'ERROR', 'message' => 'Resource not found']);
