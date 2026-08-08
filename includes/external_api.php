<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/user.php';

function validateBotCodigosApiToken(PDO $pdo, string $token): bool
{
    ensureBotCodigosApiTokensTable($pdo);

    if ($token === '') {
        return false;
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT id FROM bot_codigos_api_tokens WHERE token_hash = :token_hash AND activo = 1 LIMIT 1');
    $stmt->execute(['token_hash' => $tokenHash]);
    $row = $stmt->fetch();

    if ($row === false) {
        return false;
    }

    $pdo->prepare('UPDATE bot_codigos_api_tokens SET last_used_at = NOW() WHERE id = :id')
        ->execute(['id' => (int) $row['id']]);

    return true;
}

function resolveSellerUserByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare("SELECT id, email FROM usuarios WHERE email = :email AND role = 'usuario' LIMIT 1");
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function findResellerUserIdsForSeller(PDO $pdo, int $sellerUserId): array
{
    $stmt = $pdo->prepare('SELECT revendedor_usuario_id FROM usuario_revendedor_vendedores WHERE vendedor_usuario_id = :vendedor_usuario_id');
    $stmt->execute(['vendedor_usuario_id' => $sellerUserId]);

    return array_map(static fn(array $row): int => (int) $row['revendedor_usuario_id'], $stmt->fetchAll());
}

function assignAccountToSellerByEmails(PDO $pdo, string $accountEmail, string $sellerEmail): array
{
    $seller = resolveSellerUserByEmail($pdo, $sellerEmail);

    if ($seller === null) {
        return ['success' => false, 'http_status' => 404, 'message' => 'El correo del vendedor indicado no existe en prycorreos.'];
    }

    $accountStmt = $pdo->prepare('SELECT id FROM cuentas_servicio WHERE correo_acceso = :correo_acceso LIMIT 1');
    $accountStmt->execute(['correo_acceso' => $accountEmail]);
    $account = $accountStmt->fetch();

    if ($account === false) {
        return ['success' => false, 'http_status' => 404, 'message' => 'El correo de la cuenta indicada no existe.'];
    }

    $resellerUserIds = findResellerUserIdsForSeller($pdo, (int) $seller['id']);

    if ($resellerUserIds === []) {
        return ['success' => false, 'http_status' => 404, 'message' => 'El correo del vendedor no está vinculado a ningún revendedor.'];
    }

    foreach ($resellerUserIds as $resellerUserId) {
        $scope = fetchResellerModuleScope($pdo, $resellerUserId);

        if ($scope['enabled'] && in_array((int) $account['id'], $scope['account_ids'], true)) {
            $result = insertUserAccountAssignment($pdo, (int) $seller['id'], (int) $account['id']);
            $result['http_status'] = $result['success'] ? 200 : 409;

            return $result;
        }
    }

    return ['success' => false, 'http_status' => 404, 'message' => 'El correo de la cuenta indicada no pertenece al revendedor de ese vendedor.'];
}

function unassignAccountFromSellerByEmails(PDO $pdo, string $accountEmail, string $sellerEmail): array
{
    $seller = resolveSellerUserByEmail($pdo, $sellerEmail);

    if ($seller === null) {
        return ['success' => false, 'http_status' => 404, 'message' => 'El correo del vendedor indicado no existe en prycorreos.'];
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
