<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/mail.php';

function ensurePasswordResetTable(?PDO $pdo = null): void
{
    $pdo ??= getPdo();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            selector CHAR(18) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            token_plain CHAR(64) NULL,
            expires_at DATETIME NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_password_reset_selector (selector),
            KEY idx_password_reset_user (user_id),
            KEY idx_password_reset_expires (expires_at),
            CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    ensurePasswordResetTableColumns($pdo);
    cleanupExpiredPasswordResetTokens($pdo);
}

function cleanupExpiredPasswordResetTokens(?PDO $pdo = null): void
{
    return;
}

function ensurePasswordResetTableColumns(PDO $pdo): void
{
    ensurePasswordResetTableColumn(
        $pdo,
        'token_plain',
        'ALTER TABLE password_reset_tokens ADD COLUMN token_plain CHAR(64) NULL AFTER token_hash'
    );
}

function ensurePasswordResetTableColumn(PDO $pdo, string $columnName, string $alterSql): void
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM password_reset_tokens LIKE :column_name');
    $stmt->execute(['column_name' => $columnName]);

    if ($stmt->fetch() !== false) {
        return;
    }

    $pdo->exec($alterSql);
}

function requestPasswordReset(array $input, array $server = []): array
{
    $pdo = getPdo();
    ensurePasswordResetTable($pdo);

    $user = resolvePasswordResetUser($input, $pdo);
    $resetLinkData = getOrCreatePersistentPasswordResetLink($user, $pdo);
    $selector = $resetLinkData['selector'];
    $token = $resetLinkData['token'];

    $resetUrl = buildPasswordResetUrl($selector, $token, $server);

    try {
        sendPasswordResetLinkEmail($user, $resetUrl, $server);
    } catch (Throwable $exception) {
        if (isLocalPasswordResetFallbackEnabled($server)) {
            return [
                'success' => true,
                'message' => 'La cuenta sí está asociada, pero este servidor local no pudo enviar el correo. Usa el enlace de restablecimiento para continuar la prueba.',
                'email' => (string) $user['email'],
                'mail_delivery' => false,
                'reset_url' => $resetUrl,
            ];
        }

        throw $exception;
    }

    return [
        'success' => true,
        'message' => 'Se envió el correo de recuperación correctamente. Revisa tu bandeja de entrada.',
        'email' => (string) $user['email'],
        'mail_delivery' => true,
    ];
}

function getPasswordResetViewState(string $selector, string $token): array
{
    $record = findValidPasswordResetRecord($selector, $token);

    if ($record === null) {
        return [
            'valid' => false,
            'message' => 'El enlace de recuperación no es válido.',
            'user' => null,
        ];
    }

    return [
        'valid' => true,
        'message' => '',
        'user' => [
            'username' => (string) $record['username'],
            'email' => (string) $record['email'],
            'nombre' => (string) $record['nombre'],
            'apellido' => (string) $record['apellido'],
        ],
    ];
}

