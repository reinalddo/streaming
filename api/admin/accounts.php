<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'update') {
        $result = updateServiceAccount($_POST);
    } elseif ($action === 'delete') {
        $result = deleteServiceAccount($_POST);
    } else {
        $result = createServiceAccount($_POST);
    }
    http_response_code(200);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible crear la cuenta del servicio.'], JSON_UNESCAPED_UNICODE);
}