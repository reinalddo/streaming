<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/user.php';

function resolveResellerByApiToken(PDO $pdo, string $token): ?array
{
    ensureRevendedorApiTokensTable($pdo);

    if ($token === '') {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        "SELECT rat.id AS token_id, u.id AS usuario_id, u.email
         FROM revendedor_api_tokens rat
         INNER JOIN usuarios u ON u.id = rat.revendedor_usuario_id
         WHERE rat.token_hash = :token_hash AND rat.activo = 1 AND u.role = 'usuario' AND u.revendedor = 1 AND u.activo = 1
         LIMIT 1"
    );
    $stmt->execute(['token_hash' => $tokenHash]);
    $row = $stmt->fetch();

    if ($row === false) {
        return null;
    }

    $pdo->prepare('UPDATE revendedor_api_tokens SET last_used_at = NOW() WHERE id = :id')
        ->execute(['id' => (int) $row['token_id']]);

    return $row;
}

function assignAccountToSellerByEmailsForReseller(PDO $pdo, int $resellerUserId, string $accountEmail, string $sellerEmail): array
{
    $scope = fetchResellerModuleScope($pdo, $resellerUserId);

    if (!$scope['enabled']) {
        return ['success' => false, 'http_status' => 403, 'message' => 'El revendedor no está habilitado.'];
    }

    $sellerStmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND role = 'usuario' LIMIT 1");
    $sellerStmt->execute(['email' => $sellerEmail]);
    $seller = $sellerStmt->fetch();

    if ($seller === false || !in_array((int) $seller['id'], $scope['seller_user_ids'], true)) {
        return ['success' => false, 'http_status' => 404, 'message' => 'El correo del vendedor indicado no pertenece a este revendedor.'];
    }

    $accountStmt = $pdo->prepare('SELECT id FROM cuentas_servicio WHERE correo_acceso = :correo_acceso LIMIT 1');
    $accountStmt->execute(['correo_acceso' => $accountEmail]);
    $account = $accountStmt->fetch();

    if ($account === false || !in_array((int) $account['id'], $scope['account_ids'], true)) {
        return ['success' => false, 'http_status' => 404, 'message' => 'El correo de la cuenta indicada no pertenece a este revendedor.'];
    }

    $result = insertUserAccountAssignment($pdo, (int) $seller['id'], (int) $account['id']);
    $result['http_status'] = $result['success'] ? 200 : 409;

    return $result;
}

function unassignAccountFromSellerByEmailsForReseller(PDO $pdo, int $resellerUserId, string $accountEmail, string $sellerEmail): array
{
    $scope = fetchResellerModuleScope($pdo, $resellerUserId);

    if (!$scope['enabled']) {
        return ['success' => false, 'http_status' => 403, 'message' => 'El revendedor no está habilitado.'];
    }

    $sellerStmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND role = 'usuario' LIMIT 1");
    $sellerStmt->execute(['email' => $sellerEmail]);
    $seller = $sellerStmt->fetch();

    if ($seller === false || !in_array((int) $seller['id'], $scope['seller_user_ids'], true)) {
        return ['success' => false, 'http_status' => 404, 'message' => 'El correo del vendedor indicado no pertenece a este revendedor.'];
    }

    $accountStmt = $pdo->prepare('SELECT id FROM cuentas_servicio WHERE correo_acceso = :correo_acceso LIMIT 1');
    $accountStmt->execute(['correo_acceso' => $accountEmail]);
    $account = $accountStmt->fetch();

    if ($account === false) {
        return ['success' => false, 'http_status' => 404, 'message' => 'El correo de la cuenta indicada no existe.'];
    }

    $result = deleteUserAccountAssignmentsByAccountIdsAndUserIds($pdo, [(int) $account['id']], [(int) $seller['id']]);
    $result['http_status'] = $result['success'] ? 200 : 404;

    return $result;
}