function completePasswordReset(array $input, array $server = []): array
{
    $selector = trim((string) ($input['selector'] ?? ''));
    $token = trim((string) ($input['token'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');

    if ($password === '' || $passwordConfirmation === '') {
        return ['success' => false, 'message' => 'Debes completar y confirmar la nueva clave.'];
    }

    if ($password !== $passwordConfirmation) {
        return ['success' => false, 'message' => 'La confirmación no coincide con la nueva clave.'];
    }

    if (mb_strlen($password) < 6) {
        return ['success' => false, 'message' => 'La nueva clave debe tener al menos 6 caracteres.'];
    }

    $pdo = getPdo();
    ensurePasswordResetTable($pdo);
    $record = findValidPasswordResetRecord($selector, $token, $pdo);

    if ($record === null) {
        return ['success' => false, 'message' => 'El enlace de recuperación no es válido.'];
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare('UPDATE usuarios SET password_hash = :password_hash WHERE id = :id LIMIT 1')->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $record['user_id'],
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    $userData = [
        'username' => (string) $record['username'],
        'email' => (string) $record['email'],
        'nombre' => (string) $record['nombre'],
        'apellido' => (string) $record['apellido'],
    ];

    try {
        sendPasswordResetConfirmationEmail($userData, $password, $server);
    } catch (Throwable $exception) {
        $result = [
            'success' => true,
            'message' => 'La clave fue actualizada correctamente, pero no fue posible enviar el correo con los datos de acceso.',
            'user' => [
                'username' => (string) $record['username'],
                'email' => (string) $record['email'],
            ],
            'mail_delivery' => false,
        ];

        if (isLocalPasswordResetFallbackEnabled($server)) {
            $result['message'] = 'La clave fue actualizada correctamente. Como el servidor local no pudo enviar el correo, usa estos datos para entrar.';
            $result['login_username'] = $userData['username'];
            $result['new_password'] = $password;
        }

        return $result;
    }

    return [
        'success' => true,
        'message' => 'La clave fue actualizada correctamente. También enviamos un correo con el usuario y la nueva clave.',
        'user' => [
            'username' => (string) $record['username'],
            'email' => (string) $record['email'],
        ],
        'mail_delivery' => true,
    ];
}

function resolvePasswordResetUser(array $input, PDO $pdo): array
{
    $login = trim((string) ($input['login'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));

    if ($login !== '' && filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, role, activo FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => strtolower($login)]);
        $user = $stmt->fetch();

        if ($user === false || (int) ($user['activo'] ?? 0) !== 1) {
            throw new RuntimeException('No existe una cuenta activa registrada con ese correo.');
        }

        return $user;
    }

    if ($login !== '') {
        if ($email === '') {
            throw new RuntimeException('Debes indicar el correo asociado a ese usuario para recuperar la clave.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El correo asociado no es válido.');
        }

        $stmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, role, activo FROM usuarios WHERE username = :username AND email = :email LIMIT 1");
        $stmt->execute([
            'username' => $login,
            'email' => $email,
        ]);
        $user = $stmt->fetch();

        if ($user === false || (int) ($user['activo'] ?? 0) !== 1) {
            throw new RuntimeException('El correo indicado no existe o no pertenece a ese usuario.');
        }

        return $user;
    }

    if ($email === '') {
        throw new RuntimeException('Debes indicar un correo para enviar el enlace de recuperación.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo indicado no es válido.');
    }

    $stmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, role, activo FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user === false || (int) ($user['activo'] ?? 0) !== 1) {
        throw new RuntimeException('No existe una cuenta activa registrada con ese correo.');
    }

    return $user;
}

function findValidPasswordResetRecord(string $selector, string $token, ?PDO $pdo = null): ?array
{
    if (!isValidPasswordResetSelector($selector) || !isValidPasswordResetToken($token)) {
        return null;
    }

    $pdo ??= getPdo();
    ensurePasswordResetTable($pdo);

    $stmt = $pdo->prepare(
        "SELECT prt.id AS token_id, prt.user_id, prt.email, prt.token_hash, prt.token_plain, prt.expires_at, prt.used_at, u.username, u.nombre, u.apellido, u.activo
         FROM password_reset_tokens prt
         INNER JOIN usuarios u ON u.id = prt.user_id
         WHERE prt.selector = :selector
         LIMIT 1"
    );
    $stmt->execute(['selector' => $selector]);
    $record = $stmt->fetch();

    if ($record === false) {
        return null;
    }

    if ((int) ($record['activo'] ?? 0) !== 1) {
        return null;
    }

    if (!hash_equals((string) $record['token_hash'], hash('sha256', $token))) {
        return null;
    }

    return $record;
}

function buildPasswordResetUrl(string $selector, string $token, array $server = []): string
{
    $baseUrl = rtrim(getApplicationBaseUrl($server), '/');
    return $baseUrl . '/restablecer-clave.php?' . http_build_query([
        'selector' => $selector,
        'token' => $token,
    ]);
}

function getApplicationBaseUrl(array $server = []): string
{
    $serverData = $server !== [] ? $server : $_SERVER;
    $isHttps = !empty($serverData['HTTPS']) && strtolower((string) $serverData['HTTPS']) !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($serverData['HTTP_HOST'] ?? $serverData['SERVER_NAME'] ?? 'localhost');
    $scriptName = str_replace('\\', '/', (string) ($serverData['SCRIPT_NAME'] ?? ''));
    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    $basePath = preg_replace('#/api(?:/.*)?$#', '', $scriptDir) ?? $scriptDir;
    $basePath = rtrim($basePath, '/');

    return sprintf('%s://%s%s', $scheme, $host, $basePath);
}

function sendPasswordResetLinkEmail(array $user, string $resetUrl, array $server = []): void
{
    $configuration = fetchStoredAdminConfiguration();
    $pageName = (string) ($configuration['nombre_pagina'] ?? 'Acceso');
    $subject = $pageName . ' | Recuperación de contraseña';
    $fullName = trim(((string) ($user['nombre'] ?? '')) . ' ' . ((string) ($user['apellido'] ?? '')));
    $recipientName = $fullName !== '' ? $fullName : (string) ($user['username'] ?? 'usuario');

    $body = '
        <html lang="es">
        <body style="font-family:Arial,Helvetica,sans-serif;background:#f5f8fc;color:#1f2937;padding:24px;">
            <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #dbe5f2;border-radius:18px;padding:28px;">
                <h1 style="margin-top:0;font-size:24px;">Recuperación de contraseña</h1>
                <p>Hola ' . escapePasswordResetHtml($recipientName) . ',</p>
                <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>' . escapePasswordResetHtml($pageName) . '</strong>.</p>
                <p>Haz clic en el siguiente botón para crear una nueva clave. Puedes volver a usar este mismo enlace cuando lo necesites.</p>
                <p style="margin:24px 0;">
                    <a href="' . escapePasswordResetHtml($resetUrl) . '" style="display:inline-block;background:#0b57d0;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:12px;font-weight:700;">Restablecer contraseña</a>
                </p>
                <p>Si el botón no abre, copia y pega este enlace en tu navegador:</p>
                <p><a href="' . escapePasswordResetHtml($resetUrl) . '">' . escapePasswordResetHtml($resetUrl) . '</a></p>
                <p style="margin-bottom:0;color:#64748b;">Si no solicitaste este cambio, puedes ignorar este correo.</p>
            </div>
        </body>
        </html>';

    sendApplicationEmail((string) $user['email'], $subject, $body, $server);
}

function sendPasswordResetConfirmationEmail(array $user, string $newPassword, array $server = []): void
{
    $configuration = fetchStoredAdminConfiguration();
    $pageName = (string) ($configuration['nombre_pagina'] ?? 'Acceso');
    $subject = $pageName . ' | Confirmación de nueva contraseña';
    $fullName = trim(((string) ($user['nombre'] ?? '')) . ' ' . ((string) ($user['apellido'] ?? '')));
    $recipientName = $fullName !== '' ? $fullName : (string) ($user['username'] ?? 'usuario');

    $body = '
        <html lang="es">
        <body style="font-family:Arial,Helvetica,sans-serif;background:#f5f8fc;color:#1f2937;padding:24px;">
            <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #dbe5f2;border-radius:18px;padding:28px;">
                <h1 style="margin-top:0;font-size:24px;">Tu contraseña fue actualizada</h1>
                <p>Hola ' . escapePasswordResetHtml($recipientName) . ',</p>
                <p>Tu acceso a <strong>' . escapePasswordResetHtml($pageName) . '</strong> fue restablecido correctamente.</p>
                <div style="background:#f8fbff;border:1px solid #dbe5f2;border-radius:14px;padding:18px;margin:24px 0;">
                    <div style="margin-bottom:8px;"><strong>Usuario:</strong> ' . escapePasswordResetHtml((string) ($user['username'] ?? '')) . '</div>
                    <div><strong>Nueva clave:</strong> ' . escapePasswordResetHtml($newPassword) . '</div>
                </div>
                <p>Guarda estos datos en un lugar seguro.</p>
            </div>
        </body>
        </html>';

    sendApplicationEmail((string) $user['email'], $subject, $body, $server);
}

function sendApplicationEmail(string $to, string $subject, string $htmlBody, array $server = []): void
{
    if (!function_exists('mail')) {
        throw new RuntimeException('La función mail() no está disponible en este servidor.');
    }

    $mailConfiguration = fetchStoredMailConfiguration();
    $fromEmail = filter_var((string) ($mailConfiguration['imap_user'] ?? ''), FILTER_VALIDATE_EMAIL)
        ? (string) $mailConfiguration['imap_user']
        : buildDefaultNoReplyEmail($server);
    $fromName = (string) (fetchStoredAdminConfiguration()['nombre_pagina'] ?? 'Prycorreos');
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        sprintf('From: %s <%s>', formatMailHeaderText($fromName), $fromEmail),
        sprintf('Reply-To: %s', $fromEmail),
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $sent = @mail($to, encodeMailSubject($subject), $htmlBody, implode("\r\n", $headers));

    if ($sent !== true) {
        throw new RuntimeException('No fue posible enviar el correo de recuperación en este momento.');
    }
}

function buildDefaultNoReplyEmail(array $server = []): string
{
    $serverData = $server !== [] ? $server : $_SERVER;
    $host = strtolower((string) ($serverData['HTTP_HOST'] ?? $serverData['SERVER_NAME'] ?? 'localhost'));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host)) {
        return 'no-reply@example.com';
    }

    return 'no-reply@' . $host;
}

