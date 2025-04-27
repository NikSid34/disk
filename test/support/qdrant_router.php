<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = file_get_contents('php://input') ?: '';
$stateFile = getenv('QDRANT_STATE_FILE') ?: sys_get_temp_dir() . '/simpledisk-qdrant-state.json';
$created = is_file($stateFile) && trim((string) file_get_contents($stateFile)) === '1';

header('Content-Type: application/json; charset=UTF-8');

if ($path === '/collections/files' && $method === 'HEAD') {
    http_response_code($created ? 200 : 404);
    return;
}

if ($path === '/collections/files' && $method === 'PUT') {
    file_put_contents($stateFile, '1');
    http_response_code(200);
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    return;
}

if ($path === '/collections/files/points' && $method === 'PUT') {
    if (str_contains($body, 'fail-save')) {
        http_response_code(500);
        echo json_encode(['status' => 'error'], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    return;
}

if ($path === '/collections/files/points/search' && $method === 'POST') {
    if (str_contains($body, '"value":999')) {
        echo '{"result":';
        return;
    }

    if (str_contains($body, '"value":998')) {
        http_response_code(500);
        echo json_encode(['status' => 'error'], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(200);
    echo json_encode([
            'result' => [
                    [
                            'payload' => ['file_hash' => 'hash-a'],
                            'score' => 0.8234,
                    ],
                    [
                            'payload' => [],
                            'score' => 0.9,
                    ],
                    [
                            'payload' => ['file_hash' => 'hash-b'],
                            'score' => 0.4,
                    ],
            ],
    ], JSON_UNESCAPED_UNICODE);
    return;
}

http_response_code(404);
echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
