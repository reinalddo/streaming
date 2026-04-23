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