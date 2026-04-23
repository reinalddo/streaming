<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';

const DEFAULT_IMAP_MAILBOX = '{imap.hostinger.com:993/imap/ssl}INBOX';
const DEFAULT_MAIL_DELAY_DAYS = 0;
const DEFAULT_MAIL_DELAY_MINUTES = 20;
const DEFAULT_MAIL_MAX_MESSAGES = 20;
const IMAP_OPEN_TIMEOUT_SECONDS = 12;
const IMAP_READ_TIMEOUT_SECONDS = 20;
const IMAP_WRITE_TIMEOUT_SECONDS = 20;
const IMAP_CLOSE_TIMEOUT_SECONDS = 10;

function ensureMailConfigurationTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS configuracion_correo (
            id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            imap_mailbox VARCHAR(255) NOT NULL,
            imap_user VARCHAR(190) NOT NULL,
            imap_password VARCHAR(255) NOT NULL,
            delay_days INT UNSIGNED NOT NULL DEFAULT 0,
            delay_minutes INT UNSIGNED NOT NULL DEFAULT 20,
            max_messages INT UNSIGNED NOT NULL DEFAULT 20,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    ensureMailConfigurationColumns($pdo);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM configuracion_correo')->fetchColumn();

    if ($count === 0) {
        $defaults = getDefaultMailConfiguration();
        $stmt = $pdo->prepare('INSERT INTO configuracion_correo (id, imap_mailbox, imap_user, imap_password, delay_days, delay_minutes, max_messages) VALUES (1, :imap_mailbox, :imap_user, :imap_password, :delay_days, :delay_minutes, :max_messages)');
        $stmt->execute($defaults);
    }
}

function ensureMailConfigurationColumns(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM configuracion_correo')->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('delay_days', $columns, true)) {
        $pdo->exec('ALTER TABLE configuracion_correo ADD COLUMN delay_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER imap_password');
    }
}

function getDefaultMailConfiguration(): array
{
    return [
        'imap_mailbox' => DEFAULT_IMAP_MAILBOX,
        'imap_user' => '',
        'imap_password' => '',
        'delay_days' => DEFAULT_MAIL_DELAY_DAYS,
        'delay_minutes' => DEFAULT_MAIL_DELAY_MINUTES,
        'max_messages' => DEFAULT_MAIL_MAX_MESSAGES,
    ];
}

function fetchStoredMailConfiguration(?PDO $pdo = null): array
{
    $pdo ??= getPdo();
    ensureMailConfigurationTable($pdo);

    $record = $pdo->query('SELECT id, imap_mailbox, imap_user, imap_password, delay_days, delay_minutes, max_messages FROM configuracion_correo LIMIT 1')->fetch();

    if ($record === false) {
        return getDefaultMailConfiguration();
    }

    return [
        'imap_mailbox' => (string) ($record['imap_mailbox'] ?? DEFAULT_IMAP_MAILBOX),
        'imap_user' => (string) ($record['imap_user'] ?? ''),
        'imap_password' => (string) ($record['imap_password'] ?? ''),
        'delay_days' => max(0, min(365, (int) ($record['delay_days'] ?? DEFAULT_MAIL_DELAY_DAYS))),
        'delay_minutes' => max(1, (int) ($record['delay_minutes'] ?? DEFAULT_MAIL_DELAY_MINUTES)),
        'max_messages' => max(1, (int) ($record['max_messages'] ?? DEFAULT_MAIL_MAX_MESSAGES)),
    ];
}

function getAdminMailConfiguration(): array
{
    requireAdminUser();

    return [
        'success' => true,
        'configuration' => formatMailConfigurationForClient(fetchStoredMailConfiguration()),
    ];
}

