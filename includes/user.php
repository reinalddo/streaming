<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/user_profile.php';
require_once __DIR__ . '/admin.php';

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
    ensureUsersResellerColumn($pdo);
    ensureResellerSellerAssignmentsTable($pdo);

    $userStmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, telefono, nombre_tienda, facebook, instagram, tiktok, whatsapp, telegram, foto_perfil_url, activo, revendedor FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
    $userStmt->execute(['id' => $authenticatedUser['id']]);
    $user = $userStmt->fetch();

    if ($user === false) {
        throw new RuntimeException('No fue posible cargar tu perfil.');
    }

    $assignments = fetchUserAssignments($pdo, (int) $user['id']);
    $resellerScope = fetchResellerModuleScope($pdo, (int) $user['id']);

    return [
        'success' => true,
        'user' => normalizeUserProfile($user),
        'assignments' => $assignments,
        'is_reseller' => $resellerScope['enabled'],
        'reseller_services' => $resellerScope['services'],
        'reseller_users' => $resellerScope['users'],
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
    $userStmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, telefono, nombre_tienda, facebook, instagram, tiktok, whatsapp, telegram, foto_perfil_url, activo, revendedor FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
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

    $currentUserStmt = $pdo->prepare("SELECT foto_perfil_url, revendedor FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
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
        'revendedor' => (int) ($currentUser['revendedor'] ?? 0),
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
        'SELECT ucs.id, cs.id AS cuenta_servicio_id, cs.servicio_id, cs.correo_acceso, cs.password_acceso, cs.descripcion, s.nombre AS servicio_nombre, s.descripcion AS servicio_descripcion, s.logo_url, s.color_destacado
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
            'account_id' => (int) $assignment['cuenta_servicio_id'],
            'service_id' => (int) $assignment['servicio_id'],
            'service_name' => (string) $assignment['servicio_nombre'],
            'service_description' => $assignment['servicio_descripcion'] !== null ? (string) $assignment['servicio_descripcion'] : '',
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
        'revendedor' => isset($user['revendedor']) ? (int) $user['revendedor'] : 0,
        'activo' => (int) $user['activo'],
    ];
}

