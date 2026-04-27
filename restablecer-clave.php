<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/password_reset.php';

$publicAppSettings = getPublicAppConfiguration();
$pageName = trim((string) ($publicAppSettings['nombre_pagina'] ?? '')) !== '' ? (string) $publicAppSettings['nombre_pagina'] : 'Prycorreos';
$pageLogo = $publicAppSettings['logo_url'] ?? null;
$selector = trim((string) ($_GET['selector'] ?? $_POST['selector'] ?? ''));
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$viewState = getPasswordResetViewState($selector, $token);
$resultMessage = '';
$resultTone = 'secondary';
$resultCredentials = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = completePasswordReset($_POST, $_SERVER);
        $resultMessage = (string) ($result['message'] ?? 'La clave fue actualizada correctamente.');
        $resultTone = $result['success'] ? 'success' : 'danger';
        if (!empty($result['login_username']) && !empty($result['new_password'])) {
            $resultCredentials = [
                'username' => (string) $result['login_username'],
                'password' => (string) $result['new_password'],
            ];
        }
        if ($result['success']) {
            $viewState['valid'] = false;
            $viewState['message'] = 'Ya puedes regresar al login con tu nueva clave.';
        }
    } catch (Throwable $exception) {
        $resultMessage = 'No fue posible actualizar la clave.';
        $resultTone = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageName, ENT_QUOTES, 'UTF-8') ?> | Restablecer clave</title>
    <link rel="icon" href="<?= htmlspecialchars((string) ($pageLogo ?? 'data:,'), ENT_QUOTES, 'UTF-8') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(11, 87, 208, 0.16), transparent 28%),
                linear-gradient(180deg, #f8fbff 0%, #eef3f8 100%);
            color: #18212f;
        }

        .reset-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .reset-card {
            width: min(100%, 620px);
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid #dbe5f2;
            border-radius: 1.5rem;
            box-shadow: 0 28px 60px rgba(24, 33, 47, 0.14);
        }

        .reset-brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .reset-brand-mark {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            overflow: hidden;
            background: rgba(11, 87, 208, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dbe5f2;
            font-weight: 800;
            color: #0b57d0;
        }

        .reset-brand-mark img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
    </style>
</head>
<body>
<div class="reset-shell">
    <section class="reset-card p-4 p-lg-5">
        <div class="reset-brand">
            <div class="reset-brand-mark">
                <?php if ($pageLogo): ?>
                    <img src="<?= htmlspecialchars((string) $pageLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo">
                <?php else: ?>
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($pageName, 0, 1), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="text-primary fw-semibold">Recuperación</div>
                <h1 class="h3 mb-0"><?= htmlspecialchars($pageName, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
        </div>

        <?php if ($resultMessage !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($resultTone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($resultMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($resultCredentials !== null): ?>
            <div class="alert alert-warning">
                <div><strong>Usuario:</strong> <?= htmlspecialchars($resultCredentials['username'], ENT_QUOTES, 'UTF-8') ?></div>
                <div><strong>Nueva clave:</strong> <?= htmlspecialchars($resultCredentials['password'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($viewState['valid'])): ?>
            <p class="text-secondary mb-4">Crea una nueva clave para la cuenta <strong><?= htmlspecialchars((string) ($viewState['user']['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>.</p>

            <form method="post" class="row g-3">
                <input type="hidden" name="selector" value="<?= htmlspecialchars($selector, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="col-12">
                    <label class="form-label" for="resetUsername">Usuario</label>
                    <input class="form-control" type="text" id="resetUsername" value="<?= htmlspecialchars((string) ($viewState['user']['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly>
                </div>
                <div class="col-12">
                    <label class="form-label" for="resetPassword">Nueva clave</label>
                    <input class="form-control" type="password" id="resetPassword" name="password" placeholder="Escribe tu nueva clave" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="resetPasswordConfirmation">Confirmar nueva clave</label>
                    <input class="form-control" type="password" id="resetPasswordConfirmation" name="password_confirmation" placeholder="Repite la nueva clave" required>
                </div>
                <div class="col-12 d-grid gap-2">
                    <button class="btn btn-primary btn-lg" type="submit">Guardar nueva clave</button>
                    <a class="btn btn-outline-secondary" href="./index.php">Volver al login</a>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning"><?= htmlspecialchars((string) ($viewState['message'] ?? 'El enlace de recuperación ya no está disponible.'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="d-grid">
                <a class="btn btn-primary" href="./index.php">Volver al login</a>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>