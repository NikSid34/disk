<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$body = file_get_contents('php://input') ?: '';

header('Content-Type: application/json; charset=UTF-8');

if ($path === '/v1/chat/completions') {
    if (str_contains($body, 'RETURN_INVALID_JSON')) {
        echo '{"choices":';
        return;
    }

    if (str_contains($body, 'RETURN_BAD_STRUCTURE')) {
        echo json_encode(['choices' => [['message' => ['text' => 'missing-content']]]], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo json_encode([
            'choices' => [
                    [
                            'message' => [
                                    'content' => 'Краткое описание',
                            ],
                    ],
            ],
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if ($path === '/v1/embeddings') {
    if (str_contains($body, 'RETURN_INVALID_JSON')) {
        echo '{"data":';
        return;
    }

    if (str_contains($body, 'RETURN_BAD_STRUCTURE')) {
        echo json_encode(['data' => [['vector' => []]]], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo json_encode([
            'data' => [
                    [
                            'embedding' => [0.1, 0.2, 0.3],
                    ],
            ],
    ], JSON_UNESCAPED_UNICODE);
    return;
}

http_response_code(404);
echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
