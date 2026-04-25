<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function ensureUserProfileColumns(?PDO $pdo = null): void
{
    $pdo ??= getPdo();

    $columnDefinitions = [
        'nombre_tienda' => 'ALTER TABLE usuarios ADD COLUMN nombre_tienda VARCHAR(160) NULL AFTER telefono',
        'facebook' => 'ALTER TABLE usuarios ADD COLUMN facebook VARCHAR(255) NULL AFTER nombre_tienda',
        'instagram' => 'ALTER TABLE usuarios ADD COLUMN instagram VARCHAR(255) NULL AFTER facebook',
        'tiktok' => 'ALTER TABLE usuarios ADD COLUMN tiktok VARCHAR(255) NULL AFTER instagram',
        'whatsapp' => 'ALTER TABLE usuarios ADD COLUMN whatsapp VARCHAR(255) NULL AFTER tiktok',
        'telegram' => 'ALTER TABLE usuarios ADD COLUMN telegram VARCHAR(255) NULL AFTER whatsapp',
        'foto_perfil_url' => 'ALTER TABLE usuarios ADD COLUMN foto_perfil_url VARCHAR(255) NULL AFTER telegram',
    ];

    foreach ($columnDefinitions as $columnName => $statement) {
        $columnStmt = $pdo->query(sprintf("SHOW COLUMNS FROM usuarios LIKE '%s'", $columnName));

        if ($columnStmt->fetch() === false) {
            $pdo->exec($statement);
        }
    }
}

function normalizeOptionalUserText(string $value, int $maxLength = 255): ?string
{
    $normalized = trim($value);

    if ($normalized === '') {
        return null;
    }

    return mb_substr($normalized, 0, $maxLength);
}

function normalizeUserSocialLink(string $platform, string $value): ?string
{
    $normalized = trim($value);

    if ($normalized === '') {
        return null;
    }

    if (filter_var($normalized, FILTER_VALIDATE_URL)) {
        return $normalized;
    }

    $normalized = preg_replace('#^https?://#i', '', $normalized) ?? $normalized;
    $normalized = preg_replace('#^(www\.)#i', '', $normalized) ?? $normalized;
    $normalized = trim($normalized, " \t\n\r\0\x0B/");

    return match ($platform) {
        'facebook' => 'https://www.facebook.com/' . ltrim(preg_replace('#^facebook\.com/#i', '', $normalized) ?? $normalized, '@/'),
        'instagram' => 'https://www.instagram.com/' . ltrim(preg_replace('#^instagram\.com/#i', '', $normalized) ?? $normalized, '@/'),
        'tiktok' => 'https://www.tiktok.com/' . (str_starts_with(ltrim(preg_replace('#^tiktok\.com/#i', '', $normalized) ?? $normalized, '/'), '@')
            ? ltrim(preg_replace('#^tiktok\.com/#i', '', $normalized) ?? $normalized, '/')
            : '@' . ltrim(preg_replace('#^tiktok\.com/#i', '', $normalized) ?? $normalized, '@/')),
        'telegram' => 'https://t.me/' . ltrim(preg_replace('#^(t\.me/|telegram\.me/)#i', '', $normalized) ?? $normalized, '@/'),
        'whatsapp' => (($digits = preg_replace('/\D+/', '', $normalized) ?? '') !== '') ? 'https://wa.me/' . $digits : null,
        default => normalizeOptionalUserText($normalized),
    };
}

function normalizeUserExtraProfileInput(array $input): array
{
    return [
        'nombre_tienda' => normalizeOptionalUserText((string) ($input['nombre_tienda'] ?? ''), 160),
        'facebook' => normalizeUserSocialLink('facebook', (string) ($input['facebook'] ?? '')),
        'instagram' => normalizeUserSocialLink('instagram', (string) ($input['instagram'] ?? '')),
        'tiktok' => normalizeUserSocialLink('tiktok', (string) ($input['tiktok'] ?? '')),
        'whatsapp' => normalizeUserSocialLink('whatsapp', (string) ($input['whatsapp'] ?? '')),
        'telegram' => normalizeUserSocialLink('telegram', (string) ($input['telegram'] ?? '')),
    ];
}

function uploadUserProfilePhoto(?array $file): ?string
{
    return uploadUserScopedImageAsset($file, 'assets/users', 'user_', 'foto de perfil');
}

function deleteLocalUserProfileAsset(?string $relativePath): void
{
    deleteLocalUserScopedAsset($relativePath, 'assets/users/');
}

function userProfileAssetExists(?string $relativePath): bool
{
    return localUserScopedAssetExists($relativePath, 'assets/users/');
}

function uploadUserScopedImageAsset(?array $file, string $relativeDir, string $prefix, string $entityLabel): ?string
{
    if ($file === null || !isset($file['error'])) {
        return null;
    }

    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(sprintf('No fue posible subir la %s.', $entityLabel));
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException(sprintf('El archivo de la %s no es válido.', $entityLabel));
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
        throw new RuntimeException(sprintf('La %s debe ser una imagen válida en formato PNG, JPG, WEBP, GIF o SVG.', $entityLabel));
    }

    $absoluteDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
        throw new RuntimeException(sprintf('No fue posible preparar la carpeta para guardar la %s.', $entityLabel));
    }

    $fileName = $prefix . bin2hex(random_bytes(12)) . '.' . $allowedMimeTypes[$mimeType];
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new RuntimeException(sprintf('No fue posible guardar la %s.', $entityLabel));
    }

    return $relativeDir . '/' . $fileName;
}

function deleteLocalUserScopedAsset(?string $relativePath, string $expectedPrefix): void
{
    if ($relativePath === null || $relativePath === '' || strpos($relativePath, $expectedPrefix) !== 0) {
        return;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function localUserScopedAssetExists(?string $relativePath, string $expectedPrefix): bool
{
    if ($relativePath === null || $relativePath === '' || strpos($relativePath, $expectedPrefix) !== 0) {
        return false;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    return is_file($absolutePath);
}
