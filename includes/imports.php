<?php

declare(strict_types=1);

require_once __DIR__ . '/admin.php';

const IMPORT_MAX_REPORTED_ERRORS = 50;
const IMPORT_BATCH_SIZE = 25;

function importPreviewServiceAccounts(array $file): array
{
    requireAdminUser();

    $rows = readImportWorksheetRows($file);
    $expectedHeaders = ['ID Servicio', 'Correo', 'Contraseña', 'Descripción'];
    validateImportHeaders($rows, $expectedHeaders);
    $preparedRows = normalizeImportRows(array_slice($rows, 1), count($expectedHeaders));

    if ($preparedRows === []) {
        return ['success' => false, 'message' => 'El archivo no contiene filas de datos para importar.'];
    }

    $importKey = storePreparedImport([
        'mode' => 'services',
        'headers' => $expectedHeaders,
        'rows' => $preparedRows,
    ]);

    return [
        'success' => true,
        'message' => 'Archivo validado correctamente. La importación puede comenzar.',
        'import_key' => $importKey,
        'total_rows' => count($preparedRows),
        'expected_headers' => $expectedHeaders,
    ];
}

function importPreviewRegisteredUsers(array $file): array
{
    requireAdminUser();

    $rows = readImportWorksheetRows($file);
    $expectedHeaders = ['Nombre', 'Usuario', 'Correo', 'Clave', 'Teléfono'];
    validateImportHeaders($rows, $expectedHeaders);
    $preparedRows = normalizeImportRows(array_slice($rows, 1), count($expectedHeaders));

    if ($preparedRows === []) {
        return ['success' => false, 'message' => 'El archivo no contiene filas de datos para importar.'];
    }

    $importKey = storePreparedImport([
        'mode' => 'users',
        'headers' => $expectedHeaders,
        'rows' => $preparedRows,
    ]);

    return [
        'success' => true,
        'message' => 'Archivo validado correctamente. La importación puede comenzar.',
        'import_key' => $importKey,
        'total_rows' => count($preparedRows),
        'expected_headers' => $expectedHeaders,
    ];
}

function processPreparedImport(string $importKey): array
{
    requireAdminUser();

    if (!preg_match('/^[a-f0-9]{32}$/', $importKey)) {
        return ['success' => false, 'message' => 'El identificador de importación no es válido.'];
    }

    $payload = loadPreparedImport($importKey);

    if ($payload === null) {
        return ['success' => false, 'message' => 'La importación ya no está disponible. Vuelve a cargar el archivo.'];
    }

    $totalRows = count($payload['rows'] ?? []);
    $cursor = min((int) ($payload['cursor'] ?? 0), $totalRows);

    if ($cursor >= $totalRows) {
        return finalizePreparedImport($importKey, $payload);
    }

    $pdo = getPdo();
    $start = $cursor;
    $end = min($cursor + IMPORT_BATCH_SIZE, $totalRows);

    $accountStatement = null;
    $serviceLookupStatement = null;
    $accountLookupStatement = null;
    $accountUpdateStatement = null;
    $userDuplicateStatement = null;
    $userInsertStatement = null;

    if (($payload['mode'] ?? '') === 'services') {
        $serviceLookupStatement = $pdo->prepare('SELECT id FROM servicios WHERE id = :id LIMIT 1');
        $accountStatement = $pdo->prepare('INSERT INTO cuentas_servicio (servicio_id, correo_acceso, password_acceso, descripcion, activo) VALUES (:servicio_id, :correo_acceso, :password_acceso, :descripcion, :activo)');
        $accountLookupStatement = $pdo->prepare('SELECT id FROM cuentas_servicio WHERE servicio_id = :servicio_id AND correo_acceso = :correo_acceso LIMIT 1');
        $accountUpdateStatement = $pdo->prepare('UPDATE cuentas_servicio SET password_acceso = :password_acceso, descripcion = :descripcion, activo = :activo WHERE id = :id');
    }

    if (($payload['mode'] ?? '') === 'users') {
        $userDuplicateStatement = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email OR username = :username LIMIT 1');
        $userInsertStatement = $pdo->prepare('INSERT INTO usuarios (nombre, apellido, username, email, telefono, password_hash, role, activo) VALUES (:nombre, :apellido, :username, :email, :telefono, :password_hash, :role, :activo)');
    }

    for ($index = $start; $index < $end; $index++) {
        $row = $payload['rows'][$index] ?? [];
        $excelRowNumber = $index + 2;

        if (($payload['mode'] ?? '') === 'services') {
            processPreparedServiceImportRow($payload, $row, $excelRowNumber, $serviceLookupStatement, $accountLookupStatement, $accountUpdateStatement, $accountStatement);
        } elseif (($payload['mode'] ?? '') === 'users') {
            processPreparedUserImportRow($payload, $row, $excelRowNumber, $userDuplicateStatement, $userInsertStatement);
        } else {
            return ['success' => false, 'message' => 'El tipo de importación no es compatible.'];
        }
    }

    $payload['cursor'] = $end;
    savePreparedImport($importKey, $payload);

    if ($end >= $totalRows) {
        return finalizePreparedImport($importKey, $payload);
    }

    return buildPreparedImportResponse($payload, false, sprintf('Procesando registros... %d de %d completados.', $end, $totalRows));
}

