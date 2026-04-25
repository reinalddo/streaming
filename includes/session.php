<?php

declare(strict_types=1);

function ensureSessionStarted(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function setAuthenticatedUser(array $user): void
{
    ensureSessionStarted();

    $_SESSION['auth_user'] = [
        'id' => (int) $user['id'],
        'nombre' => (string) $user['nombre'],
        'apellido' => (string) $user['apellido'],
        'username' => (string) $user['username'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
        'nombre_tienda' => isset($user['nombre_tienda']) && $user['nombre_tienda'] !== null ? (string) $user['nombre_tienda'] : null,
        'facebook' => isset($user['facebook']) && $user['facebook'] !== null ? (string) $user['facebook'] : null,
        'instagram' => isset($user['instagram']) && $user['instagram'] !== null ? (string) $user['instagram'] : null,
        'tiktok' => isset($user['tiktok']) && $user['tiktok'] !== null ? (string) $user['tiktok'] : null,
        'whatsapp' => isset($user['whatsapp']) && $user['whatsapp'] !== null ? (string) $user['whatsapp'] : null,
        'telegram' => isset($user['telegram']) && $user['telegram'] !== null ? (string) $user['telegram'] : null,
        'foto_perfil_url' => isset($user['foto_perfil_url']) && $user['foto_perfil_url'] !== null ? (string) $user['foto_perfil_url'] : null,
    ];
}

function getAuthenticatedUser(): ?array
{
    ensureSessionStarted();

    $user = $_SESSION['auth_user'] ?? null;

    return is_array($user) ? $user : null;
}

function clearAuthenticatedUser(): void
{
    ensureSessionStarted();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function requireAdminUser(): array
{
    $user = getAuthenticatedUser();

    if ($user === null || ($user['role'] ?? '') !== 'admin') {
        throw new RuntimeException('No autorizado.');
    }

    return $user;
}