<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = getAdminOverview();
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible cargar el panel de administracion.'], JSON_UNESCAPED_UNICODE);
}