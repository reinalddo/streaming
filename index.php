<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prycorreos | Acceso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --pc-bg: #eef3f8;
            --pc-surface: rgba(255, 255, 255, 0.96);
            --pc-primary: #0b57d0;
            --pc-primary-dark: #093b8f;
            --pc-text: #18212f;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(11, 87, 208, 0.14), transparent 28%),
                linear-gradient(180deg, #f8fbff 0%, var(--pc-bg) 100%);
            color: var(--pc-text);
        }

        .auth-shell {
            min-height: 100vh;
            padding: 1.25rem;
        }

        .auth-container {
            width: 100%;
            max-width: 760px;
        }

        .auth-card {
            border: 0;
            border-radius: 1.5rem;
            background: var(--pc-surface);
            box-shadow: 0 22px 50px rgba(24, 33, 47, 0.12);
            backdrop-filter: blur(6px);
        }

        .nav-pills .nav-link {
            border-radius: 999px;
            color: var(--pc-primary-dark);
            font-weight: 600;
        }

        .nav-pills .nav-link.active {
            background: var(--pc-primary);
        }

        .btn-primary {
            background-color: var(--pc-primary);
            border-color: var(--pc-primary);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background-color: var(--pc-primary-dark);
            border-color: var(--pc-primary-dark);
        }

        .form-control,
        .form-control:focus {
            border-radius: 0.95rem;
            padding: 0.85rem 1rem;
            box-shadow: none;
        }

        .form-control:focus {
            border-color: rgba(11, 87, 208, 0.45);
        }

        .form-label {
            font-size: 0.92rem;
            font-weight: 600;
        }

        .password-field {
            position: relative;
        }

        .password-field .form-control {
            padding-right: 3.5rem;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 0.75rem;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #6c757d;
            padding: 0.25rem;
            line-height: 1;
            z-index: 3;
        }

        .password-toggle:hover,
        .password-toggle:focus {
            color: var(--pc-primary-dark);
        }

        @media (min-width: 992px) {
            .auth-shell {
                padding: 2rem;
            }

            .auth-card {
                min-height: 680px;
            }
        }
    </style>
</head>
<body>
<main class="auth-shell d-flex align-items-center justify-content-center">
    <div class="container auth-container">
        <section class="card auth-card">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-primary fw-semibold mb-1">Inicio</p>
                        <h1 class="h3 mb-0">Ingresa o crea tu cuenta</h1>
                    </div>
                    <div id="statusMessage" class="small text-secondary"></div>
                </div>

                <ul class="nav nav-pills nav-fill gap-2 mb-4" id="authTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="pill" data-bs-target="#login-pane" type="button" role="tab" aria-controls="login-pane" aria-selected="true">Iniciar sesion</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="register-tab" data-bs-toggle="pill" data-bs-target="#register-pane" type="button" role="tab" aria-controls="register-pane" aria-selected="false">Registrarse</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="login-pane" role="tabpanel" aria-labelledby="login-tab" tabindex="0">
                        <form id="loginForm" class="row g-3" novalidate>
                            <div class="col-12">
                                <label class="form-label" for="loginIdentifier">Usuario o correo electronico</label>
                                <input class="form-control" type="text" id="loginIdentifier" name="login" placeholder="usuario o correo registrado" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="loginPassword">Clave</label>
                                <div class="password-field">
                                    <input class="form-control" type="password" id="loginPassword" name="password" placeholder="Ingresa tu clave" required>
                                    <button class="password-toggle" type="button" data-password-target="loginPassword" aria-label="Mostrar clave">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 d-grid">
                                <button class="btn btn-primary btn-lg" type="submit">Entrar</button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="register-pane" role="tabpanel" aria-labelledby="register-tab" tabindex="0">
                        <form id="registerForm" class="row g-3" novalidate>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="registerNombre">Nombre</label>
                                <input class="form-control" type="text" id="registerNombre" name="nombre" placeholder="Tu nombre" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="registerApellido">Apellido</label>
                                <input class="form-control" type="text" id="registerApellido" name="apellido" placeholder="Tu apellido" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="registerUsername">Usuario</label>
                                <input class="form-control" type="text" id="registerUsername" name="username" placeholder="usuario123" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="registerTelefono">Telefono</label>
                                <input class="form-control" type="text" id="registerTelefono" name="telefono" placeholder="Opcional">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="registerEmail">Correo electronico</label>
                                <input class="form-control" type="email" id="registerEmail" name="email" placeholder="usuario@dominio.com" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="registerPassword">Clave</label>
                                <div class="password-field">
                                    <input class="form-control" type="password" id="registerPassword" name="password" placeholder="Minimo 6 caracteres" required>
                                    <button class="password-toggle" type="button" data-password-target="registerPassword" aria-label="Mostrar clave">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 d-grid">
                                <button class="btn btn-primary btn-lg" type="submit">Crear cuenta</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
    const statusMessage = document.getElementById('statusMessage');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const passwordToggleButtons = document.querySelectorAll('[data-password-target]');

    function showStatus(message, tone = 'secondary') {
        statusMessage.className = `small text-${tone}`;
        statusMessage.textContent = message;
    }

    async function submitForm(url, form) {
        const formData = new FormData(form);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
        });

        return response.json();
    }

    passwordToggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.passwordTarget);

            if (!target) {
                return;
            }

            const icon = button.querySelector('i');
            const isPassword = target.type === 'password';

            target.type = isPassword ? 'text' : 'password';
            button.setAttribute('aria-label', isPassword ? 'Ocultar clave' : 'Mostrar clave');

            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    });

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showStatus('Validando usuario...', 'secondary');

        try {
            const result = await submitForm('./api/login.php', loginForm);

            if (result.exists) {
                console.log('Usuario Existe');
                showStatus(result.message, 'success');
                return;
            }

            console.log('Usuario No existe');
            showStatus(result.message, 'danger');
        } catch (error) {
            console.error(error);
            showStatus('No fue posible validar el usuario.', 'danger');
        }
    });

    registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showStatus('Registrando usuario...', 'secondary');

        try {
            const result = await submitForm('./api/register.php', registerForm);
            showStatus(result.message, result.success ? 'success' : 'danger');

            if (result.success) {
                registerForm.reset();
            }
        } catch (error) {
            console.error(error);
            showStatus('No fue posible registrar el usuario.', 'danger');
        }
    });
</script>
</body>
</html>