function readImportWorksheetRows(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No fue posible recibir el archivo XLSX para importar.');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension !== 'xlsx') {
        throw new RuntimeException('Solo se permiten archivos con extensión .xlsx.');
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');

    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new RuntimeException('No fue posible validar el archivo subido.');
    }

    $zip = new ZipArchive();
    if ($zip->open($temporaryPath) !== true) {
        throw new RuntimeException('No fue posible abrir el archivo XLSX.');
    }

    try {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $workbookRelsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $workbookRelsXml === false) {
            throw new RuntimeException('El archivo XLSX no contiene la estructura esperada del libro.');
        }

        $workbook = parseImportXmlDocument($workbookXml, 'No fue posible leer el libro del archivo XLSX.');
        $relationships = parseImportXmlDocument($workbookRelsXml, 'No fue posible leer las relaciones del archivo XLSX.');
        $sheetPath = resolveFirstWorksheetPath($workbook, $relationships);
        $sheetXml = $zip->getFromName($sheetPath);

        if ($sheetXml === false) {
            throw new RuntimeException('No fue posible ubicar la hoja principal del archivo XLSX.');
        }

        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $sharedStrings = $sharedStringsXml === false
            ? []
            : parseSharedStrings(parseImportXmlDocument($sharedStringsXml, 'No fue posible leer las cadenas compartidas del archivo XLSX.'));
        $sheet = parseImportXmlDocument($sheetXml, 'No fue posible leer la hoja del archivo XLSX.');

        return parseWorksheetRows($sheet, $sharedStrings);
    } finally {
        $zip->close();
    }
}

function parseImportXmlDocument(string $xml, string $errorMessage): SimpleXMLElement
{
    libxml_use_internal_errors(true);
    $document = simplexml_load_string($xml);

    if (!$document instanceof SimpleXMLElement) {
        libxml_clear_errors();
        throw new RuntimeException($errorMessage);
    }

    return $document;
}

function resolveFirstWorksheetPath(SimpleXMLElement $workbook, SimpleXMLElement $relationships): string
{
    if (!isset($workbook->sheets->sheet[0])) {
        throw new RuntimeException('El archivo XLSX no contiene hojas disponibles.');
    }

    $sheetAttributes = $workbook->sheets->sheet[0]->attributes('r', true);
    $relationshipId = (string) ($sheetAttributes['id'] ?? '');

    if ($relationshipId === '') {
        throw new RuntimeException('No fue posible identificar la hoja principal del archivo XLSX.');
    }

    foreach ($relationships->Relationship as $relationship) {
        $attributes = $relationship->attributes();

        if ((string) ($attributes['Id'] ?? '') !== $relationshipId) {
            continue;
        }

        $target = (string) ($attributes['Target'] ?? '');

        if ($target === '') {
            break;
        }

        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        return 'xl/' . ltrim($target, '/');
    }

    throw new RuntimeException('No fue posible ubicar la hoja principal del archivo XLSX.');
}

