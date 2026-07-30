<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = [];
parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $query);
$method = $_SERVER['REQUEST_METHOD'];
$token = $_SERVER['HTTP_X_DATAVERSE_KEY'] ?? ($_SERVER['PHP_AUTH_USER'] ?? '');
$persistentId = $query['persistentId'] ?? 'doi:10.5072/FK2/CONTROLLED';
$persistentUri = 'https://doi.org/' . preg_replace('/^doi:/', '', $persistentId);

error_log($method . ' ' . $path);

header('Content-Type: application/json');

if ($path === '/health') {
    echo json_encode(['status' => 'OK']);
    return;
}

$statePath = sys_get_temp_dir() . '/controlled-dataverse-state.json';
$state = is_file($statePath)
    ? json_decode(file_get_contents($statePath), true)
    : ['published' => false, 'files' => []];

$saveState = static function (array $newState) use ($statePath): void {
    file_put_contents($statePath, json_encode($newState), LOCK_EX);
};

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

if ($method === 'POST' && $path === '/reset') {
    $state = ['published' => false, 'files' => []];
    $saveState($state);
    echo json_encode(['status' => 'OK']);
    return;
}

if ($path === '/api/dataverses/testDataverse') {
    echo json_encode([
        'status' => 'OK',
        'data' => [
            'id' => 1,
            'alias' => 'testDataverse',
            'name' => 'Dataverse de Exemplo Lepidus',
        ],
    ]);
    return;
}

if ($path === '/api/dataverses/:root') {
    echo json_encode([
        'status' => 'OK',
        'data' => [
            'id' => 1,
            'alias' => 'root',
            'name' => 'Controlled Dataverse',
        ],
    ]);
    return;
}

if ($method === 'GET' && $path === '/api/licenses') {
    echo json_encode([
        'status' => 'OK',
        'data' => [
            [
                'name' => 'CC0 1.0',
                'uri' => 'https://creativecommons.org/publicdomain/zero/1.0/',
                'isDefault' => true,
            ],
            [
                'name' => 'CC BY 4.0',
                'uri' => 'https://creativecommons.org/licenses/by/4.0/',
                'isDefault' => false,
            ],
        ],
    ]);
    return;
}

if ($method === 'GET' && $path === '/api/dataverses/testDataverse/metadatablocks') {
    echo json_encode(['status' => 'OK', 'data' => []]);
    return;
}

if ($method === 'GET' && $path === '/api/users/token') {
    echo json_encode([
        'status' => 'OK',
        'data' => ['message' => 'Token expires on 2099-12-31'],
    ]);
    return;
}

if ($method === 'POST' && $path === '/api/dataverses/testDataverse/datasets') {
    $state['published'] = false;
    $state['files'] = [];
    $saveState($state);
    echo json_encode([
        'status' => 'OK',
        'data' => [
            'id' => 101,
            'persistentId' => 'doi:10.5072/FK2/CONTROLLED',
            'protocol' => 'doi',
            'authority' => '10.5072',
            'identifier' => 'FK2/CONTROLLED',
        ],
    ]);
    return;
}

if ($method === 'POST' && $path === '/api/datasets/:persistentId/add') {
    $fileId = count($state['files']) + 1001;
    $fileName = $_FILES['file']['name'] ?? 'controlled-file-' . $fileId;
    $state['files'][] = ['id' => $fileId, 'name' => $fileName];
    $saveState($state);
    echo json_encode(['status' => 'OK', 'data' => ['files' => [['id' => $fileId]]]]);
    return;
}

