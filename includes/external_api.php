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

function ensureBotCodigosApiLogTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS bot_codigos_api_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            action VARCHAR(20) NOT NULL,
            account_email VARCHAR(190) NULL,
            reseller_email VARCHAR(190) NULL,
            success TINYINT(1) NOT NULL,
            http_status SMALLINT UNSIGNED NOT NULL,
            message VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_bot_codigos_api_log_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function logBotCodigosApiCall(PDO $pdo, string $action, string $accountEmail, string $sellerEmail, bool $success, int $httpStatus, string $message): void
{
    ensureBotCodigosApiLogTable($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO bot_codigos_api_log (action, account_email, reseller_email, success, http_status, message)
         VALUES (:action, :account_email, :reseller_email, :success, :http_status, :message)'
    );
    $stmt->execute([
        'action' => $action,
        'account_email' => $accountEmail !== '' ? $accountEmail : null,
        'reseller_email' => $sellerEmail !== '' ? $sellerEmail : null,
        'success' => $success ? 1 : 0,
        'http_status' => $httpStatus,
        'message' => $message,
    ]);
}

function getRecentBotCodigosApiLog(PDO $pdo, int $limit = 20): array
{
    ensureBotCodigosApiLogTable($pdo);

    $limit = max(1, min($limit, 100));
    $stmt = $pdo->query(
        "SELECT action, account_email, reseller_email, success, http_status, message, created_at
         FROM bot_codigos_api_log ORDER BY id DESC LIMIT {$limit}"
    );

    return $stmt !== false ? $stmt->fetchAll() : [];
}

function ensureBotCodigosProfileCountsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS bot_codigos_profile_counts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            cuenta_servicio_id BIGINT UNSIGNED NOT NULL,
            cantidad INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_bot_codigos_profile_counts (usuario_id, cuenta_servicio_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function incrementBotCodigosProfileCount(PDO $pdo, int $userId, int $accountId): int
{
    ensureBotCodigosProfileCountsTable($pdo);

    $pdo->prepare(
        'INSERT INTO bot_codigos_profile_counts (usuario_id, cuenta_servicio_id, cantidad)
         VALUES (:usuario_id, :cuenta_servicio_id, 1)
         ON DUPLICATE KEY UPDATE cantidad = cantidad + 1'
    )->execute(['usuario_id' => $userId, 'cuenta_servicio_id' => $accountId]);

    $stmt = $pdo->prepare('SELECT cantidad FROM bot_codigos_profile_counts WHERE usuario_id = :usuario_id AND cuenta_servicio_id = :cuenta_servicio_id');
    $stmt->execute(['usuario_id' => $userId, 'cuenta_servicio_id' => $accountId]);

    return (int) $stmt->fetchColumn();
}

function decrementBotCodigosProfileCount(PDO $pdo, int $userId, int $accountId): ?int
{
    ensureBotCodigosProfileCountsTable($pdo);

    $stmt = $pdo->prepare('SELECT cantidad FROM bot_codigos_profile_counts WHERE usuario_id = :usuario_id AND cuenta_servicio_id = :cuenta_servicio_id');
    $stmt->execute(['usuario_id' => $userId, 'cuenta_servicio_id' => $accountId]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        return null;
    }

    $remaining = max(0, (int) $current - 1);

    if ($remaining > 0) {
        $pdo->prepare('UPDATE bot_codigos_profile_counts SET cantidad = :cantidad WHERE usuario_id = :usuario_id AND cuenta_servicio_id = :cuenta_servicio_id')
            ->execute(['cantidad' => $remaining, 'usuario_id' => $userId, 'cuenta_servicio_id' => $accountId]);
    } else {
        $pdo->prepare('DELETE FROM bot_codigos_profile_counts WHERE usuario_id = :usuario_id AND cuenta_servicio_id = :cuenta_servicio_id')
            ->execute(['usuario_id' => $userId, 'cuenta_servicio_id' => $accountId]);
    }

    return $remaining;
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
            $profileCount = incrementBotCodigosProfileCount($pdo, (int) $seller['id'], (int) $account['id']);
            insertUserAccountAssignment($pdo, (int) $seller['id'], (int) $account['id']);

            return [
                'success' => true,
                'http_status' => 200,
                'message' => $profileCount > 1
                    ? "Cuenta asignada correctamente (perfil {$profileCount} de esa cuenta para este vendedor)."
                    : 'Cuenta asignada correctamente.',
            ];
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

    $remaining = decrementBotCodigosProfileCount($pdo, (int) $seller['id'], (int) $account['id']);

    if ($remaining !== null && $remaining > 0) {
        return [
            'success' => true,
            'http_status' => 200,
            'message' => "Perfil desasignado, pero el vendedor aún tiene {$remaining} perfil(es) más en esa cuenta; no se le quitó el acceso.",
        ];
    }

    $result = deleteUserAccountAssignmentsByAccountIdsAndUserIds($pdo, [(int) $account['id']], [(int) $seller['id']]);
    $result['http_status'] = $result['success'] ? 200 : 404;

    return $result;
}