function parseSharedStrings(SimpleXMLElement $sharedStrings): array
{
    $namespace = $sharedStrings->getNamespaces(true)[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $result = [];

    foreach ($sharedStrings->children($namespace)->si as $item) {
        $result[] = extractImportInlineString($item, $namespace);
    }

    return $result;
}

function parseWorksheetRows(SimpleXMLElement $sheet, array $sharedStrings): array
{
    $namespace = $sheet->getNamespaces(true)[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $sheetData = $sheet->children($namespace)->sheetData;
    $rows = [];

    if (!$sheetData instanceof SimpleXMLElement) {
        return $rows;
    }

    foreach ($sheetData->row as $row) {
        $rowValues = [];

        foreach ($row->c as $cell) {
            $attributes = $cell->attributes();
            $reference = (string) ($attributes['r'] ?? '');
            $columnIndex = convertImportColumnReferenceToIndex($reference);

            if ($columnIndex < 1) {
                continue;
            }

            $rowValues[$columnIndex] = parseWorksheetCellValue($cell, $sharedStrings, $namespace);
        }

        if ($rowValues === []) {
            $rows[] = [];
            continue;
        }

        ksort($rowValues);
        $maxColumn = max(array_keys($rowValues));
        $orderedRow = [];

        for ($column = 1; $column <= $maxColumn; $column++) {
            $orderedRow[] = trim((string) ($rowValues[$column] ?? ''));
        }

        $rows[] = $orderedRow;
    }

    return $rows;
}

function parseWorksheetCellValue(SimpleXMLElement $cell, array $sharedStrings, string $namespace): string
{
    $attributes = $cell->attributes();
    $type = (string) ($attributes['t'] ?? '');

    if ($type === 'inlineStr') {
        return extractImportInlineString($cell->children($namespace)->is, $namespace);
    }

    $value = (string) ($cell->children($namespace)->v ?? '');

    if ($type === 's') {
        $index = (int) $value;
        return (string) ($sharedStrings[$index] ?? '');
    }

    if ($type === 'b') {
        return $value === '1' ? 'TRUE' : 'FALSE';
    }

    return $value;
}

function extractImportInlineString(?SimpleXMLElement $node, string $namespace): string
{
    if (!$node instanceof SimpleXMLElement) {
        return '';
    }

    $text = '';

    foreach ($node->children($namespace)->t as $textNode) {
        $text .= (string) $textNode;
    }

    foreach ($node->children($namespace)->r as $runNode) {
        $text .= (string) ($runNode->children($namespace)->t ?? '');
    }

    if ($text !== '') {
        return $text;
    }

    return trim((string) $node);
}

function convertImportColumnReferenceToIndex(string $reference): int
{
    if (!preg_match('/^([A-Z]+)\d+$/i', $reference, $matches)) {
        return 0;
    }

    $letters = strtoupper($matches[1]);
    $index = 0;

    for ($offset = 0, $length = strlen($letters); $offset < $length; $offset++) {
        $index = ($index * 26) + (ord($letters[$offset]) - 64);
    }

    return $index;
}

function validateImportHeaders(array $rows, array $expectedHeaders): void
{
    if ($rows === [] || !isset($rows[0])) {
        throw new RuntimeException('El archivo XLSX está vacío o no contiene encabezados.');
    }

    $receivedHeaders = array_slice(array_map('trim', $rows[0]), 0, count($expectedHeaders));

    if (count($receivedHeaders) < count($expectedHeaders)) {
        throw new RuntimeException('El archivo XLSX no contiene todas las columnas requeridas en el encabezado.');
    }

    foreach ($expectedHeaders as $index => $expectedHeader) {
        $receivedHeader = $receivedHeaders[$index] ?? '';

        if (normalizeImportHeaderName($receivedHeader) !== normalizeImportHeaderName($expectedHeader)) {
            throw new RuntimeException(sprintf(
                'El encabezado del archivo no coincide. Se esperaba: %s',
                implode(' | ', $expectedHeaders)
            ));
        }
    }
}

function normalizeImportHeaderName(string $value): string
{
    return mb_strtolower(trim($value));
}

function normalizeImportRows(array $rows, int $columnCount): array
{
    $normalized = [];

    foreach ($rows as $row) {
        $values = [];

        for ($index = 0; $index < $columnCount; $index++) {
            $values[] = trim((string) ($row[$index] ?? ''));
        }

        if (implode('', $values) === '') {
            continue;
        }

        $normalized[] = $values;
    }

    return $normalized;
}

function fetchImportServiceById(int $serviceId): ?array
{
    $pdo = getPdo();
    $statement = $pdo->prepare('SELECT id, nombre FROM servicios WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $serviceId]);
    $service = $statement->fetch();

    return $service === false ? null : $service;
}

function storePreparedImport(array $payload): string
{
    $key = bin2hex(random_bytes(16));
    $payload['cursor'] = 0;
    $payload['inserted_count'] = 0;
    $payload['updated_count'] = 0;
    $payload['skipped_existing_count'] = 0;
    $payload['error_count'] = 0;
    $payload['notices'] = [];
    $payload['errors'] = [];
    $payload['created_at'] = time();

    savePreparedImport($key, $payload);

    if (!isset($_SESSION['prepared_imports']) || !is_array($_SESSION['prepared_imports'])) {
        $_SESSION['prepared_imports'] = [];
    }

    $_SESSION['prepared_imports'][$key] = [
        'path' => getPreparedImportPath($key),
        'mode' => $payload['mode'] ?? '',
    ];

    return $key;
}

function savePreparedImport(string $key, array $payload): void
{
    $path = getPreparedImportPath($key);
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('No fue posible preparar el almacenamiento temporal de la importación.');
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($encoded === false || file_put_contents($path, $encoded, LOCK_EX) === false) {
        throw new RuntimeException('No fue posible guardar el estado temporal de la importación.');
    }
}

function loadPreparedImport(string $key): ?array
{
    $path = getPreparedImportPath($key);

    if (!is_file($path)) {
        return null;
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('No fue posible leer el estado temporal de la importación.');
    }

    $payload = json_decode($contents, true);

    if (!is_array($payload)) {
        throw new RuntimeException('El estado temporal de la importación no es válido.');
    }

    return $payload;
}

function finalizePreparedImport(string $key, array $payload): array
{
    deletePreparedImport($key);

    return buildPreparedImportResponse($payload, true, buildPreparedImportMessage($payload));
}

function deletePreparedImport(string $key): void
{
    $path = getPreparedImportPath($key);

    if (is_file($path)) {
        @unlink($path);
    }

    if (isset($_SESSION['prepared_imports'][$key])) {
        unset($_SESSION['prepared_imports'][$key]);
    }
}

function getPreparedImportPath(string $key): string
{
    return rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'prycorreos_imports' . DIRECTORY_SEPARATOR . $key . '.json';
}

function processPreparedServiceImportRow(
    array &$payload,
    array $row,
    int $excelRowNumber,
    PDOStatement $serviceLookupStatement,
    PDOStatement $lookupStatement,
    PDOStatement $updateStatement,
    PDOStatement $insertStatement
): void
{
    $serviceId = (int) ($row[0] ?? 0);
    $email = strtolower(trim((string) ($row[1] ?? '')));
    $password = trim((string) ($row[2] ?? ''));
    $description = trim((string) ($row[3] ?? ''));

    if ($serviceId <= 0) {
        registerPreparedImportError($payload, $excelRowNumber, 'El ID del servicio no es válido.');
        return;
    }

    $serviceLookupStatement->execute(['id' => $serviceId]);
    if ($serviceLookupStatement->fetch() === false) {
        registerPreparedImportError($payload, $excelRowNumber, sprintf('El servicio con ID %d no existe.', $serviceId));
        return;
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        registerPreparedImportError($payload, $excelRowNumber, 'El correo de acceso no es válido.');
        return;
    }

    if ($password === '') {
        registerPreparedImportError($payload, $excelRowNumber, 'La contraseña de la cuenta no puede estar vacía.');
        return;
    }

    $lookupStatement->execute([
        'servicio_id' => $serviceId,
        'correo_acceso' => $email,
    ]);
    $existingAccount = $lookupStatement->fetch();

    if ($existingAccount !== false) {
        $updateStatement->execute([
            'password_acceso' => $password,
            'descripcion' => $description !== '' ? $description : null,
            'activo' => 1,
            'id' => (int) $existingAccount['id'],
        ]);
        $payload['updated_count'] = (int) ($payload['updated_count'] ?? 0) + 1;
        registerPreparedImportNotice($payload, $excelRowNumber, sprintf('La cuenta %s ya existía en el servicio %d y fue actualizada.', $email, $serviceId));
        return;
    }

    try {
        $insertStatement->execute([
            'servicio_id' => $serviceId,
            'correo_acceso' => $email,
            'password_acceso' => $password,
            'descripcion' => $description !== '' ? $description : null,
            'activo' => 1,
        ]);
        $payload['inserted_count'] = (int) ($payload['inserted_count'] ?? 0) + 1;
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            registerPreparedImportError($payload, $excelRowNumber, 'No fue posible registrar la cuenta porque ya existe un conflicto con el servicio y el correo indicados.');
            return;
        }

        throw $exception;
    }
}

function processPreparedUserImportRow(array &$payload, array $row, int $excelRowNumber, PDOStatement $duplicateStatement, PDOStatement $insertStatement): void
{
    $fullName = trim((string) ($row[0] ?? ''));
    $username = trim((string) ($row[1] ?? ''));
    $email = strtolower(trim((string) ($row[2] ?? '')));
    $password = trim((string) ($row[3] ?? ''));
    $phone = trim((string) ($row[4] ?? ''));

    if ($fullName === '' || $username === '' || $email === '' || $password === '') {
        registerPreparedImportError($payload, $excelRowNumber, 'Nombre, usuario, correo y clave son obligatorios.');
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        registerPreparedImportError($payload, $excelRowNumber, 'El correo del usuario no es válido.');
        return;
    }

    if (mb_strlen($password) < 6) {
        registerPreparedImportError($payload, $excelRowNumber, 'La clave debe tener al menos 6 caracteres.');
        return;
    }

    $nameParts = splitImportedFullName($fullName);

    $duplicateStatement->execute([
        'email' => $email,
        'username' => $username,
    ]);

    if ($duplicateStatement->fetch() !== false) {
        $payload['skipped_existing_count'] = (int) ($payload['skipped_existing_count'] ?? 0) + 1;
        registerPreparedImportNotice($payload, $excelRowNumber, 'El correo o usuario ya existe y el registro fue omitido.');
        return;
    }

    $insertStatement->execute([
        'nombre' => $nameParts['nombre'],
        'apellido' => $nameParts['apellido'],
        'username' => $username,
        'email' => $email,
        'telefono' => $phone !== '' ? $phone : null,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'usuario',
        'activo' => 1,
    ]);
    $payload['inserted_count'] = (int) ($payload['inserted_count'] ?? 0) + 1;
}

function splitImportedFullName(string $fullName): array
{
    $normalized = preg_replace('/\s+/u', ' ', trim($fullName));

    if ($normalized === null || $normalized === '') {
        return ['nombre' => '', 'apellido' => ''];
    }

    $parts = preg_split('/\s+/u', $normalized) ?: [];
    $firstName = array_shift($parts) ?? '';
    $lastName = trim(implode(' ', $parts));

    return [
        'nombre' => $firstName,
        'apellido' => $lastName,
    ];
}

function registerPreparedImportError(array &$payload, int $excelRowNumber, string $message): void
{
    $payload['error_count'] = (int) ($payload['error_count'] ?? 0) + 1;

    if (count($payload['errors'] ?? []) >= IMPORT_MAX_REPORTED_ERRORS) {
        return;
    }

    $payload['errors'][] = sprintf('Fila %d: %s', $excelRowNumber, $message);
}

function registerPreparedImportNotice(array &$payload, int $excelRowNumber, string $message): void
{
    if (count($payload['notices'] ?? []) >= IMPORT_MAX_REPORTED_ERRORS) {
        return;
    }

    $payload['notices'][] = sprintf('Fila %d: %s', $excelRowNumber, $message);
}

function buildPreparedImportResponse(array $payload, bool $completed, string $message): array
{
    $totalRows = count($payload['rows'] ?? []);
    $processedCount = min((int) ($payload['cursor'] ?? 0), $totalRows);
    $errorCount = (int) ($payload['error_count'] ?? 0);
    $updatedCount = (int) ($payload['updated_count'] ?? 0);
    $skippedExistingCount = (int) ($payload['skipped_existing_count'] ?? 0);
    $reportedNotices = array_values(array_filter($payload['notices'] ?? [], static fn ($notice): bool => is_string($notice) && trim($notice) !== ''));
    $reportedErrors = array_values(array_filter($payload['errors'] ?? [], static fn ($error): bool => is_string($error) && trim($error) !== ''));
    $hiddenErrors = max(0, $errorCount - count($reportedErrors));

    return [
        'success' => true,
        'completed' => $completed,
        'message' => $message,
        'mode' => (string) ($payload['mode'] ?? ''),
        'total_rows' => $totalRows,
        'processed_count' => $processedCount,
        'inserted_count' => (int) ($payload['inserted_count'] ?? 0),
        'updated_count' => $updatedCount,
        'skipped_existing_count' => $skippedExistingCount,
        'error_count' => $errorCount,
        'notices' => $reportedNotices,
        'errors' => $reportedErrors,
        'hidden_error_count' => $hiddenErrors,
        'selected_service_id' => isset($payload['selected_service_id']) ? (int) $payload['selected_service_id'] : null,
        'selected_service_name' => isset($payload['selected_service_name']) ? (string) $payload['selected_service_name'] : null,
    ];
}

function buildPreparedImportMessage(array $payload): string
{
    $insertedCount = (int) ($payload['inserted_count'] ?? 0);
    $updatedCount = (int) ($payload['updated_count'] ?? 0);
    $skippedExistingCount = (int) ($payload['skipped_existing_count'] ?? 0);
    $errorCount = (int) ($payload['error_count'] ?? 0);
    $mode = (string) ($payload['mode'] ?? '');
    $subject = $mode === 'services' ? 'cuentas del servicio' : 'usuarios registrados';
    $summaryParts = [sprintf('Se registraron %d %s.', $insertedCount, $subject)];

    if ($mode === 'services' && $updatedCount > 0) {
        $summaryParts[] = sprintf('Se actualizaron %d cuenta(s) repetida(s) dentro del mismo servicio.', $updatedCount);
    }

    if ($mode === 'users' && $skippedExistingCount > 0) {
        $summaryParts[] = sprintf('Se omitieron %d registro(s) porque el usuario o correo ya existía.', $skippedExistingCount);
    }

    if ($errorCount > 0) {
        $summaryParts[] = sprintf('%d fila(s) presentaron errores.', $errorCount);
    }

    return 'Importación finalizada. ' . implode(' ', $summaryParts);
}