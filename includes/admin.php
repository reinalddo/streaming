<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';

function getAdminOverview(): array
{
    requireAdminUser();

    $pdo = getPdo();

    $services = $pdo->query('SELECT id, nombre, slug, logo_url, color_destacado, descripcion, activo, created_at FROM servicios ORDER BY nombre ASC')->fetchAll();
    $accounts = $pdo->query('SELECT cs.id, cs.servicio_id, cs.correo_acceso, cs.password_acceso, cs.descripcion, cs.activo, s.nombre AS servicio_nombre, s.logo_url, s.color_destacado FROM cuentas_servicio cs INNER JOIN servicios s ON s.id = cs.servicio_id ORDER BY s.nombre ASC, cs.correo_acceso ASC')->fetchAll();
    $users = $pdo->query("SELECT id, nombre, apellido, username, email, telefono, activo FROM usuarios WHERE role = 'usuario' ORDER BY nombre ASC, apellido ASC, username ASC")->fetchAll();
    $assignments = $pdo->query('SELECT ucs.id, ucs.usuario_id, ucs.cuenta_servicio_id, cs.correo_acceso, cs.password_acceso, cs.descripcion, s.nombre AS servicio_nombre, s.color_destacado, s.logo_url, u.nombre, u.apellido, u.username, u.email FROM usuario_cuentas_servicio ucs INNER JOIN cuentas_servicio cs ON cs.id = ucs.cuenta_servicio_id INNER JOIN servicios s ON s.id = cs.servicio_id INNER JOIN usuarios u ON u.id = ucs.usuario_id ORDER BY s.nombre ASC, cs.correo_acceso ASC')->fetchAll();

    $servicesById = [];
    foreach ($services as $service) {
        $service['accounts'] = [];
        $servicesById[(int) $service['id']] = $service;
    }

    $accountsById = [];
    foreach ($accounts as $account) {
        $account['assigned_users'] = [];
        $accountsById[(int) $account['id']] = $account;

        $serviceId = (int) $account['servicio_id'];
        if (isset($servicesById[$serviceId])) {
            $servicesById[$serviceId]['accounts'][] = &$accountsById[(int) $account['id']];
        }
    }

    $usersById = [];
    foreach ($users as $user) {
        $user['assignments'] = [];
        $usersById[(int) $user['id']] = $user;
    }

    foreach ($assignments as $assignment) {
        $userId = (int) $assignment['usuario_id'];
        $accountId = (int) $assignment['cuenta_servicio_id'];

        if (isset($usersById[$userId])) {
            $usersById[$userId]['assignments'][] = [
                'assignment_id' => (int) $assignment['id'],
                'account_id' => $accountId,
                'service_name' => $assignment['servicio_nombre'],
                'account_email' => $assignment['correo_acceso'],
                'account_password' => $assignment['password_acceso'],
                'description' => $assignment['descripcion'],
                'color' => $assignment['color_destacado'],
                'logo_url' => $assignment['logo_url'],
            ];
        }

        if (isset($accountsById[$accountId])) {
            $accountsById[$accountId]['assigned_users'][] = [
                'assignment_id' => (int) $assignment['id'],
                'id' => $userId,
                'nombre' => $assignment['nombre'],
                'apellido' => $assignment['apellido'],
                'username' => $assignment['username'],
                'email' => $assignment['email'],
            ];
        }
    }

    $servicesOutput = [];
    foreach ($servicesById as $service) {
        $service['accounts'] = array_values(is_array($service['accounts']) ? $service['accounts'] : []);
        $servicesOutput[] = $service;
    }

    $accountsOutput = [];
    foreach ($accountsById as $account) {
        $account['assigned_users'] = array_values(is_array($account['assigned_users']) ? $account['assigned_users'] : []);
        $accountsOutput[] = $account;
    }

    $usersOutput = [];
    foreach ($usersById as $user) {
        $user['assignments'] = array_values(is_array($user['assignments']) ? $user['assignments'] : []);
        $usersOutput[] = $user;
    }

    return [
        'services' => $servicesOutput,
        'accounts' => $accountsOutput,
        'users' => $usersOutput,
    ];
}

