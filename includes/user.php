<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/mail.php';

function requireRegisteredUser(): array
{
    $user = getAuthenticatedUser();

    if ($user === null || ($user['role'] ?? '') !== 'usuario') {
        throw new RuntimeException('No autorizado.');
    }

    return $user;
}

function getCurrentUserModuleOverview(): array
{
    $authenticatedUser = requireRegisteredUser();
    $pdo = getPdo();

    $userStmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, telefono, activo FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
    $userStmt->execute(['id' => $authenticatedUser['id']]);
    $user = $userStmt->fetch();

    if ($user === false) {
        throw new RuntimeException('No fue posible cargar tu perfil.');
    }

    $assignments = fetchUserAssignments($pdo, (int) $user['id']);

    return [
        'success' => true,
        'user' => normalizeUserProfile($user),
        'assignments' => $assignments,
    ];
}

function searchUserInformationByEmail(array $input): array
{
    $authenticatedUser = requireRegisteredUser();

    $email = strtolower(trim((string) ($input['email'] ?? '')));

    if ($email === '') {
        return ['success' => false, 'message' => 'Debes indicar un correo para buscar.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Debes escribir un correo válido.'];
    }

    $pdo = getPdo();
    $userStmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, telefono, activo FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
    $userStmt->execute(['id' => $authenticatedUser['id']]);
    $user = $userStmt->fetch();

    if ($user === false) {
        throw new RuntimeException('No fue posible cargar tu información.');
    }

    $assignments = fetchUserAssignments($pdo, (int) $user['id']);
    $matchedAssignments = array_values(array_filter($assignments, static fn(array $assignment): bool => strtolower((string) $assignment['account_email']) === $email));

    if ($matchedAssignments === []) {
        return ['success' => true, 'found' => false, 'message' => 'Ese correo no está asignado a tu usuario.'];
    }

    $mailConfiguration = fetchStoredMailConfiguration();

    return [
        'success' => true,
        'found' => true,
        'message' => 'Información encontrada correctamente.',
        'user' => normalizeUserProfile($user),
        'selected_account_email' => $email,
        'assignments' => $matchedAssignments,
        'messages' => fetchRecentMailboxMessagesForAssignedAccount($email),
        'delay_days' => $mailConfiguration['delay_days'],
        'delay_minutes' => $mailConfiguration['delay_minutes'],
    ];
}

function updateCurrentUserProfile(array $input): array
{
    $authenticatedUser = requireRegisteredUser();
    $name = trim((string) ($input['nombre'] ?? ''));
    $lastName = trim((string) ($input['apellido'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $phone = trim((string) ($input['telefono'] ?? ''));

    if ($name === '' || $lastName === '' || $username === '' || $email === '') {
        return ['success' => false, 'message' => 'Completa los datos obligatorios.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Debes escribir un correo válido.'];
    }

    $pdo = getPdo();
    $dupStmt = $pdo->prepare('SELECT id FROM usuarios WHERE (email = :email OR username = :username) AND id <> :id LIMIT 1');
    $dupStmt->execute([
        'email' => $email,
        'username' => $username,
        'id' => $authenticatedUser['id'],
    ]);

    if ($dupStmt->fetch() !== false) {
        return ['success' => false, 'message' => 'Ese correo o usuario ya pertenece a otra cuenta.'];
    }

    $updateStmt = $pdo->prepare("UPDATE usuarios SET nombre = :nombre, apellido = :apellido, username = :username, email = :email, telefono = :telefono WHERE id = :id AND role = 'usuario'");
    $updateStmt->execute([
        'nombre' => $name,
        'apellido' => $lastName,
        'username' => $username,
        'email' => $email,
        'telefono' => $phone !== '' ? $phone : null,
        'id' => $authenticatedUser['id'],
    ]);

    $updatedUser = [
        'id' => (int) $authenticatedUser['id'],
        'nombre' => $name,
        'apellido' => $lastName,
        'username' => $username,
        'email' => $email,
        'role' => 'usuario',
    ];

    setAuthenticatedUser($updatedUser);

    return [
        'success' => true,
        'message' => 'Tus datos fueron actualizados correctamente.',
        'user' => $updatedUser + ['telefono' => $phone !== '' ? $phone : null, 'activo' => 1],
        'assignments' => fetchUserAssignments($pdo, (int) $authenticatedUser['id']),
    ];
}

function fetchUserAssignments(PDO $pdo, int $userId): array
{
    $assignmentsStmt = $pdo->prepare(
        'SELECT ucs.id, cs.correo_acceso, cs.password_acceso, cs.descripcion, s.nombre AS servicio_nombre, s.logo_url, s.color_destacado
         FROM usuario_cuentas_servicio ucs
         INNER JOIN cuentas_servicio cs ON cs.id = ucs.cuenta_servicio_id
         INNER JOIN servicios s ON s.id = cs.servicio_id
         WHERE ucs.usuario_id = :usuario_id
         ORDER BY s.nombre ASC, cs.correo_acceso ASC'
    );
    $assignmentsStmt->execute(['usuario_id' => $userId]);

    return array_map(static function (array $assignment): array {
        return [
            'assignment_id' => (int) $assignment['id'],
            'service_name' => (string) $assignment['servicio_nombre'],
            'account_email' => (string) $assignment['correo_acceso'],
            'account_password' => (string) $assignment['password_acceso'],
            'description' => $assignment['descripcion'] !== null ? (string) $assignment['descripcion'] : '',
            'logo_url' => $assignment['logo_url'] !== null ? (string) $assignment['logo_url'] : null,
            'color' => (string) $assignment['color_destacado'],
        ];
    }, $assignmentsStmt->fetchAll());
}

function normalizeUserProfile(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'nombre' => (string) $user['nombre'],
        'apellido' => (string) $user['apellido'],
        'username' => (string) $user['username'],
        'email' => (string) $user['email'],
        'telefono' => $user['telefono'] !== null ? (string) $user['telefono'] : null,
        'activo' => (int) $user['activo'],
    ];
}