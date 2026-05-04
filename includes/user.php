<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/user_profile.php';

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
    ensureUserProfileColumns($pdo);

    $userStmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, telefono, nombre_tienda, facebook, instagram, tiktok, whatsapp, telegram, foto_perfil_url, activo FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
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

    $searchTerm = trim((string) ($input['email'] ?? ''));
    $page = max(1, (int) ($input['page'] ?? 1));

    if ($searchTerm === '') {
        return ['success' => false, 'message' => 'Debes indicar un correo o texto para buscar.'];
    }

    if (mb_strlen($searchTerm) < 3) {
        return ['success' => false, 'message' => 'Debes escribir al menos 3 caracteres para buscar.'];
    }

    $pdo = getPdo();
    ensureUserProfileColumns($pdo);
    $userStmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, telefono, nombre_tienda, facebook, instagram, tiktok, whatsapp, telegram, foto_perfil_url, activo FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
    $userStmt->execute(['id' => $authenticatedUser['id']]);
    $user = $userStmt->fetch();

    if ($user === false) {
        throw new RuntimeException('No fue posible cargar tu información.');
    }

    $assignments = fetchUserAssignments($pdo, (int) $user['id']);
    $matchedAssignments = array_values(array_filter($assignments, static fn(array $assignment): bool => strtolower((string) $assignment['account_email']) === strtolower($searchTerm)));

    $mailConfiguration = fetchStoredMailConfiguration();
    $mailSearchResult = fetchRecentMailboxMessagesForAssignedAccount($searchTerm, $page);

    return [
        'success' => true,
        'found' => true,
        'message' => 'Información encontrada correctamente.',
        'user' => normalizeUserProfile($user),
        'selected_account_email' => $searchTerm,
        'assignments' => $matchedAssignments,
        'messages' => $mailSearchResult['messages'],
        'pagination' => $mailSearchResult['pagination'],
        'mail_search_notice' => $mailSearchResult['search_notice'] ?? null,
        'delay_days' => $mailConfiguration['delay_days'],
        'delay_minutes' => $mailConfiguration['delay_minutes'],
    ];
}

function fetchUserMailboxMessage(array $input): array
{
    $authenticatedUser = requireRegisteredUser();
    $searchTerm = trim((string) ($input['email'] ?? ''));
    $messageUid = (int) ($input['uid'] ?? 0);

    if ($searchTerm === '' || mb_strlen($searchTerm) < 3) {
        return ['success' => false, 'message' => 'Debes indicar un criterio válido para consultar el mensaje.'];
    }

    if ($messageUid <= 0) {
        return ['success' => false, 'message' => 'No fue posible identificar el correo solicitado.'];
    }

    $pdo = getPdo();

    return [
        'success' => true,
        'message' => 'Contenido del correo cargado correctamente.',
        'email' => $searchTerm,
        'message_data' => fetchMailboxMessageBodyForAssignedAccount($searchTerm, $messageUid),
    ];
}

