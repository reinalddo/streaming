<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';

function registerUser(array $input): array
{
    $nombre = trim((string) ($input['nombre'] ?? ''));
    $apellido = trim((string) ($input['apellido'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $telefono = trim((string) ($input['telefono'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($nombre === '' || $apellido === '' || $username === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'Completa los campos obligatorios.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El correo no es valido.'];
    }

    if (mb_strlen($password) < 6) {
        return ['success' => false, 'message' => 'La clave debe tener al menos 6 caracteres.'];
    }

    $pdo = getPdo();
    $existsStmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email OR username = :username LIMIT 1');
    $existsStmt->execute([
        'email' => $email,
        'username' => $username,
    ]);

    if ($existsStmt->fetch() !== false) {
        return ['success' => false, 'message' => 'El correo o usuario ya se encuentra registrado.'];
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, apellido, username, email, telefono, password_hash, role, activo) VALUES (:nombre, :apellido, :username, :email, :telefono, :password_hash, :role, :activo)'
    );

    $insertStmt->execute([
        'nombre' => $nombre,
        'apellido' => $apellido,
        'username' => $username,
        'email' => $email,
        'telefono' => $telefono !== '' ? $telefono : null,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'usuario',
        'activo' => 1,
    ]);

    return ['success' => true, 'message' => 'Usuario registrado correctamente.'];
}

function loginUser(array $input): array
{
    $identifier = trim((string) ($input['login'] ?? $input['email'] ?? ''));
    $normalizedEmail = strtolower($identifier);
    $password = (string) ($input['password'] ?? '');

    if ($identifier === '' || $password === '') {
        return ['success' => false, 'exists' => false, 'message' => 'Debes indicar usuario o correo y clave.'];
    }

    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT id, nombre, apellido, username, email, password_hash, role, activo FROM usuarios WHERE username = :identifier OR email = :email LIMIT 1');
    $stmt->execute([
        'identifier' => $identifier,
        'email' => $normalizedEmail,
    ]);
    $user = $stmt->fetch();

    if ($user === false || (int) $user['activo'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
        return ['success' => false, 'exists' => false, 'message' => 'Usuario No existe'];
    }

    setAuthenticatedUser($user);

    $updateStmt = $pdo->prepare('UPDATE usuarios SET ultimo_login_at = NOW() WHERE id = :id');
    $updateStmt->execute(['id' => $user['id']]);

    return [
        'success' => true,
        'exists' => true,
        'message' => 'Usuario Existe',
        'role' => $user['role'],
        'user' => [
            'id' => (int) $user['id'],
            'nombre' => (string) $user['nombre'],
            'apellido' => (string) $user['apellido'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ],
    ];
}