function isLocalPasswordResetFallbackEnabled(array $server = []): bool
{
    $serverData = $server !== [] ? $server : $_SERVER;
    $host = strtolower((string) ($serverData['HTTP_HOST'] ?? $serverData['SERVER_NAME'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    return in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.local');
}

function getOrCreatePersistentPasswordResetLink(array $user, PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT id, selector, token_plain FROM password_reset_tokens WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
    $stmt->execute(['user_id' => (int) $user['id']]);
    $record = $stmt->fetch();

    if ($record !== false && isValidPasswordResetSelector((string) ($record['selector'] ?? '')) && isValidPasswordResetToken((string) ($record['token_plain'] ?? ''))) {
        $pdo->prepare('UPDATE password_reset_tokens SET email = :email, used_at = NULL, expires_at = NULL WHERE id = :id LIMIT 1')->execute([
            'email' => (string) $user['email'],
            'id' => (int) $record['id'],
        ]);

        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id AND id <> :id')->execute([
            'user_id' => (int) $user['id'],
            'id' => (int) $record['id'],
        ]);

        return [
            'selector' => (string) $record['selector'],
            'token' => (string) $record['token_plain'],
        ];
    }

    $selector = bin2hex(random_bytes(9));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    if ($record !== false) {
        $pdo->prepare('UPDATE password_reset_tokens SET email = :email, selector = :selector, token_hash = :token_hash, token_plain = :token_plain, used_at = NULL, expires_at = NULL WHERE id = :id LIMIT 1')->execute([
            'email' => (string) $user['email'],
            'selector' => $selector,
            'token_hash' => $tokenHash,
            'token_plain' => $token,
            'id' => (int) $record['id'],
        ]);

        $tokenId = (int) $record['id'];
    } else {
        $insertStmt = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, email, selector, token_hash, token_plain, expires_at, used_at) VALUES (:user_id, :email, :selector, :token_hash, :token_plain, NULL, NULL)');
        $insertStmt->execute([
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'selector' => $selector,
            'token_hash' => $tokenHash,
            'token_plain' => $token,
        ]);

        $tokenId = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id AND id <> :id')->execute([
        'user_id' => (int) $user['id'],
        'id' => $tokenId,
    ]);

    return [
        'selector' => $selector,
        'token' => $token,
    ];
}

function isValidPasswordResetSelector(string $selector): bool
{
    return preg_match('/^[a-f0-9]{18}$/', $selector) === 1;
}

function isValidPasswordResetToken(string $token): bool
{
    return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
}

function encodeMailSubject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function formatMailHeaderText(string $value): string
{
    $sanitized = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? $value);

    return $sanitized !== '' ? $sanitized : 'Prycorreos';
}

function escapePasswordResetHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}