function fetchResellerModuleScope(PDO $pdo, int $resellerUserId): array
{
    ensureUsersResellerColumn($pdo);
    ensureResellerSellerAssignmentsTable($pdo);

    $resellerStmt = $pdo->prepare("SELECT id, revendedor FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
    $resellerStmt->execute(['id' => $resellerUserId]);
    $resellerUser = $resellerStmt->fetch();

    if ($resellerUser === false || (int) ($resellerUser['revendedor'] ?? 0) !== 1) {
        return [
            'enabled' => false,
            'seller_user_ids' => [],
            'account_ids' => [],
            'services' => [],
            'users' => [],
        ];
    }

    $resellerAssignments = fetchUserAssignments($pdo, $resellerUserId);
    $sellerIdsStmt = $pdo->prepare('SELECT vendedor_usuario_id FROM usuario_revendedor_vendedores WHERE revendedor_usuario_id = :revendedor_usuario_id ORDER BY vendedor_usuario_id ASC');
    $sellerIdsStmt->execute(['revendedor_usuario_id' => $resellerUserId]);
    $sellerUserIds = array_values(array_map(static fn(array $row): int => (int) $row['vendedor_usuario_id'], $sellerIdsStmt->fetchAll()));

    $servicesById = [];
    $accountsById = [];

    foreach ($resellerAssignments as $assignment) {
        $serviceId = (int) ($assignment['service_id'] ?? 0);
        $accountId = (int) ($assignment['account_id'] ?? 0);

        if ($serviceId <= 0 || $accountId <= 0) {
            continue;
        }

        if (!isset($servicesById[$serviceId])) {
            $servicesById[$serviceId] = [
                'id' => $serviceId,
                'nombre' => (string) ($assignment['service_name'] ?? 'Servicio'),
                'logo_url' => $assignment['logo_url'] ?? null,
                'color_destacado' => (string) ($assignment['color'] ?? '#0b57d0'),
                'descripcion' => (string) ($assignment['service_description'] ?? ''),
                'accounts' => [],
            ];
        }

        if (!isset($accountsById[$accountId])) {
            $accountsById[$accountId] = [
                'id' => $accountId,
                'servicio_id' => $serviceId,
                'correo_acceso' => (string) ($assignment['account_email'] ?? ''),
                'password_acceso' => (string) ($assignment['account_password'] ?? ''),
                'descripcion' => (string) ($assignment['description'] ?? ''),
                'assigned_users' => [],
            ];
            $servicesById[$serviceId]['accounts'][] = &$accountsById[$accountId];
        }
    }

    $visibleAccountIds = array_values(array_map(static fn(array $assignment): int => (int) $assignment['account_id'], $resellerAssignments));
    $usersById = [];

    if ($sellerUserIds !== []) {
        $sellerPlaceholders = implode(',', array_fill(0, count($sellerUserIds), '?'));
        $sellerUsersStmt = $pdo->prepare(
            "SELECT id, nombre, apellido, username, email, telefono, nombre_tienda, facebook, instagram, tiktok, whatsapp, telegram, foto_perfil_url, activo, revendedor FROM usuarios WHERE role = 'usuario' AND id IN ($sellerPlaceholders) ORDER BY nombre ASC, apellido ASC, username ASC"
        );
        $sellerUsersStmt->execute($sellerUserIds);

        foreach ($sellerUsersStmt->fetchAll() as $sellerUser) {
            $usersById[(int) $sellerUser['id']] = normalizeUserProfile($sellerUser) + ['assignments' => []];
        }
    }

    if ($sellerUserIds !== [] && $visibleAccountIds !== []) {
        $sellerPlaceholders = implode(',', array_fill(0, count($sellerUserIds), '?'));
        $accountPlaceholders = implode(',', array_fill(0, count($visibleAccountIds), '?'));
        $sellerAssignmentsStmt = $pdo->prepare(
            "SELECT ucs.id, u.id AS usuario_id, u.nombre, u.apellido, u.username, u.email, u.nombre_tienda, u.foto_perfil_url, cs.id AS cuenta_servicio_id, cs.correo_acceso, cs.password_acceso, cs.descripcion, s.id AS servicio_id, s.nombre AS servicio_nombre, s.logo_url, s.color_destacado
             FROM usuario_cuentas_servicio ucs
             INNER JOIN usuarios u ON u.id = ucs.usuario_id
             INNER JOIN cuentas_servicio cs ON cs.id = ucs.cuenta_servicio_id
             INNER JOIN servicios s ON s.id = cs.servicio_id
             WHERE ucs.usuario_id IN ($sellerPlaceholders) AND ucs.cuenta_servicio_id IN ($accountPlaceholders)
             ORDER BY s.nombre ASC, cs.correo_acceso ASC"
        );
        $sellerAssignmentsStmt->execute([...$sellerUserIds, ...$visibleAccountIds]);

        foreach ($sellerAssignmentsStmt->fetchAll() as $assignment) {
            $sellerUserId = (int) ($assignment['usuario_id'] ?? 0);
            $accountId = (int) ($assignment['cuenta_servicio_id'] ?? 0);

            if (!isset($usersById[$sellerUserId])) {
                continue;
            }

            $usersById[$sellerUserId]['assignments'][] = [
                'assignment_id' => (int) $assignment['id'],
                'account_id' => $accountId,
                'service_id' => (int) ($assignment['servicio_id'] ?? 0),
                'service_name' => (string) ($assignment['servicio_nombre'] ?? 'Servicio'),
                'account_email' => (string) ($assignment['correo_acceso'] ?? ''),
                'account_password' => (string) ($assignment['password_acceso'] ?? ''),
                'description' => $assignment['descripcion'] !== null ? (string) $assignment['descripcion'] : '',
                'logo_url' => $assignment['logo_url'] !== null ? (string) $assignment['logo_url'] : null,
                'color' => (string) ($assignment['color_destacado'] ?? '#0b57d0'),
            ];

            if (isset($accountsById[$accountId])) {
                $accountsById[$accountId]['assigned_users'][] = [
                    'assignment_id' => (int) $assignment['id'],
                    'id' => $sellerUserId,
                    'nombre' => (string) ($assignment['nombre'] ?? ''),
                    'apellido' => (string) ($assignment['apellido'] ?? ''),
                    'username' => (string) ($assignment['username'] ?? ''),
                    'email' => (string) ($assignment['email'] ?? ''),
                    'nombre_tienda' => $assignment['nombre_tienda'] !== null ? (string) $assignment['nombre_tienda'] : null,
                    'foto_perfil_url' => $assignment['foto_perfil_url'] !== null ? (string) $assignment['foto_perfil_url'] : null,
                ];
            }
        }
    }

    return [
        'enabled' => true,
        'seller_user_ids' => $sellerUserIds,
        'account_ids' => $visibleAccountIds,
        'services' => array_values($servicesById),
        'users' => array_values($usersById),
    ];
}

function assignResellerAccountToSellerUser(array $input): array
{
    $authenticatedUser = requireRegisteredUser();
    $sellerUserId = (int) ($input['usuario_id'] ?? 0);
    $accountId = (int) ($input['cuenta_servicio_id'] ?? 0);

    if ($sellerUserId <= 0 || $accountId <= 0) {
        return ['success' => false, 'message' => 'Selecciona el usuario y la cuenta a asignar.'];
    }

    $pdo = getPdo();
    $scope = fetchResellerModuleScope($pdo, (int) $authenticatedUser['id']);

    if (!$scope['enabled']) {
        return ['success' => false, 'message' => 'Solo los usuarios marcados como revendedores pueden asignar cuentas a sus vendedores.'];
    }

    if (!in_array($sellerUserId, $scope['seller_user_ids'], true)) {
        return ['success' => false, 'message' => 'Ese usuario no está asignado como vendedor para este revendedor.'];
    }

    if (!in_array($accountId, $scope['account_ids'], true)) {
        return ['success' => false, 'message' => 'La cuenta seleccionada no pertenece a las cuentas asignadas al revendedor.'];
    }

    return insertUserAccountAssignment($pdo, $sellerUserId, $accountId);
}

function unassignResellerAccountFromSellerUser(array $input): array
{
    $authenticatedUser = requireRegisteredUser();
    $assignmentId = (int) ($input['assignment_id'] ?? 0);

    if ($assignmentId <= 0) {
        return ['success' => false, 'message' => 'Debes indicar la asignación a eliminar.'];
    }

    $pdo = getPdo();
    $scope = fetchResellerModuleScope($pdo, (int) $authenticatedUser['id']);

    if (!$scope['enabled']) {
        return ['success' => false, 'message' => 'Solo los usuarios marcados como revendedores pueden administrar estas asignaciones.'];
    }

    $assignment = fetchUserAccountAssignmentById($pdo, $assignmentId);

    if ($assignment === null) {
        return ['success' => false, 'message' => 'La asignación indicada no existe.'];
    }

    if (!in_array((int) $assignment['usuario_id'], $scope['seller_user_ids'], true) || !in_array((int) $assignment['cuenta_servicio_id'], $scope['account_ids'], true)) {
        return ['success' => false, 'message' => 'No puedes modificar una asignación fuera del alcance de este revendedor.'];
    }

    return deleteUserAccountAssignmentById($pdo, $assignmentId);
}

function bulkUnassignResellerAccountsFromSellerUsersByService(array $input): array
{
    $authenticatedUser = requireRegisteredUser();
    $serviceId = (int) ($input['servicio_id'] ?? 0);
    $assignmentIds = array_values(array_filter(array_map('intval', (array) ($input['assignment_ids'] ?? [])), static fn(int $value): bool => $value > 0));
    $userIds = array_values(array_filter(array_map('intval', (array) ($input['usuario_ids'] ?? [])), static fn(int $value): bool => $value > 0));

    if ($serviceId <= 0 || ($assignmentIds === [] && $userIds === [])) {
        return ['success' => false, 'message' => 'Debes indicar el servicio y al menos una asignación para desasignar.'];
    }

    $pdo = getPdo();
    $scope = fetchResellerModuleScope($pdo, (int) $authenticatedUser['id']);

    if (!$scope['enabled']) {
        return ['success' => false, 'message' => 'Solo los usuarios marcados como revendedores pueden administrar estas asignaciones.'];
    }

    $allowedUserIds = $scope['seller_user_ids'];
    foreach ($userIds as $userId) {
        if (!in_array($userId, $allowedUserIds, true)) {
            return ['success' => false, 'message' => 'Uno de los usuarios seleccionados no pertenece a los vendedores asignados a este revendedor.'];
        }
    }

    $service = null;
    foreach ($scope['services'] as $candidateService) {
        if ((int) ($candidateService['id'] ?? 0) === $serviceId) {
            $service = $candidateService;
            break;
        }
    }

    if ($service === null) {
        return ['success' => false, 'message' => 'El servicio indicado no pertenece al alcance de este revendedor.'];
    }

    $accountIds = array_values(array_map(static fn(array $account): int => (int) ($account['id'] ?? 0), (array) ($service['accounts'] ?? [])));
    $accountIds = array_values(array_filter($accountIds, static fn(int $value): bool => $value > 0));

    if ($accountIds === []) {
        return ['success' => false, 'message' => 'El servicio indicado no tiene cuentas disponibles para este revendedor.'];
    }

    if ($assignmentIds !== []) {
        $assignments = fetchUserAccountAssignmentsByIds($pdo, $assignmentIds);

        if (count($assignments) !== count(array_unique($assignmentIds))) {
            return ['success' => false, 'message' => 'Una de las asignaciones seleccionadas ya no existe.'];
        }

        foreach ($assignments as $assignment) {
            if (!in_array((int) $assignment['usuario_id'], $allowedUserIds, true) || !in_array((int) $assignment['cuenta_servicio_id'], $accountIds, true)) {
                return ['success' => false, 'message' => 'Una de las asignaciones seleccionadas está fuera del alcance de este revendedor.'];
            }
        }

        $result = deleteUserAccountAssignmentsByIds($pdo, $assignmentIds);
        $result['selected_assignments_count'] = count($assignmentIds);
        $result['servicio_id'] = $serviceId;

        if ($result['success']) {
            $result['message'] = count($assignmentIds) === 1
                ? 'Se desasignó la cuenta seleccionada del servicio.'
                : 'Se desasignaron las cuentas seleccionadas del servicio.';
        }

        return $result;
    }

    $result = deleteUserAccountAssignmentsByAccountIdsAndUserIds($pdo, $accountIds, $userIds);
    $result['selected_users_count'] = count($userIds);
    $result['servicio_id'] = $serviceId;

    if ($result['success']) {
        $result['message'] = count($userIds) === 1
            ? 'Se desasignó el usuario seleccionado del servicio.'
            : 'Se desasignaron los usuarios seleccionados del servicio.';
    }

    return $result;
}