function createService(array $input, array $files = []): array
{
    requireAdminUser();

    $serviceData = normalizeServicePayload($input);

    if ($serviceData['nombre'] === '') {
        return ['success' => false, 'message' => 'Debes indicar el nombre del servicio.'];
    }

    $pdo = getPdo();
    $baseSlug = slugify($serviceData['nombre']);
    $slug = uniqueServiceSlug($pdo, $baseSlug);
    $logoPath = uploadServiceLogo($files['logo'] ?? null);

    $stmt = $pdo->prepare('INSERT INTO servicios (nombre, slug, logo_url, color_destacado, descripcion, activo) VALUES (:nombre, :slug, :logo_url, :color_destacado, :descripcion, :activo)');

    try {
        $stmt->execute([
            'nombre' => $serviceData['nombre'],
            'slug' => $slug,
            'logo_url' => $logoPath,
            'color_destacado' => $serviceData['color_destacado'],
            'descripcion' => $serviceData['descripcion'],
            'activo' => 1,
        ]);
    } catch (PDOException $exception) {
        deleteLocalServiceAsset($logoPath);

        if ($exception->getCode() === '23000') {
            return ['success' => false, 'message' => 'Ese servicio ya existe.'];
        }

        throw $exception;
    }

    return ['success' => true, 'message' => 'Servicio creado correctamente.'];
}

function updateService(array $input, array $files = []): array
{
    requireAdminUser();

    $serviceId = (int) ($input['servicio_id'] ?? 0);
    $serviceData = normalizeServicePayload($input);

    if ($serviceId <= 0 || $serviceData['nombre'] === '') {
        return ['success' => false, 'message' => 'Completa los datos obligatorios del servicio.'];
    }

    $pdo = getPdo();
    $currentServiceStmt = $pdo->prepare('SELECT id, nombre, slug, logo_url FROM servicios WHERE id = :id LIMIT 1');
    $currentServiceStmt->execute(['id' => $serviceId]);
    $currentService = $currentServiceStmt->fetch();

    if ($currentService === false) {
        return ['success' => false, 'message' => 'El servicio a editar no existe.'];
    }

    $dupStmt = $pdo->prepare('SELECT id FROM servicios WHERE nombre = :nombre AND id <> :id LIMIT 1');
    $dupStmt->execute([
        'nombre' => $serviceData['nombre'],
        'id' => $serviceId,
    ]);

    if ($dupStmt->fetch() !== false) {
        return ['success' => false, 'message' => 'Ya existe otro servicio con ese nombre.'];
    }

    $slug = $serviceData['nombre'] === $currentService['nombre']
        ? (string) $currentService['slug']
        : uniqueServiceSlug($pdo, slugify($serviceData['nombre']));

    $newLogoPath = uploadServiceLogo($files['logo'] ?? null);
    $finalLogoPath = $newLogoPath ?? ($currentService['logo_url'] !== '' ? $currentService['logo_url'] : null);

    $stmt = $pdo->prepare('UPDATE servicios SET nombre = :nombre, slug = :slug, logo_url = :logo_url, color_destacado = :color_destacado, descripcion = :descripcion WHERE id = :id');

    try {
        $stmt->execute([
            'nombre' => $serviceData['nombre'],
            'slug' => $slug,
            'logo_url' => $finalLogoPath,
            'color_destacado' => $serviceData['color_destacado'],
            'descripcion' => $serviceData['descripcion'],
            'id' => $serviceId,
        ]);
    } catch (PDOException $exception) {
        deleteLocalServiceAsset($newLogoPath);

        if ($exception->getCode() === '23000') {
            return ['success' => false, 'message' => 'No fue posible actualizar el servicio por conflicto de datos.'];
        }

        throw $exception;
    }

    if ($newLogoPath !== null) {
        deleteLocalServiceAsset((string) $currentService['logo_url']);
    }

    return ['success' => true, 'message' => 'Servicio actualizado correctamente.'];
}