function updateCurrentUserProfile(array $input, array $files = []): array
{
    $authenticatedUser = requireRegisteredUser();
    $name = trim((string) ($input['nombre'] ?? ''));
    $lastName = trim((string) ($input['apellido'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $phone = trim((string) ($input['telefono'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $extraProfileData = normalizeUserExtraProfileInput($input);

    if ($name === '' || $lastName === '' || $username === '' || $email === '' || $phone === '') {
        return ['success' => false, 'message' => 'Completa los datos obligatorios.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Debes escribir un correo válido.'];
    }

    if ($password !== '' && mb_strlen($password) < 6) {
        return ['success' => false, 'message' => 'La nueva clave debe tener al menos 6 caracteres.'];
    }

    $pdo = getPdo();
    ensureUserProfileColumns($pdo);
    $dupStmt = $pdo->prepare('SELECT id FROM usuarios WHERE (email = :email OR username = :username) AND id <> :id LIMIT 1');
    $dupStmt->execute([
        'email' => $email,
        'username' => $username,
        'id' => $authenticatedUser['id'],
    ]);

    if ($dupStmt->fetch() !== false) {
        return ['success' => false, 'message' => 'Ese correo o usuario ya pertenece a otra cuenta.'];
    }

    $currentUserStmt = $pdo->prepare("SELECT foto_perfil_url FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
    $currentUserStmt->execute(['id' => $authenticatedUser['id']]);
    $currentUser = $currentUserStmt->fetch();
    $newProfilePhotoPath = uploadUserProfilePhoto($files['foto_perfil'] ?? null);
    $finalPhotoPath = $newProfilePhotoPath ?? (($currentUser['foto_perfil_url'] ?? null) !== null ? (string) $currentUser['foto_perfil_url'] : null);

    if ($password !== '') {
        $updateStmt = $pdo->prepare("UPDATE usuarios SET nombre = :nombre, apellido = :apellido, username = :username, email = :email, telefono = :telefono, nombre_tienda = :nombre_tienda, facebook = :facebook, instagram = :instagram, tiktok = :tiktok, whatsapp = :whatsapp, telegram = :telegram, foto_perfil_url = :foto_perfil_url, password_hash = :password_hash WHERE id = :id AND role = 'usuario'");
    } else {
        $updateStmt = $pdo->prepare("UPDATE usuarios SET nombre = :nombre, apellido = :apellido, username = :username, email = :email, telefono = :telefono, nombre_tienda = :nombre_tienda, facebook = :facebook, instagram = :instagram, tiktok = :tiktok, whatsapp = :whatsapp, telegram = :telegram, foto_perfil_url = :foto_perfil_url WHERE id = :id AND role = 'usuario'");
    }

    $updateParams = [
        'nombre' => $name,
        'apellido' => $lastName,
        'username' => $username,
        'email' => $email,
        'telefono' => $phone,
        'nombre_tienda' => $extraProfileData['nombre_tienda'],
        'facebook' => $extraProfileData['facebook'],
        'instagram' => $extraProfileData['instagram'],
        'tiktok' => $extraProfileData['tiktok'],
        'whatsapp' => $extraProfileData['whatsapp'],
        'telegram' => $extraProfileData['telegram'],
        'foto_perfil_url' => $finalPhotoPath,
        'id' => $authenticatedUser['id'],
    ];

    if ($password !== '') {
        $updateParams['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    try {
        $updateStmt->execute($updateParams);
    } catch (Throwable $exception) {
        deleteLocalUserProfileAsset($newProfilePhotoPath);
        throw $exception;
    }

    if ($newProfilePhotoPath !== null) {
        deleteLocalUserProfileAsset(($currentUser['foto_perfil_url'] ?? null) !== null ? (string) $currentUser['foto_perfil_url'] : null);
    }

    $updatedUser = [
        'id' => (int) $authenticatedUser['id'],
        'nombre' => $name,
        'apellido' => $lastName,
        'username' => $username,
        'email' => $email,
        'role' => 'usuario',
        'nombre_tienda' => $extraProfileData['nombre_tienda'],
        'facebook' => $extraProfileData['facebook'],
        'instagram' => $extraProfileData['instagram'],
        'tiktok' => $extraProfileData['tiktok'],
        'whatsapp' => $extraProfileData['whatsapp'],
        'telegram' => $extraProfileData['telegram'],
        'foto_perfil_url' => $finalPhotoPath,
    ];

    setAuthenticatedUser($updatedUser);

    return [
        'success' => true,
        'message' => 'Tus datos fueron actualizados correctamente.',
        'user' => $updatedUser + ['telefono' => $phone, 'activo' => 1],
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
    $profilePhoto = $user['foto_perfil_url'] !== null ? (string) $user['foto_perfil_url'] : null;

    if ($profilePhoto !== null && !userProfileAssetExists($profilePhoto)) {
        $profilePhoto = null;
    }

    return [
        'id' => (int) $user['id'],
        'nombre' => (string) $user['nombre'],
        'apellido' => (string) $user['apellido'],
        'username' => (string) $user['username'],
        'email' => (string) $user['email'],
        'telefono' => $user['telefono'] !== null ? (string) $user['telefono'] : null,
        'nombre_tienda' => $user['nombre_tienda'] !== null ? (string) $user['nombre_tienda'] : null,
        'facebook' => $user['facebook'] !== null ? (string) $user['facebook'] : null,
        'instagram' => $user['instagram'] !== null ? (string) $user['instagram'] : null,
        'tiktok' => $user['tiktok'] !== null ? (string) $user['tiktok'] : null,
        'whatsapp' => $user['whatsapp'] !== null ? (string) $user['whatsapp'] : null,
        'telegram' => $user['telegram'] !== null ? (string) $user['telegram'] : null,
        'foto_perfil_url' => $profilePhoto,
        'activo' => (int) $user['activo'],
    ];
}