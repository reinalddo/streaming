<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos.');
}

require_once __DIR__ . '/../includes/admin.php';

$result = generateBotCodigosApiToken(getPdo());

echo "Token (guardalo ahora, no se puede volver a ver despues): {$result['token']}\n";
