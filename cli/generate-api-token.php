<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos.');
}

require_once __DIR__ . '/../includes/admin.php';

$email = trim((string) ($argv[1] ?? ''));

if ($email === '') {
    fwrite(STDERR, "Uso: php generate-api-token.php correo_del_revendedor@ejemplo.com\n");
    exit(1);
}

$pdo = getPdo();
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND role = 'usuario' LIMIT 1");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if ($user === false) {
    fwrite(STDERR, "No existe un usuario con ese correo.\n");
    exit(1);
}

$result = generateRevendedorApiToken($pdo, (int) $user['id']);

if (!$result['success']) {
    fwrite(STDERR, $result['message'] . "\n");
    exit(1);
}

echo "Revendedor: {$result['reseller_email']}\n";
echo "Token (guardalo ahora, no se puede volver a ver despues): {$result['token']}\n";