function deleteService(array $input): array
{
    requireAdminUser();

    $serviceId = (int) ($input['servicio_id'] ?? 0);

    if ($serviceId <= 0) {
        return ['success' => false, 'message' => 'Debes indicar el servicio a eliminar.'];
    }

    $pdo = getPdo();
    $serviceStmt = $pdo->prepare('SELECT id, logo_url FROM servicios WHERE id = :id LIMIT 1');
    $serviceStmt->execute(['id' => $serviceId]);
    $service = $serviceStmt->fetch();

    if ($service === false) {
        return ['success' => false, 'message' => 'El servicio indicado no existe.'];
    }

    $accountCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cuentas_servicio WHERE servicio_id = :servicio_id');
    $accountCountStmt->execute(['servicio_id' => $serviceId]);

    if ((int) $accountCountStmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'Solo puedes eliminar servicios que no tengan cuentas registradas.'];
    }

    $deleteStmt = $pdo->prepare('DELETE FROM servicios WHERE id = :id');
    $deleteStmt->execute(['id' => $serviceId]);

    if ($deleteStmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'No fue posible eliminar el servicio.'];
    }

    deleteLocalServiceAsset((string) $service['logo_url']);

    return ['success' => true, 'message' => 'Servicio eliminado correctamente.'];
}

