<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $user = getAuthenticatedUser();

    echo json_encode([
        'authenticated' => $user !== null,
        'user' => $user,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['authenticated' => false, 'user' => null], JSON_UNESCAPED_UNICODE);
}