function saveAdminMailConfiguration(array $input): array
{
    requireAdminUser();

    $pdo = getPdo();
    $current = fetchStoredMailConfiguration($pdo);
    $imapMailbox = trim((string) ($input['imap_mailbox'] ?? ''));
    $imapUser = strtolower(trim((string) ($input['imap_user'] ?? '')));
    $imapPassword = (string) ($input['imap_password'] ?? '');
    $delayDays = max(0, min(365, (int) ($input['delay_days'] ?? DEFAULT_MAIL_DELAY_DAYS)));
    $delayMinutes = max(1, min(10080, (int) ($input['delay_minutes'] ?? DEFAULT_MAIL_DELAY_MINUTES)));
    $maxMessages = max(1, min(100, (int) ($input['max_messages'] ?? DEFAULT_MAIL_MAX_MESSAGES)));

    if ($imapMailbox === '' || $imapUser === '') {
        return ['success' => false, 'message' => 'Debes completar el buzón IMAP y el correo de acceso.'];
    }

    if ($imapPassword === '') {
        $imapPassword = (string) ($current['imap_password'] ?? '');
    }

    if ($imapPassword === '') {
        return ['success' => false, 'message' => 'Debes indicar la clave del correo IMAP.'];
    }

    ensureMailConfigurationTable($pdo);
    $stmt = $pdo->prepare('UPDATE configuracion_correo SET imap_mailbox = :imap_mailbox, imap_user = :imap_user, imap_password = :imap_password, delay_days = :delay_days, delay_minutes = :delay_minutes, max_messages = :max_messages WHERE id = 1');
    $stmt->execute([
        'imap_mailbox' => $imapMailbox,
        'imap_user' => $imapUser,
        'imap_password' => $imapPassword,
        'delay_days' => $delayDays,
        'delay_minutes' => $delayMinutes,
        'max_messages' => $maxMessages,
    ]);

    return [
        'success' => true,
        'message' => 'Configuración de correo actualizada correctamente.',
        'configuration' => formatMailConfigurationForClient(fetchStoredMailConfiguration($pdo)),
    ];
}

function formatMailConfigurationForClient(array $configuration): array
{
    return [
        'imap_mailbox' => (string) ($configuration['imap_mailbox'] ?? DEFAULT_IMAP_MAILBOX),
        'imap_user' => (string) ($configuration['imap_user'] ?? ''),
        'imap_password' => '',
        'delay_days' => (int) ($configuration['delay_days'] ?? DEFAULT_MAIL_DELAY_DAYS),
        'delay_minutes' => (int) ($configuration['delay_minutes'] ?? DEFAULT_MAIL_DELAY_MINUTES),
        'max_messages' => (int) ($configuration['max_messages'] ?? DEFAULT_MAIL_MAX_MESSAGES),
    ];
}

function fetchRecentMailboxMessagesForAssignedAccount(string $accountEmail): array
{
    if (!function_exists('imap_open')) {
        throw new RuntimeException('La extensión PHP IMAP no está habilitada en este servidor.');
    }

    $config = fetchStoredMailConfiguration();

    if ($config['imap_mailbox'] === '' || $config['imap_user'] === '' || $config['imap_password'] === '') {
        throw new RuntimeException('El administrador aún no ha configurado el acceso al correo IMAP.');
    }

    $imap = openConfiguredMailbox($config['imap_mailbox'], $config['imap_user'], $config['imap_password']);

    try {
        return searchRecentMessagesForAccount(
            $imap,
            strtolower(trim($accountEmail)),
            (int) ($config['delay_days'] ?? DEFAULT_MAIL_DELAY_DAYS),
            (int) $config['delay_minutes'],
            (int) $config['max_messages']
        );
    } finally {
        imap_close($imap);
    }
}

/**
 * @return resource|\IMAP\Connection
 */
function openConfiguredMailbox(string $imapMailbox, string $imapUser, string $imapPassword)
{
    configureImapTimeouts();
    $imap = @imap_open($imapMailbox, $imapUser, $imapPassword);

    if ($imap === false) {
        throw new RuntimeException(getImapError('No fue posible abrir la conexión IMAP.'));
    }

    return $imap;
}

/**
 * @param resource|\IMAP\Connection $imap
 * @return array<int, array<string, mixed>>
 */