function createServiceAccount(array $input): array
{
    requireAdminUser();

    $serviceId = (int) ($input['servicio_id'] ?? 0);
    $accessEmail = strtolower(trim((string) ($input['correo_acceso'] ?? '')));
    $accessPassword = trim((string) ($input['password_acceso'] ?? ''));
    $description = trim((string) ($input['descripcion'] ?? ''));

    if ($serviceId <= 0 || $accessEmail === '' || $accessPassword === '') {
        return ['success' => false, 'message' => 'Completa servicio, correo y contraseña de la cuenta.'];
    }

    if (!filter_var($accessEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El correo de acceso no es válido.'];
    }

    $pdo = getPdo();
    $serviceStmt = $pdo->prepare('SELECT id FROM servicios WHERE id = :id LIMIT 1');
    $serviceStmt->execute(['id' => $serviceId]);

    if ($serviceStmt->fetch() === false) {
        return ['success' => false, 'message' => 'El servicio seleccionado no existe.'];
    }

    $stmt = $pdo->prepare('INSERT INTO cuentas_servicio (servicio_id, correo_acceso, password_acceso, descripcion, activo) VALUES (:servicio_id, :correo_acceso, :password_acceso, :descripcion, :activo)');

    try {
        $stmt->execute([
            'servicio_id' => $serviceId,
            'correo_acceso' => $accessEmail,
            'password_acceso' => $accessPassword,
            'descripcion' => $description !== '' ? $description : null,
            'activo' => 1,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            return ['success' => false, 'message' => 'Esa cuenta ya fue registrada para el servicio.'];
        }

        throw $exception;
    }

    return ['success' => true, 'message' => 'Cuenta del servicio creada correctamente.'];
}

function updateServiceAccount(array $input): array
{
    requireAdminUser();

    $accountId = (int) ($input['cuenta_id'] ?? 0);
    $serviceId = (int) ($input['servicio_id'] ?? 0);
    $accessEmail = strtolower(trim((string) ($input['correo_acceso'] ?? '')));
    $accessPassword = trim((string) ($input['password_acceso'] ?? ''));
    $description = trim((string) ($input['descripcion'] ?? ''));

    if ($accountId <= 0 || $serviceId <= 0 || $accessEmail === '' || $accessPassword === '') {
        return ['success' => false, 'message' => 'Completa servicio, correo y contraseña de la cuenta.'];
    }

    if (!filter_var($accessEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El correo de acceso no es válido.'];
    }

    $pdo = getPdo();
    $accountStmt = $pdo->prepare('SELECT id FROM cuentas_servicio WHERE id = :id LIMIT 1');
    $accountStmt->execute(['id' => $accountId]);

    if ($accountStmt->fetch() === false) {
        return ['success' => false, 'message' => 'La cuenta indicada no existe.'];
    }

    $serviceStmt = $pdo->prepare('SELECT id FROM servicios WHERE id = :id LIMIT 1');
    $serviceStmt->execute(['id' => $serviceId]);

    if ($serviceStmt->fetch() === false) {
        return ['success' => false, 'message' => 'El servicio seleccionado no existe.'];
    }

    $dupStmt = $pdo->prepare('SELECT id FROM cuentas_servicio WHERE servicio_id = :servicio_id AND correo_acceso = :correo_acceso AND id <> :id LIMIT 1');
    $dupStmt->execute([
        'servicio_id' => $serviceId,
        'correo_acceso' => $accessEmail,
        'id' => $accountId,
    ]);

    if ($dupStmt->fetch() !== false) {
        return ['success' => false, 'message' => 'Esa cuenta ya fue registrada para el servicio.'];
    }

    $stmt = $pdo->prepare('UPDATE cuentas_servicio SET servicio_id = :servicio_id, correo_acceso = :correo_acceso, password_acceso = :password_acceso, descripcion = :descripcion WHERE id = :id');

    try {
        $stmt->execute([
            'servicio_id' => $serviceId,
            'correo_acceso' => $accessEmail,
            'password_acceso' => $accessPassword,
            'descripcion' => $description !== '' ? $description : null,
            'id' => $accountId,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            return ['success' => false, 'message' => 'Esa cuenta ya fue registrada para el servicio.'];
        }

        throw $exception;
    }

    return ['success' => true, 'message' => 'Cuenta del servicio actualizada correctamente.'];
}

function deleteServiceAccount(array $input): array
{
    requireAdminUser();

    $accountId = (int) ($input['cuenta_id'] ?? 0);

    if ($accountId <= 0) {
        return ['success' => false, 'message' => 'Debes indicar la cuenta a eliminar.'];
    }

    $pdo = getPdo();
    $accountStmt = $pdo->prepare('SELECT id FROM cuentas_servicio WHERE id = :id LIMIT 1');
    $accountStmt->execute(['id' => $accountId]);

    if ($accountStmt->fetch() === false) {
        return ['success' => false, 'message' => 'La cuenta indicada no existe.'];
    }

    $assignmentCountStmt = $pdo->prepare('SELECT COUNT(*) FROM usuario_cuentas_servicio WHERE cuenta_servicio_id = :cuenta_id');
    $assignmentCountStmt->execute(['cuenta_id' => $accountId]);

    if ((int) $assignmentCountStmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'No puedes eliminar una cuenta que tiene usuarios asignados.'];
    }

    $deleteStmt = $pdo->prepare('DELETE FROM cuentas_servicio WHERE id = :id');
    $deleteStmt->execute(['id' => $accountId]);

    if ($deleteStmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'No fue posible eliminar la cuenta del servicio.'];
    }

    return ['success' => true, 'message' => 'Cuenta del servicio eliminada correctamente.'];
}

function assignAccountToUser(array $input): array
{
    requireAdminUser();

    $userId = (int) ($input['usuario_id'] ?? 0);
    $accountId = (int) ($input['cuenta_servicio_id'] ?? 0);

    if ($userId <= 0 || $accountId <= 0) {
        return ['success' => false, 'message' => 'Selecciona el usuario y la cuenta a asignar.'];
    }

    $pdo = getPdo();

    $userStmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
    $userStmt->execute(['id' => $userId]);
    if ($userStmt->fetch() === false) {
        return ['success' => false, 'message' => 'El usuario seleccionado no existe o no es asignable.'];
    }

    $accountStmt = $pdo->prepare('SELECT id FROM cuentas_servicio WHERE id = :id LIMIT 1');
    $accountStmt->execute(['id' => $accountId]);
    if ($accountStmt->fetch() === false) {
        return ['success' => false, 'message' => 'La cuenta seleccionada no existe.'];
    }

    $stmt = $pdo->prepare('INSERT INTO usuario_cuentas_servicio (usuario_id, cuenta_servicio_id) VALUES (:usuario_id, :cuenta_servicio_id)');

    try {
        $stmt->execute([
            'usuario_id' => $userId,
            'cuenta_servicio_id' => $accountId,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            return ['success' => false, 'message' => 'Esa cuenta ya está asignada a ese usuario.'];
        }

        throw $exception;
    }

    return ['success' => true, 'message' => 'Cuenta asignada correctamente.'];
}

function unassignAccountFromUser(array $input): array
{
    requireAdminUser();

    $assignmentId = (int) ($input['assignment_id'] ?? 0);

    if ($assignmentId <= 0) {
        return ['success' => false, 'message' => 'Debes indicar la asignación a eliminar.'];
    }

    $pdo = getPdo();
    $stmt = $pdo->prepare('DELETE FROM usuario_cuentas_servicio WHERE id = :id');
    $stmt->execute(['id' => $assignmentId]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'La asignación indicada no existe.'];
    }

    return ['success' => true, 'message' => 'Cuenta desasignada correctamente.'];
}

function updateRegisteredUser(array $input): array
{
    requireAdminUser();

    $userId = (int) ($input['usuario_id'] ?? 0);
    $name = trim((string) ($input['nombre'] ?? ''));
    $lastName = trim((string) ($input['apellido'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $phone = trim((string) ($input['telefono'] ?? ''));
    $active = isset($input['activo']) ? (int) $input['activo'] : 1;

    if ($userId <= 0 || $name === '' || $lastName === '' || $username === '' || $email === '') {
        return ['success' => false, 'message' => 'Completa los datos obligatorios del usuario.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El correo del usuario no es válido.'];
    }

    $pdo = getPdo();
    $existsStmt = $pdo->prepare("SELECT id FROM usuarios WHERE role = 'usuario' AND id = :id LIMIT 1");
    $existsStmt->execute(['id' => $userId]);

    if ($existsStmt->fetch() === false) {
        return ['success' => false, 'message' => 'El usuario a editar no existe.'];
    }

    $dupStmt = $pdo->prepare('SELECT id FROM usuarios WHERE (email = :email OR username = :username) AND id <> :id LIMIT 1');
    $dupStmt->execute([
        'email' => $email,
        'username' => $username,
        'id' => $userId,
    ]);

    if ($dupStmt->fetch() !== false) {
        return ['success' => false, 'message' => 'El correo o usuario ya pertenece a otro registro.'];
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET nombre = :nombre, apellido = :apellido, username = :username, email = :email, telefono = :telefono, activo = :activo WHERE id = :id AND role = :role');
    $stmt->execute([
        'nombre' => $name,
        'apellido' => $lastName,
        'username' => $username,
        'email' => $email,
        'telefono' => $phone !== '' ? $phone : null,
        'activo' => $active === 1 ? 1 : 0,
        'id' => $userId,
        'role' => 'usuario',
    ]);

    return ['success' => true, 'message' => 'Usuario actualizado correctamente.'];
}

function createRegisteredUser(array $input): array
{
    requireAdminUser();

    $name = trim((string) ($input['nombre'] ?? ''));
    $lastName = trim((string) ($input['apellido'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $phone = trim((string) ($input['telefono'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($name === '' || $lastName === '' || $username === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'Completa los datos obligatorios del usuario.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El correo del usuario no es válido.'];
    }

    if (mb_strlen($password) < 6) {
        return ['success' => false, 'message' => 'La clave debe tener al menos 6 caracteres.'];
    }

    $pdo = getPdo();
    $dupStmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email OR username = :username LIMIT 1');
    $dupStmt->execute([
        'email' => $email,
        'username' => $username,
    ]);

    if ($dupStmt->fetch() !== false) {
        return ['success' => false, 'message' => 'El correo o usuario ya se encuentra registrado.'];
    }

    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, apellido, username, email, telefono, password_hash, role, activo) VALUES (:nombre, :apellido, :username, :email, :telefono, :password_hash, :role, :activo)');
    $stmt->execute([
        'nombre' => $name,
        'apellido' => $lastName,
        'username' => $username,
        'email' => $email,
        'telefono' => $phone !== '' ? $phone : null,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'usuario',
        'activo' => 1,
    ]);

    return ['success' => true, 'message' => 'Usuario creado correctamente desde administración.'];
}

function deleteRegisteredUser(array $input): array
{
    requireAdminUser();

    $userId = (int) ($input['usuario_id'] ?? 0);

    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Debes indicar el usuario a eliminar.'];
    }

    $pdo = getPdo();
    $assignmentCountStmt = $pdo->prepare('SELECT COUNT(*) FROM usuario_cuentas_servicio WHERE usuario_id = :usuario_id');
    $assignmentCountStmt->execute(['usuario_id' => $userId]);

    if ((int) $assignmentCountStmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'No puedes eliminar un usuario que tiene cuentas asignadas.'];
    }

    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id AND role = 'usuario'");
    $stmt->execute(['id' => $userId]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'El usuario indicado no existe o no puede eliminarse.'];
    }

    return ['success' => true, 'message' => 'Usuario eliminado correctamente.'];
}

function normalizeColor(string $value): string
{
    $color = trim($value);

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
        return strtoupper($color);
    }

    return '#0B57D0';
}

function slugify(string $value): string
{
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $normalized = strtolower($normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : 'servicio';
}

function uniqueServiceSlug(PDO $pdo, string $baseSlug): string
{
    $slug = $baseSlug;
    $counter = 2;
    $stmt = $pdo->prepare('SELECT id FROM servicios WHERE slug = :slug LIMIT 1');

    while (true) {
        $stmt->execute(['slug' => $slug]);

        if ($stmt->fetch() === false) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
}

function normalizeServicePayload(array $input): array
{
    return [
        'nombre' => trim((string) ($input['nombre'] ?? '')),
        'color_destacado' => normalizeColor((string) ($input['color_destacado'] ?? '#0b57d0')),
        'descripcion' => ($description = trim((string) ($input['descripcion'] ?? ''))) !== '' ? $description : null,
    ];
}

function uploadServiceLogo(?array $file): ?string
{
    if ($file === null || !isset($file['error'])) {
        return null;
    }

    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No fue posible subir el logo del servicio.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('El archivo del logo no es válido.');
    }

    $allowedMimeTypes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    $mimeType = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? finfo_file($finfo, $tmpName) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }
    }

    if (!is_string($mimeType) || !isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException('El logo debe ser una imagen válida en formato PNG, JPG, WEBP, GIF o SVG.');
    }

    $relativeDir = 'assets/services';
    $absoluteDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'services';

    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
        throw new RuntimeException('No fue posible preparar la carpeta para guardar el logo.');
    }

    $fileName = 'service_' . bin2hex(random_bytes(12)) . '.' . $allowedMimeTypes[$mimeType];
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new RuntimeException('No fue posible guardar el logo del servicio.');
    }

    return $relativeDir . '/' . $fileName;
}

function deleteLocalServiceAsset(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '' || strpos($relativePath, 'assets/services/') !== 0) {
        return;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}