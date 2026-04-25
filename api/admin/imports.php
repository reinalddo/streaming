<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/imports.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'preview_services') {
        $result = importPreviewServiceAccounts($_FILES['archivo'] ?? []);
    } elseif ($action === 'preview_users') {
        $result = importPreviewRegisteredUsers($_FILES['archivo'] ?? []);
    } elseif ($action === 'preview_assignments') {
        $result = importPreviewServiceAssignments($_FILES['archivo'] ?? []);
    } elseif ($action === 'process') {
        $result = processPreparedImport((string) ($_POST['import_key'] ?? ''));
    } else {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code($result['success'] ? 200 : 422);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible completar la importación.'], JSON_UNESCAPED_UNICODE);
}