function searchRecentMessagesForAccount($imap, string $accountEmail, int $delayDays, int $delayMinutes, int $maxMessages): array
{
    $totalMinutes = max(1, ($delayDays * 1440) + $delayMinutes);
    $sinceTime = new DateTimeImmutable(sprintf('-%d minutes', $totalMinutes));
    $messageUids = imap_search($imap, sprintf('SINCE "%s"', $sinceTime->format('d-M-Y')), SE_UID);

    if ($messageUids === false) {
        return [];
    }

    rsort($messageUids);
    $messages = [];

    foreach ($messageUids as $uid) {
        $overviewList = imap_fetch_overview($imap, (string) $uid, FT_UID);

        if (!is_array($overviewList) || !isset($overviewList[0])) {
            continue;
        }

        $overview = $overviewList[0];
        $receivedAt = parseImapOverviewDate((string) ($overview->date ?? ''));

        if ($receivedAt === null || $receivedAt < $sinceTime) {
            continue;
        }

        $messageNumber = imap_msgno($imap, (int) $uid);

        if ($messageNumber <= 0) {
            continue;
        }

        $htmlBody = getHtmlBody($imap, $messageNumber);
        $plainTextBody = getPlainTextBody($imap, $messageNumber);

        if (!messageTargetsAccountEmail($imap, $messageNumber, $accountEmail, $plainTextBody, $htmlBody)) {
            continue;
        }

        $sanitizedHtml = $htmlBody !== null && trim($htmlBody) !== ''
            ? sanitizeHtmlBody($htmlBody)
            : nl2br(escapeHtmlFragment(trim($plainTextBody) !== '' ? trim($plainTextBody) : '[sin contenido]'));

        $messages[] = [
            'uid' => (int) $uid,
            'subject' => decodeMimeHeaderString((string) ($overview->subject ?? '')) ?: '[sin asunto]',
            'from' => decodeMimeHeaderString((string) ($overview->from ?? 'Desconocido')),
            'received_at' => $receivedAt->format('Y-m-d H:i:s'),
            'received_at_label' => $receivedAt->format('d/m/Y H:i'),
            'is_seen' => !empty($overview->seen),
            'body_html' => $sanitizedHtml,
            'preview' => buildMessagePreview($plainTextBody, $sanitizedHtml),
        ];

        if (count($messages) >= $maxMessages) {
            break;
        }
    }

    return $messages;
}

function configureImapTimeouts(): void
{
    if (function_exists('imap_timeout')) {
        @imap_timeout(IMAP_OPENTIMEOUT, IMAP_OPEN_TIMEOUT_SECONDS);
        @imap_timeout(IMAP_READTIMEOUT, IMAP_READ_TIMEOUT_SECONDS);
        @imap_timeout(IMAP_WRITETIMEOUT, IMAP_WRITE_TIMEOUT_SECONDS);
        @imap_timeout(IMAP_CLOSETIMEOUT, IMAP_CLOSE_TIMEOUT_SECONDS);
    }

    @ini_set('default_socket_timeout', (string) max(IMAP_OPEN_TIMEOUT_SECONDS, IMAP_READ_TIMEOUT_SECONDS, IMAP_WRITE_TIMEOUT_SECONDS));
}

/**
 * @param resource|\IMAP\Connection $imap
 */
