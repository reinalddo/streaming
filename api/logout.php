<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    clearAuthenticatedUser();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible cerrar sesion.'], JSON_UNESCAPED_UNICODE);
}