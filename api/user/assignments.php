<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/user.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'assign') {
        $result = assignResellerAccountToSellerUser($_POST);
    } elseif ($action === 'unassign') {
        $result = unassignResellerAccountFromSellerUser($_POST);
    } elseif ($action === 'bulk_unassign') {
        $result = bulkUnassignResellerAccountsFromSellerUsersByService($_POST);
    } else {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code($result['success'] ? 200 : 422);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible actualizar la asignación.'], JSON_UNESCAPED_UNICODE);
}