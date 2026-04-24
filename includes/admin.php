<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/mail.php';

function getAdminOverview(): array
{
    $authenticatedUser = requireAdminUser();

    $pdo = getPdo();
    ensureGallerySlidesTable($pdo);
    $adminProfile = fetchAdminProfile($pdo, (int) $authenticatedUser['id']);

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
        'gallery_slides' => fetchGallerySlides($pdo),
        'admin_profile' => $adminProfile,
        'admin_settings' => fetchStoredAdminConfiguration($pdo),
        'mail_configuration' => formatMailConfigurationForClient(fetchStoredMailConfiguration($pdo)),
    ];
}

function getPublicAppConfiguration(): array
{
    return fetchStoredAdminConfiguration();
}

function getPublicGallerySlides(): array
{
    return fetchGallerySlides();
}

function saveAdminConfiguration(array $input, array $files = []): array
{
    $authenticatedUser = requireAdminUser();
    $name = trim((string) ($input['nombre'] ?? ''));
    $lastName = trim((string) ($input['apellido'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $phone = trim((string) ($input['telefono'] ?? ''));
    $pageName = trim((string) ($input['nombre_pagina'] ?? ''));
    $barColor = normalizeColor((string) ($input['bar_color'] ?? '#0b57d0'));
    $password = (string) ($input['password'] ?? '');

    if ($name === '' || $lastName === '' || $username === '' || $email === '' || $pageName === '') {
        return ['success' => false, 'message' => 'Completa los datos obligatorios del administrador y el nombre de la página.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El correo del administrador no es válido.'];
    }

    if ($password !== '' && mb_strlen($password) < 6) {
        return ['success' => false, 'message' => 'La nueva clave del administrador debe tener al menos 6 caracteres.'];
    }

    $pdo = getPdo();
    ensureAdminConfigurationTable($pdo);
    $currentAdmin = fetchAdminProfile($pdo, (int) $authenticatedUser['id']);
    $currentSettings = fetchStoredAdminConfiguration($pdo);
    $dupStmt = $pdo->prepare('SELECT id FROM usuarios WHERE (email = :email OR username = :username) AND id <> :id LIMIT 1');
    $dupStmt->execute([
        'email' => $email,
        'username' => $username,
        'id' => $authenticatedUser['id'],
    ]);

    if ($dupStmt->fetch() !== false) {
        return ['success' => false, 'message' => 'Ese correo o usuario ya pertenece a otra cuenta.'];
    }

    $newLogoPath = uploadAdminSiteLogo($files['logo_pagina'] ?? null);
    $finalLogoPath = $newLogoPath ?? ($currentSettings['logo_url'] !== '' ? $currentSettings['logo_url'] : null);

    $pdo->beginTransaction();

    try {
        $adminParams = [
            'nombre' => $name,
            'apellido' => $lastName,
            'username' => $username,
            'email' => $email,
            'telefono' => $phone !== '' ? $phone : null,
            'id' => $authenticatedUser['id'],
        ];

        if ($password !== '') {
            $updateAdminStmt = $pdo->prepare("UPDATE usuarios SET nombre = :nombre, apellido = :apellido, username = :username, email = :email, telefono = :telefono, password_hash = :password_hash WHERE id = :id AND role = 'admin'");
            $adminParams['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        } else {
            $updateAdminStmt = $pdo->prepare("UPDATE usuarios SET nombre = :nombre, apellido = :apellido, username = :username, email = :email, telefono = :telefono WHERE id = :id AND role = 'admin'");
        }

        $updateAdminStmt->execute($adminParams);

        $saveSettingsStmt = $pdo->prepare('INSERT INTO configuracion_admin (id, nombre_pagina, logo_url, bar_color) VALUES (1, :nombre_pagina, :logo_url, :bar_color) ON DUPLICATE KEY UPDATE nombre_pagina = VALUES(nombre_pagina), logo_url = VALUES(logo_url), bar_color = VALUES(bar_color)');
        $saveSettingsStmt->execute([
            'nombre_pagina' => $pageName,
            'logo_url' => $finalLogoPath,
            'bar_color' => $barColor,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        deleteLocalAdminBrandAsset($newLogoPath);
        throw $exception;
    }

    if ($newLogoPath !== null) {
        deleteLocalAdminBrandAsset($currentSettings['logo_url'] ?? null);
    }

    $updatedUser = [
        'id' => (int) $authenticatedUser['id'],
        'nombre' => $name,
        'apellido' => $lastName,
        'username' => $username,
        'email' => $email,
        'role' => 'admin',
    ];
    setAuthenticatedUser($updatedUser);

    return [
        'success' => true,
        'message' => 'Los datos del administrador fueron actualizados correctamente.',
        'admin_profile' => $updatedUser + ['telefono' => $phone !== '' ? $phone : null],
        'admin_settings' => fetchStoredAdminConfiguration($pdo),
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

function createGallerySlide(array $input, array $files = []): array
{
    requireAdminUser();

    $slideData = normalizeGallerySlidePayload($input);
    $imagePath = uploadGalleryImage($files['imagen'] ?? null);

    if ($imagePath === null) {
        return ['success' => false, 'message' => 'Debes seleccionar una imagen para el slide de la galería.'];
    }

    $pdo = getPdo();
    ensureGallerySlidesTable($pdo);
    $stmt = $pdo->prepare('INSERT INTO galeria_slides (image_url, texto, enlace, open_in_new_tab, sort_order) VALUES (:image_url, :texto, :enlace, :open_in_new_tab, :sort_order)');

    try {
        $stmt->execute([
            'image_url' => $imagePath,
            'texto' => $slideData['texto'],
            'enlace' => $slideData['enlace'],
            'open_in_new_tab' => $slideData['open_in_new_tab'],
            'sort_order' => getNextGallerySortOrder($pdo),
        ]);
    } catch (Throwable $exception) {
        deleteLocalGalleryAsset($imagePath);
        throw $exception;
    }

    return ['success' => true, 'message' => 'Slide de la galería guardado correctamente.'];
}

function updateGallerySlide(array $input, array $files = []): array
{
    requireAdminUser();

    $slideId = (int) ($input['slide_id'] ?? 0);
    $slideData = normalizeGallerySlidePayload($input);

    if ($slideId <= 0) {
        return ['success' => false, 'message' => 'Debes indicar el slide que deseas actualizar.'];
    }

    $pdo = getPdo();
    ensureGallerySlidesTable($pdo);
    $currentSlideStmt = $pdo->prepare('SELECT id, image_url FROM galeria_slides WHERE id = :id LIMIT 1');
    $currentSlideStmt->execute(['id' => $slideId]);
    $currentSlide = $currentSlideStmt->fetch();

    if ($currentSlide === false) {
        return ['success' => false, 'message' => 'El slide indicado no existe.'];
    }

    $newImagePath = uploadGalleryImage($files['imagen'] ?? null);
    $finalImagePath = $newImagePath ?? (($currentSlide['image_url'] ?? '') !== '' ? (string) $currentSlide['image_url'] : null);

    if ($finalImagePath === null) {
        return ['success' => false, 'message' => 'El slide debe conservar o incluir una imagen válida.'];
    }

    $stmt = $pdo->prepare('UPDATE galeria_slides SET image_url = :image_url, texto = :texto, enlace = :enlace, open_in_new_tab = :open_in_new_tab WHERE id = :id');

    try {
        $stmt->execute([
            'image_url' => $finalImagePath,
            'texto' => $slideData['texto'],
            'enlace' => $slideData['enlace'],
            'open_in_new_tab' => $slideData['open_in_new_tab'],
            'id' => $slideId,
        ]);
    } catch (Throwable $exception) {
        deleteLocalGalleryAsset($newImagePath);
        throw $exception;
    }

    if ($newImagePath !== null) {
        deleteLocalGalleryAsset((string) $currentSlide['image_url']);
    }

    return ['success' => true, 'message' => 'Slide de la galería actualizado correctamente.'];
}

function deleteGallerySlide(array $input): array
{
    requireAdminUser();

    $slideId = (int) ($input['slide_id'] ?? 0);

    if ($slideId <= 0) {
        return ['success' => false, 'message' => 'Debes indicar el slide que deseas eliminar.'];
    }

    $pdo = getPdo();
    ensureGallerySlidesTable($pdo);
    $slideStmt = $pdo->prepare('SELECT id, image_url FROM galeria_slides WHERE id = :id LIMIT 1');
    $slideStmt->execute(['id' => $slideId]);
    $slide = $slideStmt->fetch();

    if ($slide === false) {
        return ['success' => false, 'message' => 'El slide indicado no existe.'];
    }

    $deleteStmt = $pdo->prepare('DELETE FROM galeria_slides WHERE id = :id');
    $deleteStmt->execute(['id' => $slideId]);

    if ($deleteStmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'No fue posible eliminar el slide indicado.'];
    }

    deleteLocalGalleryAsset((string) $slide['image_url']);

    return ['success' => true, 'message' => 'Slide de la galería eliminado correctamente.'];
}

function updateGallerySlideOrder(array $input): array
{
    requireAdminUser();

    $slideId = (int) ($input['slide_id'] ?? 0);
    $sortOrder = filter_var($input['sort_order'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($slideId <= 0 || $sortOrder === false) {
        return ['success' => false, 'message' => 'Debes indicar un slide válido y un orden mayor o igual a 1.'];
    }

    $pdo = getPdo();
    ensureGallerySlidesTable($pdo);
    $stmt = $pdo->prepare('UPDATE galeria_slides SET sort_order = :sort_order WHERE id = :id');
    $stmt->execute([
        'sort_order' => (int) $sortOrder,
        'id' => $slideId,
    ]);

    if ($stmt->rowCount() === 0) {
        $existsStmt = $pdo->prepare('SELECT id FROM galeria_slides WHERE id = :id LIMIT 1');
        $existsStmt->execute(['id' => $slideId]);

        if ($existsStmt->fetch() === false) {
            return ['success' => false, 'message' => 'El slide indicado no existe.'];
        }
    }

    return ['success' => true, 'message' => 'El orden del slide fue actualizado correctamente.'];
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

function resetRegisteredUserPassword(array $input): array
{
    requireAdminUser();

    $userId = (int) ($input['usuario_id'] ?? 0);

    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Debes indicar el usuario al que deseas restablecer la clave.'];
    }

    $pdo = getPdo();
    $userStmt = $pdo->prepare("SELECT id, nombre, apellido FROM usuarios WHERE id = :id AND role = 'usuario' LIMIT 1");
    $userStmt->execute(['id' => $userId]);
    $user = $userStmt->fetch();

    if ($user === false) {
        return ['success' => false, 'message' => 'El usuario indicado no existe o no puede modificarse.'];
    }

    $temporaryPassword = generateTemporaryUserPassword();
    $updateStmt = $pdo->prepare("UPDATE usuarios SET password_hash = :password_hash WHERE id = :id AND role = 'usuario'");
    $updateStmt->execute([
        'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    return [
        'success' => true,
        'message' => 'La clave del usuario fue restablecida correctamente.',
        'temporary_password' => $temporaryPassword,
        'user_full_name' => trim((string) $user['nombre'] . ' ' . (string) $user['apellido']),
    ];
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

function generateTemporaryUserPassword(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';

    for ($index = 0; $index < $length; $index++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }

    return $password;
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

function normalizeGallerySlidePayload(array $input): array
{
    $link = trim((string) ($input['enlace'] ?? ''));
    $linkTarget = (string) ($input['link_target'] ?? 'blank');

    if ($link !== '' && !isValidGalleryLink($link)) {
        throw new RuntimeException('El enlace del slide no es válido. Usa una URL completa o una ruta interna válida.');
    }

    return [
        'texto' => ($text = trim((string) ($input['texto'] ?? ''))) !== '' ? $text : null,
        'enlace' => $link !== '' ? $link : null,
        'open_in_new_tab' => $linkTarget === 'self' ? 0 : 1,
    ];
}

function isValidGalleryLink(string $value): bool
{
    if ($value === '') {
        return true;
    }

    if (filter_var($value, FILTER_VALIDATE_URL)) {
        return true;
    }

    return str_starts_with($value, '/') || str_starts_with($value, './') || str_starts_with($value, '../') || str_starts_with($value, '#');
}

function fetchAdminProfile(PDO $pdo, int $adminUserId): array
{
    $stmt = $pdo->prepare("SELECT id, nombre, apellido, username, email, telefono FROM usuarios WHERE id = :id AND role = 'admin' LIMIT 1");
    $stmt->execute(['id' => $adminUserId]);
    $admin = $stmt->fetch();

    if ($admin === false) {
        throw new RuntimeException('No fue posible cargar el perfil del administrador.');
    }

    return [
        'id' => (int) $admin['id'],
        'nombre' => (string) $admin['nombre'],
        'apellido' => (string) $admin['apellido'],
        'username' => (string) $admin['username'],
        'email' => (string) $admin['email'],
        'telefono' => $admin['telefono'] !== null ? (string) $admin['telefono'] : null,
    ];
}

function ensureAdminConfigurationTable(?PDO $pdo = null): void
{
    $pdo ??= getPdo();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS configuracion_admin (
            id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            nombre_pagina VARCHAR(160) NOT NULL DEFAULT "Prycorreos",
            logo_url VARCHAR(255) NULL,
            bar_color VARCHAR(20) NOT NULL DEFAULT "#0b57d0",
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $columnStmt = $pdo->query("SHOW COLUMNS FROM configuracion_admin LIKE 'bar_color'");
    if ($columnStmt->fetch() === false) {
        $pdo->exec('ALTER TABLE configuracion_admin ADD COLUMN bar_color VARCHAR(20) NOT NULL DEFAULT "#0b57d0" AFTER logo_url');
    }

    $pdo->exec("INSERT IGNORE INTO configuracion_admin (id, nombre_pagina, logo_url, bar_color) VALUES (1, 'Prycorreos', NULL, '#0b57d0')");
}

function ensureGallerySlidesTable(?PDO $pdo = null): void
{
    $pdo ??= getPdo();
    $columnWasAdded = false;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS galeria_slides (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            image_url VARCHAR(255) NOT NULL,
            texto VARCHAR(255) NULL,
            enlace VARCHAR(500) NULL,
            open_in_new_tab TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $columnStmt = $pdo->query("SHOW COLUMNS FROM galeria_slides LIKE 'sort_order'");
    if ($columnStmt->fetch() === false) {
        $pdo->exec('ALTER TABLE galeria_slides ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 1 AFTER open_in_new_tab');
        $columnWasAdded = true;
    }

    $pdo->exec('UPDATE galeria_slides SET sort_order = id WHERE sort_order IS NULL OR sort_order = 0');

    if ($columnWasAdded) {
        $pdo->exec('UPDATE galeria_slides SET sort_order = id');
        return;
    }

    $stats = $pdo->query('SELECT COUNT(*) AS total_rows, COUNT(DISTINCT sort_order) AS distinct_orders, MIN(sort_order) AS min_order, MAX(sort_order) AS max_order FROM galeria_slides')->fetch();

    if (
        is_array($stats)
        && (int) ($stats['total_rows'] ?? 0) > 1
        && (int) ($stats['distinct_orders'] ?? 0) === 1
        && (int) ($stats['min_order'] ?? 0) === 1
        && (int) ($stats['max_order'] ?? 0) === 1
    ) {
        $pdo->exec('UPDATE galeria_slides SET sort_order = id');
    }
}

function fetchGallerySlides(?PDO $pdo = null): array
{
    $pdo ??= getPdo();
    ensureGallerySlidesTable($pdo);
    $slides = $pdo->query('SELECT id, image_url, texto, enlace, open_in_new_tab, sort_order, created_at, updated_at FROM galeria_slides ORDER BY sort_order ASC, id ASC')->fetchAll();

    return array_values(array_map(static function (array $slide): array {
        return [
            'id' => (int) $slide['id'],
            'image_url' => (string) $slide['image_url'],
            'texto' => $slide['texto'] !== null ? (string) $slide['texto'] : null,
            'enlace' => $slide['enlace'] !== null ? (string) $slide['enlace'] : null,
            'open_in_new_tab' => (int) $slide['open_in_new_tab'] === 1,
            'sort_order' => (int) ($slide['sort_order'] ?? 1),
            'created_at' => $slide['created_at'] !== null ? (string) $slide['created_at'] : null,
            'updated_at' => $slide['updated_at'] !== null ? (string) $slide['updated_at'] : null,
        ];
    }, is_array($slides) ? $slides : []));
}

function getNextGallerySortOrder(PDO $pdo): int
{
    $value = $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM galeria_slides')->fetchColumn();
    return max(1, (int) $value);
}

function fetchStoredAdminConfiguration(?PDO $pdo = null): array
{
    $pdo ??= getPdo();
    ensureAdminConfigurationTable($pdo);
    $stmt = $pdo->query('SELECT id, nombre_pagina, logo_url, bar_color, updated_at FROM configuracion_admin WHERE id = 1 LIMIT 1');
    $configuration = $stmt->fetch();

    if ($configuration === false) {
        return [
            'id' => 1,
            'nombre_pagina' => 'Prycorreos',
            'logo_url' => null,
            'bar_color' => '#0b57d0',
            'updated_at' => null,
        ];
    }

    $logoUrl = $configuration['logo_url'] !== null ? (string) $configuration['logo_url'] : null;

    if ($logoUrl !== null && !localAssetExists($logoUrl, 'assets/branding/')) {
        $logoUrl = null;
    }

    return [
        'id' => (int) $configuration['id'],
        'nombre_pagina' => trim((string) ($configuration['nombre_pagina'] ?? '')) !== '' ? (string) $configuration['nombre_pagina'] : 'Prycorreos',
        'logo_url' => $logoUrl,
        'bar_color' => normalizeColor((string) ($configuration['bar_color'] ?? '#0b57d0')),
        'updated_at' => $configuration['updated_at'] !== null ? (string) $configuration['updated_at'] : null,
    ];
}

function uploadServiceLogo(?array $file): ?string
{
    return uploadImageAsset($file, 'assets/services', 'service_', 'logo del servicio');
}

function uploadAdminSiteLogo(?array $file): ?string
{
    return uploadImageAsset($file, 'assets/branding', 'branding_', 'logo de la página');
}

function uploadGalleryImage(?array $file): ?string
{
    return uploadImageAsset($file, 'assets/galeria', 'gallery_', 'imagen de la galería');
}

function uploadImageAsset(?array $file, string $relativeDir, string $prefix, string $entityLabel): ?string
{
    if ($file === null || !isset($file['error'])) {
        return null;
    }

    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(sprintf('No fue posible subir el %s.', $entityLabel));
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException(sprintf('El archivo del %s no es válido.', $entityLabel));
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
        throw new RuntimeException(sprintf('El %s debe ser una imagen válida en formato PNG, JPG, WEBP, GIF o SVG.', $entityLabel));
    }

    $absoluteDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
        throw new RuntimeException(sprintf('No fue posible preparar la carpeta para guardar el %s.', $entityLabel));
    }

    $fileName = $prefix . bin2hex(random_bytes(12)) . '.' . $allowedMimeTypes[$mimeType];
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new RuntimeException(sprintf('No fue posible guardar el %s.', $entityLabel));
    }

    return $relativeDir . '/' . $fileName;
}

function deleteLocalServiceAsset(?string $relativePath): void
{
    deleteLocalAsset($relativePath, 'assets/services/');
}

function deleteLocalAdminBrandAsset(?string $relativePath): void
{
    deleteLocalAsset($relativePath, 'assets/branding/');
}

function deleteLocalGalleryAsset(?string $relativePath): void
{
    deleteLocalAsset($relativePath, 'assets/galeria/');
}

function deleteLocalAsset(?string $relativePath, string $expectedPrefix): void
{
    if ($relativePath === null || $relativePath === '' || strpos($relativePath, $expectedPrefix) !== 0) {
        return;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function localAssetExists(?string $relativePath, string $expectedPrefix): bool
{
    if ($relativePath === null || $relativePath === '' || strpos($relativePath, $expectedPrefix) !== 0) {
        return false;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($absolutePath);
}