if ($method === 'GET' && $path === '/api/datasets/:persistentId/versions') {
    $files = array_map(static function (array $file): array {
        return [
            'label' => $file['name'],
            'dataFile' => ['id' => $file['id'], 'filename' => $file['name']],
        ];
    }, $state['files']);
    echo json_encode([
        'status' => 'OK',
        'data' => [[
            'datasetId' => 101,
            'datasetPersistentId' => $persistentId,
            'versionState' => $state['published'] ? 'RELEASED' : 'DRAFT',
            'license' => ['name' => 'CC BY 4.0'],
            'metadataBlocks' => [
                'citation' => [
                    'fields' => [
                        ['typeName' => 'title', 'typeClass' => 'primitive', 'value' => 'Controlled dataset'],
                        ['typeName' => 'author', 'typeClass' => 'compound', 'value' => [[
                            'authorName' => ['value' => 'Controlled, Author'],
                        ]]],
                        ['typeName' => 'datasetContact', 'typeClass' => 'compound', 'value' => [[
                            'datasetContactName' => ['value' => 'Controlled, Contact'],
                            'datasetContactEmail' => ['value' => 'contact@example.test'],
                        ]]],
                        ['typeName' => 'dsDescription', 'typeClass' => 'compound', 'value' => [[
                            'dsDescriptionValue' => ['value' => 'Controlled description'],
                        ]]],
                        ['typeName' => 'subject', 'typeClass' => 'controlledVocabulary', 'value' => ['Other']],
                        ['typeName' => 'language', 'typeClass' => 'controlledVocabulary', 'value' => ['English']],
                        ['typeName' => 'publication', 'typeClass' => 'compound', 'value' => [[
                            'publicationRelationType' => ['value' => 'IsCitedBy'],
                            'publicationCitation' => ['value' => 'Controlled related publication'],
                        ]]],
                    ],
                ],
            ],
            'files' => $files,
        ]],
    ]);
    return;
}

if ($method === 'GET' && $path === '/api/datasets/:persistentId/versions/:latest/files') {
    $files = array_map(static function (array $file): array {
        return [
            'label' => $file['name'],
            'dataFile' => ['id' => $file['id'], 'filename' => $file['name']],
        ];
    }, $state['files']);
    echo json_encode(['status' => 'OK', 'data' => $files]);
    return;
}

if ($method === 'GET' && $path === '/api/datasets/export') {
    echo json_encode([
        'status' => 'OK',
        'datasetVersion' => [
            'citation' => 'Controlled, Author, 2099, "Controlled dataset", Controlled Dataverse, V1, '
                . $persistentUri,
        ],
        'persistentUrl' => $persistentUri,
    ]);
    return;
}

if ($method === 'PUT' && $path === '/api/datasets/:persistentId/versions/:draft') {
    echo json_encode(['status' => 'OK']);
    return;
}

if ($method === 'DELETE' && $path === '/api/datasets/:persistentId/versions/:draft') {
    $state = ['published' => false, 'files' => []];
    $saveState($state);
    echo json_encode(['status' => 'OK']);
    return;
}

if ($method === 'POST' && $path === '/api/datasets/:persistentId/actions/:publish') {
    $state['published'] = true;
    $saveState($state);
    echo json_encode(['status' => 'OK']);
    return;
}

if ($method === 'GET' && preg_match('~^/api/datasets/\d+/locks$~', $path)) {
    echo json_encode(['status' => 'OK', 'data' => []]);
    return;
}

if ($method === 'GET' && strpos($path, '/dvn/api/data-deposit/v1.1/swordv2/edit/study/') === 0) {
    header('Content-Type: application/atom+xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>'
        . '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:bib="http://purl.org/net/biblio#">'
        . '<link href="one"/><link href="two"/><link href="three"/><link href="four"/>'
        . '<link href="https://doi.org/10.5072/FK2/CONTROLLED"/>'
        . '<bib:bibliographicCitation>Controlled, Author (2099), Controlled dataset, '
        . 'https://doi.org/10.5072/FK2/CONTROLLED</bib:bibliographicCitation>'
        . '</entry>';
    return;
}

if ($method === 'DELETE' && strpos($path, '/dvn/api/data-deposit/v1.1/swordv2/edit-media/file/') === 0) {
    echo json_encode(['status' => 'OK']);
    return;
}

http_response_code(404);
echo json_encode(['status' => 'ERROR', 'message' => 'Resource not found']);
