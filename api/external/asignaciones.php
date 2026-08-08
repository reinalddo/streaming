<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/external_api.php';

header('Content-Type: application/json; charset=UTF-8');

function extractBearerTokenFromRequest(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = (string) $value;
                break;
            }
        }
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        return '';
    }

    return trim($matches[1]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody !== false ? $rawBody : '', true);

    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $token = extractBearerTokenFromRequest();

    if ($token === '') {
        $token = (string) ($payload['token'] ?? '');
    }

    $pdo = getPdo();

    if (!validateBotCodigosApiToken($pdo, $token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token de autenticación inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = strtolower(trim((string) ($payload['action'] ?? '')));

    if ($action === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Debes indicar la acción a realizar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'test' || $action === 'ping') {
        echo json_encode(['success' => true, 'message' => 'Bot conectado correctamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $accountEmail = trim((string) ($payload['account_email'] ?? ''));
    $sellerEmail = trim((string) ($payload['reseller_email'] ?? ''));

    if ($accountEmail === '' || $sellerEmail === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Debes indicar account_email y reseller_email.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'asignar' || $action === 'assign') {
        $result = assignAccountToSellerByEmails($pdo, $accountEmail, $sellerEmail);
    } elseif ($action === 'desasignar' || $action === 'unassign') {
        $result = unassignAccountFromSellerByEmails($pdo, $accountEmail, $sellerEmail);
    } else {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $httpStatus = (int) ($result['http_status'] ?? ($result['success'] ? 200 : 422));
    unset($result['http_status']);

    logBotCodigosApiCall($pdo, $action, $accountEmail, $sellerEmail, (bool) $result['success'], $httpStatus, (string) $result['message']);

    http_response_code($httpStatus);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible procesar la solicitud.'], JSON_UNESCAPED_UNICODE);
}