function messageTargetsAccountEmail($imap, int $messageNumber, string $accountEmail, string $plainTextBody = '', ?string $htmlBody = null): bool
{
    $rawHeaders = imap_fetchheader($imap, $messageNumber);

    if ($rawHeaders === false) {
        throw new RuntimeException(getImapError('No fue posible leer los encabezados del correo.'));
    }

    $normalizedHeaders = preg_replace("/\r?\n[ \t]+/", ' ', $rawHeaders) ?? $rawHeaders;
    $quotedEmail = preg_quote($accountEmail, '/');
    $recipientHeaderPattern = implode('|', [
        'to',
        'cc',
        'bcc',
        'delivered-to',
        'envelope-to',
        'x-original-to',
        'resent-to',
    ]);

    if (preg_match('/^(?:' . $recipientHeaderPattern . '):.*' . $quotedEmail . '/im', $normalizedHeaders) === 1) {
        return true;
    }

    $searchBodies = [];

    if ($plainTextBody !== '') {
        $searchBodies[] = $plainTextBody;
    }

    if ($htmlBody !== null && $htmlBody !== '') {
        $searchBodies[] = html_entity_decode(strip_tags($htmlBody), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $searchBodies[] = $htmlBody;
    }

    foreach ($searchBodies as $bodyContent) {
        if (stripos($bodyContent, $accountEmail) !== false) {
            return true;
        }
    }

    return false;
}

function parseImapOverviewDate(string $date): ?DateTimeImmutable
{
    if ($date === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($date);
    } catch (Throwable) {
        return null;
    }
}

function decodeMimeHeaderString(string $value): string
{
    if ($value === '') {
        return '';
    }

    $decoded = imap_mime_header_decode($value);
    $output = '';

    foreach ($decoded as $part) {
        $output .= convertToUtf8((string) ($part->text ?? ''), (string) ($part->charset ?? 'UTF-8'));
    }

    return trim($output);
}

/**
 * @param resource|\IMAP\Connection $imap
 */
function getHtmlBody($imap, int $messageNumber): ?string
{
    $structure = imap_fetchstructure($imap, $messageNumber);

    if ($structure === false) {
        throw new RuntimeException(getImapError('No fue posible leer la estructura del correo.'));
    }

    return extractHtmlPart($imap, $messageNumber, $structure);
}

/**
 * @param resource|\IMAP\Connection $imap
 */
function getPlainTextBody($imap, int $messageNumber): string
{
    $structure = imap_fetchstructure($imap, $messageNumber);

    if ($structure === false) {
        throw new RuntimeException(getImapError('No fue posible leer la estructura del correo.'));
    }

    $plainText = extractPlainTextPart($imap, $messageNumber, $structure);

    if ($plainText !== null) {
        return $plainText;
    }

    $html = extractHtmlPart($imap, $messageNumber, $structure);

    return $html !== null ? html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
}

/**
 * @param resource|\IMAP\Connection $imap
 */
function extractPlainTextPart($imap, int $messageNumber, object $structure, string $partNumber = ''): ?string
{
    if (($structure->type ?? null) === TYPETEXT && strtoupper((string) ($structure->subtype ?? '')) === 'PLAIN' && !isAttachment($structure)) {
        return fetchPartBody($imap, $messageNumber, $structure, $partNumber);
    }

    if (!isset($structure->parts) || !is_array($structure->parts)) {
        return null;
    }

    foreach ($structure->parts as $index => $part) {
        $nestedPartNumber = $partNumber === '' ? (string) ($index + 1) : $partNumber . '.' . ($index + 1);
        $plainText = extractPlainTextPart($imap, $messageNumber, $part, $nestedPartNumber);

        if ($plainText !== null) {
            return $plainText;
        }
    }

    return null;
}

/**
 * @param resource|\IMAP\Connection $imap
 */
function extractHtmlPart($imap, int $messageNumber, object $structure, string $partNumber = ''): ?string
{
    if (($structure->type ?? null) === TYPETEXT && strtoupper((string) ($structure->subtype ?? '')) === 'HTML' && !isAttachment($structure)) {
        return fetchPartBody($imap, $messageNumber, $structure, $partNumber);
    }

    if (!isset($structure->parts) || !is_array($structure->parts)) {
        return null;
    }

    foreach ($structure->parts as $index => $part) {
        $nestedPartNumber = $partNumber === '' ? (string) ($index + 1) : $partNumber . '.' . ($index + 1);
        $htmlText = extractHtmlPart($imap, $messageNumber, $part, $nestedPartNumber);

        if ($htmlText !== null) {
            return $htmlText;
        }
    }

    return null;
}

/**
 * @param resource|\IMAP\Connection $imap
 */
function fetchPartBody($imap, int $messageNumber, object $structure, string $partNumber = ''): string
{
    $rawBody = $partNumber === ''
        ? imap_body($imap, $messageNumber, FT_PEEK)
        : imap_fetchbody($imap, $messageNumber, $partNumber, FT_PEEK);

    if ($rawBody === false) {
        throw new RuntimeException(getImapError('No fue posible leer el cuerpo del correo.'));
    }

    $decodedBody = decodeTransferEncoding($rawBody, (int) ($structure->encoding ?? ENC7BIT));
    $charset = detectCharset($structure);

    return convertToUtf8($decodedBody, $charset);
}

function isAttachment(object $structure): bool
{
    foreach (['ifdisposition' => 'disposition', 'ifparameters' => 'parameters', 'ifdparameters' => 'dparameters'] as $flag => $property) {
        if (!empty($structure->{$flag}) && isset($structure->{$property}) && is_array($structure->{$property})) {
            foreach ($structure->{$property} as $parameter) {
                $attribute = strtoupper((string) ($parameter->attribute ?? ''));

                if (in_array($attribute, ['NAME', 'FILENAME'], true)) {
                    return true;
                }
            }
        }
    }

    return strtoupper((string) ($structure->disposition ?? '')) === 'ATTACHMENT';
}

function decodeTransferEncoding(string $rawBody, int $encoding): string
{
    return match ($encoding) {
        ENCBASE64 => base64_decode($rawBody, true) ?: '',
        ENCQUOTEDPRINTABLE => quoted_printable_decode($rawBody),
        default => $rawBody,
    };
}

function detectCharset(object $structure): string
{
    $parameters = [];

    if (!empty($structure->ifparameters) && isset($structure->parameters) && is_array($structure->parameters)) {
        $parameters = array_merge($parameters, $structure->parameters);
    }

    if (!empty($structure->ifdparameters) && isset($structure->dparameters) && is_array($structure->dparameters)) {
        $parameters = array_merge($parameters, $structure->dparameters);
    }

    foreach ($parameters as $parameter) {
        if (strtoupper((string) ($parameter->attribute ?? '')) === 'CHARSET') {
            return (string) $parameter->value;
        }
    }

    return 'UTF-8';
}

function convertToUtf8(string $text, string $charset): string
{
    $normalizedCharset = strtoupper(trim($charset));

    if ($normalizedCharset === '' || in_array($normalizedCharset, ['DEFAULT', 'UTF-8', 'US-ASCII'], true)) {
        return $text;
    }

    if (function_exists('iconv')) {
        $converted = @iconv($normalizedCharset, 'UTF-8//IGNORE', $text);

        if ($converted !== false) {
            return $converted;
        }
    }

    return $text;
}

function sanitizeHtmlBody(string $html): string
{
    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        return nl2br(escapeHtmlFragment(strip_tags($html)));
    }

    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($document);
    foreach (['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'base'] as $tagName) {
        foreach ($xpath->query('//' . $tagName) ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    foreach ($xpath->query('//@*') ?: [] as $attribute) {
        $attributeName = strtolower($attribute->nodeName);
        $attributeValue = strtolower(trim($attribute->nodeValue));

        if (str_starts_with($attributeName, 'on') || str_starts_with($attributeValue, 'javascript:')) {
            $attribute->ownerElement?->removeAttributeNode($attribute);
        }
    }

    return $document->saveHTML() ?: '';
}

function escapeHtmlFragment(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function buildMessagePreview(string $plainTextBody, string $htmlBody): string
{
    $baseText = trim($plainTextBody);

    if ($baseText === '') {
        $baseText = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    if ($baseText === '') {
        return '[sin vista previa]';
    }

    return mb_strimwidth(preg_replace('/\s+/', ' ', $baseText) ?? $baseText, 0, 180, '...');
}

function getImapError(string $fallbackMessage): string
{
    $lastError = imap_last_error();

    return $lastError !== false && $lastError !== '' ? $lastError : $fallbackMessage;
}