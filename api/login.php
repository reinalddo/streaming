<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'exists' => false, 'message' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = loginUser($_POST);
    http_response_code($result['success'] ? 200 : 401);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'exists' => false, 'message' => 'Error interno al iniciar sesion.'], JSON_UNESCAPED_UNICODE);
}