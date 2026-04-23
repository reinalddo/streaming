<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/admin.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'create') {
        $result = createService($_POST, $_FILES);
    } elseif ($action === 'update') {
        $result = updateService($_POST, $_FILES);
    } elseif ($action === 'delete') {
        $result = deleteService($_POST);
    } else {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Accion no valida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code($result['success'] ? 200 : 422);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible procesar el servicio.'], JSON_UNESCAPED_UNICODE);
}