<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/user.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $result = getCurrentUserModuleOverview();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible cargar tu módulo de usuario.'], JSON_UNESCAPED_UNICODE);
}