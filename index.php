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
            --pc-border: #dbe5f2;
            --pc-muted: #64748b;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(11, 87, 208, 0.14), transparent 28%),
                linear-gradient(180deg, #f8fbff 0%, var(--pc-bg) 100%);
            color: var(--pc-text);
        }

        .auth-shell,
        .admin-shell {
            width: 100%;
            padding: 1.25rem;
        }

        .auth-shell {
            min-height: 100vh;
        }

        .auth-container {
            width: 100%;
            max-width: 760px;
        }

        .admin-container {
            width: 100%;
            max-width: 1280px;
        }

        .surface-card {
            border: 0;
            border-radius: 1.5rem;
            background: var(--pc-surface);
            box-shadow: 0 22px 50px rgba(24, 33, 47, 0.12);
            backdrop-filter: blur(6px);
        }

        .dashboard-block {
            border: 1px solid var(--pc-border);
            border-radius: 1.25rem;
            background: #fff;
            padding: 1.15rem;
            height: 100%;
        }

        .section-title {
            font-size: 1.06rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .section-subtitle {
            color: var(--pc-muted);
            font-size: 0.92rem;
            margin-bottom: 1rem;
        }

        .auth-tabs .nav-link,
        .admin-tabs .nav-link {
            border-radius: 999px;
            color: var(--pc-primary-dark);
            font-weight: 600;
        }

        .auth-tabs .nav-link.active,
        .admin-tabs .nav-link.active {
            background: var(--pc-primary);
            color: #fff;
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
        .form-select,
        .form-control:focus,
        .form-select:focus {
            border-radius: 0.95rem;
            padding: 0.85rem 1rem;
            box-shadow: none;
        }

        .form-control:focus,
        .form-select:focus {
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

        .service-card {
            border: 1px solid var(--pc-border);
            border-radius: 1rem;
            padding: 1rem;
            background: #fff;
            height: 100%;
        }

        .service-logo {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            flex-shrink: 0;
        }

        .service-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .service-logo-preview {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid var(--pc-border);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .service-logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .badge-soft {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.28rem 0.7rem;
            background: #eef4ff;
            color: var(--pc-primary-dark);
            font-size: 0.82rem;
            font-weight: 600;
        }

        .admin-identity {
            background: #eef4ff;
            color: var(--pc-primary-dark);
            border-radius: 999px;
            padding: 0.55rem 0.95rem;
            font-weight: 600;
        }

        .empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 1rem;
            background: #fbfdff;
            padding: 1.25rem;
            color: var(--pc-muted);
            text-align: center;
        }

        .data-table-wrapper {
            border: 1px solid var(--pc-border);
            border-radius: 1rem;
            overflow: hidden;
            background: #fff;
        }

        .table thead th {
            background: #f8fbff;
            color: var(--pc-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .inline-card {
            border: 1px solid var(--pc-border);
            border-radius: 1rem;
            padding: 1rem;
            background: #fbfdff;
        }

        .assignment-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .assignment-item {
            border: 1px solid var(--pc-border);
            border-radius: 1rem;
            padding: 0.95rem;
            background: #fff;
        }

        .color-swatch {
            width: 14px;
            height: 14px;
            border-radius: 999px;
            display: inline-block;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .form-control-color {
            width: 100%;
            height: 52px;
            border-radius: 0.95rem;
        }

        .cursor-pointer {
            cursor: pointer;
        }

        .service-accordion-row td {
            padding: 0 !important;
            background: #fbfdff;
        }

        .service-accordion-panel {
            border-top: 1px solid var(--pc-border);
            padding: 1rem 1.1rem 1.25rem;
            background: #fbfdff;
        }

        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .pagination-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .table-action-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .service-table-title {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .service-table-title .service-logo {
            width: 44px;
            height: 44px;
            border-radius: 14px;
        }

        .metric-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.25rem 0.65rem;
            background: #f1f5f9;
            color: #475569;
            font-size: 0.8rem;
            font-weight: 600;
        }

        @media (min-width: 992px) {
            .auth-shell,
            .admin-shell {
                padding: 2rem;
            }

            .auth-card-login {
                min-height: 680px;
            }
        }
    </style>
</head>
<body>
<main>
    <section id="authView" class="auth-shell d-flex align-items-center justify-content-center">
        <div class="container auth-container">
            <section class="card surface-card auth-card-login">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <div>
                            <p class="text-primary fw-semibold mb-1">Inicio</p>
                            <h1 class="h3 mb-0">Ingresa o crea tu cuenta</h1>
                        </div>
                        <div id="statusMessage" class="small text-secondary"></div>
                    </div>

                    <ul class="nav nav-pills nav-fill gap-2 mb-4 auth-tabs" role="tablist">
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
    </section>

    <section id="adminView" class="admin-shell d-none">
        <div class="container admin-container">
            <section class="card surface-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                        <div>
                            <p class="text-primary fw-semibold mb-1">Administracion</p>
                            <h1 class="h3 mb-2">Panel del administrador</h1>
                            <p class="text-secondary mb-0">Organiza servicios, consulta sus cuentas disponibles, administra usuarios registrados y asigna cuentas activas.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                            <div id="adminIdentity" class="admin-identity"></div>
                            <button id="logoutButton" class="btn btn-outline-secondary" type="button">Cerrar sesion</button>
                        </div>
                    </div>

                    <div id="adminStatusMessage" class="small text-secondary mb-4"></div>

                    <ul class="nav nav-pills flex-wrap gap-2 mb-4 admin-tabs" id="adminTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="services-tab" data-bs-toggle="pill" data-bs-target="#services-pane" type="button" role="tab" aria-controls="services-pane" aria-selected="true">Servicios</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="users-tab" data-bs-toggle="pill" data-bs-target="#users-pane" type="button" role="tab" aria-controls="users-pane" aria-selected="false">Usuarios Registrados</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="assignments-tab" data-bs-toggle="pill" data-bs-target="#assignments-pane" type="button" role="tab" aria-controls="assignments-pane" aria-selected="false">Asignar Cuentas</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="services-pane" role="tabpanel" aria-labelledby="services-tab" tabindex="0">
                            <div id="servicesOverviewSection" class="row g-4">
                                <div class="col-12 col-xl-4">
                                    <div class="dashboard-block h-100">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div>
                                                <h2 id="serviceFormHeading" class="section-title">Agregar servicio</h2>
                                                <p id="serviceFormSubtitle" class="section-subtitle mb-0">Crea servicios con nombre, logo local, color destacado y descripcion.</p>
                                            </div>
                                            <button id="cancelServiceEditButton" class="btn btn-sm btn-outline-secondary d-none" type="button">Cancelar</button>
                                        </div>
                                        <form id="serviceForm" class="row g-3" novalidate enctype="multipart/form-data">
                                            <input type="hidden" id="serviceFormAction" name="action" value="create">
                                            <input type="hidden" id="serviceFormServiceId" name="servicio_id" value="">
                                            <div class="col-12">
                                                <label class="form-label" for="serviceName">Nombre del servicio</label>
                                                <input class="form-control" type="text" id="serviceName" name="nombre" placeholder="Netflix" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="serviceLogo">Logo del servicio</label>
                                                <input class="form-control" type="file" id="serviceLogo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
                                                <div class="form-text">Selecciona una imagen desde tu dispositivo. Se guardara en `assets/services`.</div>
                                            </div>
                                            <div id="currentServiceLogoWrapper" class="col-12 d-none">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="service-logo-preview">
                                                        <img id="currentServiceLogoImage" src="" alt="Logo actual del servicio">
                                                    </div>
                                                    <div class="small text-secondary">Logo actual del servicio</div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label" for="serviceColor">Color destacado</label>
                                                <input class="form-control form-control-color" type="color" id="serviceColor" name="color_destacado" value="#0b57d0">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="serviceDescription">Descripcion</label>
                                                <textarea class="form-control" id="serviceDescription" name="descripcion" rows="4" placeholder="Describe el servicio, tipo de plan, notas u observaciones."></textarea>
                                            </div>
                                            <div class="col-12 d-grid">
                                                <button id="serviceSubmitButton" class="btn btn-primary" type="submit">Guardar servicio</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="col-12 col-xl-8">
                                    <div class="dashboard-block h-100">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                            <div>
                                                <h2 class="section-title mb-0">Todos los servicios</h2>
                                                <p class="section-subtitle mb-0">Consulta tus servicios disponibles y abre la vista de cuentas de cada uno.</p>
                                            </div>
                                            <span id="serviceCountBadge" class="badge text-bg-primary rounded-pill"></span>
                                        </div>
                                        <div id="servicesList" class="row g-3"></div>
                                    </div>
                                </div>
                            </div>

                            <div id="serviceAccountsSection" class="d-none">
                                <div class="dashboard-block">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                        <div>
                                            <button id="backToServicesButton" class="btn btn-sm btn-outline-secondary mb-3" type="button">
                                                <i class="bi bi-arrow-left"></i>
                                                Regresar a servicios
                                            </button>
                                            <h2 id="serviceAccountsTitle" class="section-title mb-1"></h2>
                                            <p id="serviceAccountsSubtitle" class="section-subtitle mb-0">Consulta las cuentas registradas para este servicio.</p>
                                        </div>
                                        <button id="toggleCreateAccountButton" class="btn btn-primary" type="button">
                                            <i class="bi bi-plus-circle"></i>
                                            Crear Cuenta
                                        </button>
                                    </div>

                                    <div id="createAccountPanel" class="inline-card d-none mb-4">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                            <h3 id="serviceAccountFormHeading" class="h6 mb-0">Nueva cuenta para este servicio</h3>
                                            <button id="cancelServiceAccountEditButton" class="btn btn-sm btn-outline-secondary d-none" type="button">Cancelar</button>
                                        </div>
                                        <form id="serviceAccountForm" class="row g-3" novalidate>
                                            <input type="hidden" id="serviceAccountAction" name="action" value="create">
                                            <input type="hidden" id="serviceAccountId" name="cuenta_id" value="">
                                            <input type="hidden" id="serviceAccountServiceId" name="servicio_id">
                                            <div class="col-12 col-lg-4">
                                                <label class="form-label" for="serviceAccountEmail">Correo de acceso</label>
                                                <input class="form-control" type="email" id="serviceAccountEmail" name="correo_acceso" placeholder="cuenta@servicio.com" required>
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <label class="form-label" for="serviceAccountPassword">Contrasena</label>
                                                <div class="password-field">
                                                    <input class="form-control" type="password" id="serviceAccountPassword" name="password_acceso" placeholder="Clave de la cuenta" required>
                                                    <button class="password-toggle" type="button" data-password-target="serviceAccountPassword" aria-label="Mostrar clave">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <label class="form-label" for="serviceAccountDescription">Descripcion</label>
                                                <input class="form-control" type="text" id="serviceAccountDescription" name="descripcion" placeholder="Perfil 1, pantalla disponible...">
                                            </div>
                                            <div class="col-12 d-grid d-lg-flex justify-content-lg-end">
                                                <button id="serviceAccountSubmitButton" class="btn btn-primary" type="submit">Guardar cuenta</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="data-table-wrapper">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Correo</th>
                                                        <th>Descripcion</th>
                                                        <th>Contrasena</th>
                                                        <th>Usuarios Asignados</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="serviceAccountsTableBody"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="users-pane" role="tabpanel" aria-labelledby="users-tab" tabindex="0">
                            <div class="dashboard-block">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <div>
                                        <h2 class="section-title mb-0">Usuarios registrados</h2>
                                        <p class="section-subtitle mb-0">El administrador puede registrar usuarios, editarlos, eliminarlos y revisar todas sus cuentas asignadas.</p>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span id="userCountBadge" class="badge text-bg-secondary rounded-pill"></span>
                                        <button id="toggleCreateUserButton" class="btn btn-primary" type="button">
                                            <i class="bi bi-person-plus"></i>
                                            Registrar usuario
                                        </button>
                                    </div>
                                </div>

                                <div id="createUserPanel" class="inline-card d-none mb-4">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                        <h3 class="h6 mb-0">Registrar usuario desde administracion</h3>
                                        <button id="cancelCreateUserButton" class="btn btn-sm btn-outline-secondary" type="button">Cancelar</button>
                                    </div>
                                    <form id="adminCreateUserForm" class="row g-3" novalidate>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label" for="adminCreateUserNombre">Nombre</label>
                                            <input class="form-control" type="text" id="adminCreateUserNombre" name="nombre" required>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label" for="adminCreateUserApellido">Apellido</label>
                                            <input class="form-control" type="text" id="adminCreateUserApellido" name="apellido" required>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-2">
                                            <label class="form-label" for="adminCreateUsername">Usuario</label>
                                            <input class="form-control" type="text" id="adminCreateUsername" name="username" required>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-4">
                                            <label class="form-label" for="adminCreateUserEmail">Correo</label>
                                            <input class="form-control" type="email" id="adminCreateUserEmail" name="email" required>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-4">
                                            <label class="form-label" for="adminCreateUserPhone">Telefono</label>
                                            <input class="form-control" type="text" id="adminCreateUserPhone" name="telefono">
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-4">
                                            <label class="form-label" for="adminCreateUserPassword">Clave</label>
                                            <div class="password-field">
                                                <input class="form-control" type="password" id="adminCreateUserPassword" name="password" placeholder="Minimo 6 caracteres" required>
                                                <button class="password-toggle" type="button" data-password-target="adminCreateUserPassword" aria-label="Mostrar clave">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-12 d-grid d-lg-flex justify-content-lg-end">
                                            <button class="btn btn-primary" type="submit">Guardar usuario</button>
                                        </div>
                                    </form>
                                </div>

                                <div class="data-table-wrapper mb-4">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Nombre</th>
                                                    <th>Usuario</th>
                                                    <th>Correo</th>
                                                    <th>Telefono</th>
                                                    <th>Estado</th>
                                                    <th>Cuentas Asignadas</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="registeredUsersTableBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div id="userEditPanel" class="inline-card d-none">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                        <h3 class="h6 mb-0">Editar usuario</h3>
                                        <button id="cancelUserEditButton" class="btn btn-sm btn-outline-secondary" type="button">Cancelar</button>
                                    </div>
                                    <form id="userEditForm" class="row g-3" novalidate>
                                        <input type="hidden" id="editUserId" name="usuario_id">
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label" for="editUserNombre">Nombre</label>
                                            <input class="form-control" type="text" id="editUserNombre" name="nombre" required>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label" for="editUserApellido">Apellido</label>
                                            <input class="form-control" type="text" id="editUserApellido" name="apellido" required>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-2">
                                            <label class="form-label" for="editUsername">Usuario</label>
                                            <input class="form-control" type="text" id="editUsername" name="username" required>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-4">
                                            <label class="form-label" for="editUserEmail">Correo</label>
                                            <input class="form-control" type="email" id="editUserEmail" name="email" required>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label" for="editUserPhone">Telefono</label>
                                            <input class="form-control" type="text" id="editUserPhone" name="telefono">
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label" for="editUserActive">Estado</label>
                                            <select class="form-select" id="editUserActive" name="activo">
                                                <option value="1">Activo</option>
                                                <option value="0">Inactivo</option>
                                            </select>
                                        </div>
                                        <div class="col-12 d-grid d-lg-flex justify-content-lg-end gap-2">
                                            <button class="btn btn-primary" type="submit">Guardar cambios</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="assignments-pane" role="tabpanel" aria-labelledby="assignments-tab" tabindex="0">
                            <div class="dashboard-block">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <div>
                                        <h2 class="section-title mb-0">Asignar cuentas por servicio</h2>
                                        <p class="section-subtitle mb-0">Revisa los usuarios asignados por servicio y abre un modal para asignarlos a cualquiera de sus cuentas disponibles.</p>
                                    </div>
                                    <span id="assignmentServiceCountBadge" class="badge text-bg-secondary rounded-pill"></span>
                                </div>

                                <div class="data-table-wrapper">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Servicio</th>
                                                    <th>Cuentas</th>
                                                    <th>Usuarios Asignados</th>
                                                    <th>Ver/Asignar</th>
                                                </tr>
                                            </thead>
                                            <tbody id="serviceAssignmentsTableBody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </section>
</main>

<div class="modal fade" id="assignedUsersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 id="assignedUsersModalTitle" class="h5 mb-1">Usuarios asignados</h2>
                    <p id="assignedUsersModalSubtitle" class="small text-secondary mb-0"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="data-table-wrapper">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="assignedUsersTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userAssignmentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 id="userAssignmentsModalTitle" class="h5 mb-1">Cuentas asignadas</h2>
                    <p id="userAssignmentsModalSubtitle" class="small text-secondary mb-0"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="data-table-wrapper">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Servicio</th>
                                    <th>Cuenta</th>
                                    <th>Descripcion</th>
                                    <th>Clave</th>
                                </tr>
                            </thead>
                            <tbody id="userAssignmentsTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="serviceAssignUsersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 id="serviceAssignUsersModalTitle" class="h5 mb-1">Asignar usuarios</h2>
                    <p id="serviceAssignUsersModalSubtitle" class="small text-secondary mb-0"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="inline-card mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="serviceAssignAccountSelect">Cuenta del servicio</label>
                            <select class="form-select" id="serviceAssignAccountSelect"></select>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="serviceAssignUserSearchInput">Buscar usuario</label>
                            <input class="form-control" type="search" id="serviceAssignUserSearchInput" placeholder="Nombre, usuario o correo">
                        </div>
                    </div>
                </div>

                <div class="data-table-wrapper">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th>Asignaciones en este servicio</th>
                                    <th>Accion</th>
                                </tr>
                            </thead>
                            <tbody id="serviceAssignUsersTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="confirmActionModalTitle" class="h5 mb-0">Confirmar accion</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="confirmActionModalBody" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button id="confirmActionModalCancelButton" type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button id="confirmActionModalConfirmButton" type="button" class="btn btn-danger">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="feedbackModalTitle" class="h5 mb-0">Aviso</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="feedbackModalBody" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
    const authView = document.getElementById('authView');
    const adminView = document.getElementById('adminView');
    const statusMessage = document.getElementById('statusMessage');
    const adminStatusMessage = document.getElementById('adminStatusMessage');
    const adminIdentity = document.getElementById('adminIdentity');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const logoutButton = document.getElementById('logoutButton');

    const serviceForm = document.getElementById('serviceForm');
    const serviceFormHeading = document.getElementById('serviceFormHeading');
    const serviceFormSubtitle = document.getElementById('serviceFormSubtitle');
    const serviceFormAction = document.getElementById('serviceFormAction');
    const serviceFormServiceId = document.getElementById('serviceFormServiceId');
    const serviceSubmitButton = document.getElementById('serviceSubmitButton');
    const cancelServiceEditButton = document.getElementById('cancelServiceEditButton');
    const currentServiceLogoWrapper = document.getElementById('currentServiceLogoWrapper');
    const currentServiceLogoImage = document.getElementById('currentServiceLogoImage');
    const servicesList = document.getElementById('servicesList');
    const serviceCountBadge = document.getElementById('serviceCountBadge');
    const servicesOverviewSection = document.getElementById('servicesOverviewSection');
    const serviceAccountsSection = document.getElementById('serviceAccountsSection');
    const serviceAccountsTitle = document.getElementById('serviceAccountsTitle');
    const serviceAccountsSubtitle = document.getElementById('serviceAccountsSubtitle');
    const serviceAccountsTableBody = document.getElementById('serviceAccountsTableBody');
    const backToServicesButton = document.getElementById('backToServicesButton');
    const toggleCreateAccountButton = document.getElementById('toggleCreateAccountButton');
    const createAccountPanel = document.getElementById('createAccountPanel');
    const serviceAccountFormHeading = document.getElementById('serviceAccountFormHeading');
    const cancelServiceAccountEditButton = document.getElementById('cancelServiceAccountEditButton');
    const serviceAccountAction = document.getElementById('serviceAccountAction');
    const serviceAccountId = document.getElementById('serviceAccountId');
    const serviceAccountForm = document.getElementById('serviceAccountForm');
    const serviceAccountServiceId = document.getElementById('serviceAccountServiceId');
    const serviceAccountSubmitButton = document.getElementById('serviceAccountSubmitButton');
    const serviceAccountEmail = document.getElementById('serviceAccountEmail');

    const registeredUsersTableBody = document.getElementById('registeredUsersTableBody');
    const userCountBadge = document.getElementById('userCountBadge');
    const toggleCreateUserButton = document.getElementById('toggleCreateUserButton');
    const createUserPanel = document.getElementById('createUserPanel');
    const adminCreateUserForm = document.getElementById('adminCreateUserForm');
    const cancelCreateUserButton = document.getElementById('cancelCreateUserButton');
    const userEditPanel = document.getElementById('userEditPanel');
    const userEditForm = document.getElementById('userEditForm');
    const cancelUserEditButton = document.getElementById('cancelUserEditButton');

    const serviceAssignmentsTableBody = document.getElementById('serviceAssignmentsTableBody');
    const assignmentServiceCountBadge = document.getElementById('assignmentServiceCountBadge');

    const assignedUsersModalElement = document.getElementById('assignedUsersModal');
    const assignedUsersModalTitle = document.getElementById('assignedUsersModalTitle');
    const assignedUsersModalSubtitle = document.getElementById('assignedUsersModalSubtitle');
    const assignedUsersTableBody = document.getElementById('assignedUsersTableBody');
    const assignedUsersModal = new bootstrap.Modal(assignedUsersModalElement);

    const userAssignmentsModalElement = document.getElementById('userAssignmentsModal');
    const userAssignmentsModalTitle = document.getElementById('userAssignmentsModalTitle');
    const userAssignmentsModalSubtitle = document.getElementById('userAssignmentsModalSubtitle');
    const userAssignmentsTableBody = document.getElementById('userAssignmentsTableBody');
    const userAssignmentsModal = new bootstrap.Modal(userAssignmentsModalElement);

    const serviceAssignUsersModalElement = document.getElementById('serviceAssignUsersModal');
    const serviceAssignUsersModalTitle = document.getElementById('serviceAssignUsersModalTitle');
    const serviceAssignUsersModalSubtitle = document.getElementById('serviceAssignUsersModalSubtitle');
    const serviceAssignAccountSelect = document.getElementById('serviceAssignAccountSelect');
    const serviceAssignUserSearchInput = document.getElementById('serviceAssignUserSearchInput');
    const serviceAssignUsersTableBody = document.getElementById('serviceAssignUsersTableBody');
    const serviceAssignUsersModal = new bootstrap.Modal(serviceAssignUsersModalElement);

    const confirmActionModalElement = document.getElementById('confirmActionModal');
    const confirmActionModalTitle = document.getElementById('confirmActionModalTitle');
    const confirmActionModalBody = document.getElementById('confirmActionModalBody');
    const confirmActionModalConfirmButton = document.getElementById('confirmActionModalConfirmButton');
    const confirmActionModal = new bootstrap.Modal(confirmActionModalElement);

    const feedbackModalElement = document.getElementById('feedbackModal');
    const feedbackModalTitle = document.getElementById('feedbackModalTitle');
    const feedbackModalBody = document.getElementById('feedbackModalBody');
    const feedbackModal = new bootstrap.Modal(feedbackModalElement);

    const passwordToggleButtons = document.querySelectorAll('[data-password-target]');

    const appState = {
        user: null,
        selectedServiceId: null,
        selectedAssignServiceId: null,
        expandedAssignmentServiceId: null,
        assignmentTableState: {},
        confirmResolver: null,
        overview: {
            services: [],
            accounts: [],
            users: [],
        },
    };

    function showStatus(message, tone = 'secondary') {
        statusMessage.className = `small text-${tone}`;
        statusMessage.textContent = message;
    }

    function showAdminStatus(message, tone = 'secondary') {
        adminStatusMessage.className = `small text-${tone} mb-4`;
        adminStatusMessage.textContent = message;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function normalizeArray(value) {
        return Array.isArray(value) ? value : [];
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Ocurrio un error al procesar la solicitud.');
        }

        return data;
    }

    function getServices() {
        return normalizeArray(appState.overview.services);
    }

    function getAccounts() {
        return normalizeArray(appState.overview.accounts);
    }

    function getUsers() {
        return normalizeArray(appState.overview.users);
    }

    function getServiceById(serviceId) {
        return getServices().find((service) => Number(service.id) === Number(serviceId)) || null;
    }

    function getUserById(userId) {
        return getUsers().find((user) => Number(user.id) === Number(userId)) || null;
    }

    function getAccountById(accountId) {
        return getAccounts().find((account) => Number(account.id) === Number(accountId)) || null;
    }

    function getServiceAssignmentRows(service) {
        const rows = [];

        normalizeArray(service.accounts).forEach((account) => {
            normalizeArray(account.assigned_users).forEach((assignedUser) => {
                rows.push({
                    assignment_id: Number(assignedUser.assignment_id),
                    user_id: Number(assignedUser.id),
                    nombre: assignedUser.nombre,
                    apellido: assignedUser.apellido,
                    username: assignedUser.username,
                    email: assignedUser.email,
                    account_id: Number(account.id),
                    account_email: account.correo_acceso,
                    account_password: account.password_acceso,
                    description: account.descripcion || '',
                });
            });
        });

        return rows;
    }

    function getAssignmentTableState(serviceId) {
        const normalizedServiceId = String(serviceId);

        if (!appState.assignmentTableState[normalizedServiceId]) {
            appState.assignmentTableState[normalizedServiceId] = {
                query: '',
                page: 1,
                pageSize: 5,
            };
        }

        return appState.assignmentTableState[normalizedServiceId];
    }

    function enterAdminMode(user) {
        appState.user = user;
        authView.classList.add('d-none');
        adminView.classList.remove('d-none');
        adminIdentity.textContent = `${user.nombre} ${user.apellido} · ${user.username}`;
    }

    function leaveAdminMode() {
        appState.user = null;
        appState.selectedServiceId = null;
        appState.selectedAssignServiceId = null;
        appState.expandedAssignmentServiceId = null;
        appState.assignmentTableState = {};
        userEditPanel.classList.add('d-none');
        createUserPanel.classList.add('d-none');
        adminView.classList.add('d-none');
        authView.classList.remove('d-none');
        resetServiceForm();
        resetServiceAccountForm();
        resetCreateUserForm();
        showServicesOverview();
        showAdminStatus('', 'secondary');
    }

    function showServicesOverview() {
        appState.selectedServiceId = null;
        servicesOverviewSection.classList.remove('d-none');
        serviceAccountsSection.classList.add('d-none');
        createAccountPanel.classList.add('d-none');
    }

    function showServiceAccounts(serviceId, openCreatePanel = false) {
        appState.selectedServiceId = Number(serviceId);
        servicesOverviewSection.classList.add('d-none');
        serviceAccountsSection.classList.remove('d-none');
        resetServiceAccountForm();
        createAccountPanel.classList.toggle('d-none', !openCreatePanel);
        renderServiceAccountsView();
    }

    function resetServiceForm() {
        serviceForm.reset();
        serviceFormAction.value = 'create';
        serviceFormServiceId.value = '';
        serviceSubmitButton.textContent = 'Guardar servicio';
        serviceFormHeading.textContent = 'Agregar servicio';
        serviceFormSubtitle.textContent = 'Crea servicios con nombre, logo local, color destacado y descripcion.';
        cancelServiceEditButton.classList.add('d-none');
        currentServiceLogoWrapper.classList.add('d-none');
        currentServiceLogoImage.src = '';
        document.getElementById('serviceColor').value = '#0b57d0';
    }

    function resetCreateUserForm() {
        adminCreateUserForm.reset();
    }

    function populateServiceForm(serviceId) {
        const service = getServiceById(serviceId);

        if (!service) {
            return;
        }

        serviceFormAction.value = 'update';
        serviceFormServiceId.value = service.id;
        document.getElementById('serviceName').value = service.nombre;
        document.getElementById('serviceColor').value = service.color_destacado || '#0b57d0';
        document.getElementById('serviceDescription').value = service.descripcion || '';
        document.getElementById('serviceLogo').value = '';
        serviceSubmitButton.textContent = 'Actualizar servicio';
        serviceFormHeading.textContent = 'Editar servicio';
        serviceFormSubtitle.textContent = 'Actualiza nombre, logo local, color destacado y descripcion del servicio.';
        cancelServiceEditButton.classList.remove('d-none');

        if (service.logo_url) {
            currentServiceLogoImage.src = service.logo_url;
            currentServiceLogoWrapper.classList.remove('d-none');
        } else {
            currentServiceLogoImage.src = '';
            currentServiceLogoWrapper.classList.add('d-none');
        }

        serviceForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function populateUserEditForm(userId) {
        const user = getUserById(userId);

        if (!user) {
            return;
        }

        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserNombre').value = user.nombre;
        document.getElementById('editUserApellido').value = user.apellido;
        document.getElementById('editUsername').value = user.username;
        document.getElementById('editUserEmail').value = user.email;
        document.getElementById('editUserPhone').value = user.telefono || '';
        document.getElementById('editUserActive').value = Number(user.activo) === 1 ? '1' : '0';
        userEditPanel.classList.remove('d-none');
        userEditPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function resetServiceAccountForm() {
        serviceAccountForm.reset();
        serviceAccountAction.value = 'create';
        serviceAccountId.value = '';
        serviceAccountServiceId.value = appState.selectedServiceId !== null ? String(appState.selectedServiceId) : '';
        serviceAccountFormHeading.textContent = 'Nueva cuenta para este servicio';
        serviceAccountSubmitButton.textContent = 'Guardar cuenta';
        cancelServiceAccountEditButton.classList.add('d-none');
    }

    function populateServiceAccountForm(accountId) {
        const account = getAccountById(accountId);

        if (!account) {
            return;
        }

        appState.selectedServiceId = Number(account.servicio_id);
        serviceAccountAction.value = 'update';
        serviceAccountId.value = String(account.id);
        serviceAccountServiceId.value = String(account.servicio_id);
        document.getElementById('serviceAccountEmail').value = account.correo_acceso;
        document.getElementById('serviceAccountPassword').value = account.password_acceso;
        document.getElementById('serviceAccountDescription').value = account.descripcion || '';
        serviceAccountFormHeading.textContent = 'Editar cuenta de este servicio';
        serviceAccountSubmitButton.textContent = 'Actualizar cuenta';
        cancelServiceAccountEditButton.classList.remove('d-none');
        createAccountPanel.classList.remove('d-none');
        serviceAccountForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function formatServiceLogo(service) {
        if (service.logo_url) {
            return `<img src="${escapeHtml(service.logo_url)}" alt="${escapeHtml(service.nombre)}">`;
        }

        return `<span>${escapeHtml(service.nombre.slice(0, 1).toUpperCase())}</span>`;
    }

    function openConfirmModal({ title, message, confirmText = 'Aceptar', confirmClass = 'btn-danger' }) {
        return new Promise((resolve) => {
            appState.confirmResolver = resolve;
            confirmActionModalTitle.textContent = title;
            confirmActionModalBody.textContent = message;
            confirmActionModalConfirmButton.textContent = confirmText;
            confirmActionModalConfirmButton.className = `btn ${confirmClass}`;
            confirmActionModal.show();
        });
    }

    function showFeedbackModal({ title = 'Aviso', message }) {
        feedbackModalTitle.textContent = title;
        feedbackModalBody.textContent = message;
        feedbackModal.show();
    }

    function settleConfirmModal(result) {
        if (typeof appState.confirmResolver === 'function') {
            const resolver = appState.confirmResolver;
            appState.confirmResolver = null;
            resolver(result);
        }
    }

    function renderServices() {
        const services = getServices();
        serviceCountBadge.textContent = `${services.length} servicio(s)`;

        if (services.length === 0) {
            servicesList.innerHTML = '<div class="col-12"><div class="empty-state">Aun no hay servicios creados.</div></div>';
            return;
        }

        servicesList.innerHTML = services.map((service) => {
            const accountCount = normalizeArray(service.accounts).length;
            const deleteDisabled = accountCount > 0 ? 'disabled' : '';
            const deleteHelp = accountCount > 0 ? 'Solo se puede eliminar si no tiene cuentas registradas.' : 'Eliminar servicio';

            return `
                <div class="col-12 col-md-6 col-xxl-4">
                    <article class="service-card d-flex flex-column h-100">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="service-logo" style="background:${escapeHtml(service.color_destacado)};">${formatServiceLogo(service)}</div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                    <h3 class="h6 mb-0">${escapeHtml(service.nombre)}</h3>
                                    <span class="d-inline-flex align-items-center gap-2 small text-secondary">
                                        <span class="color-swatch" style="background:${escapeHtml(service.color_destacado)};"></span>
                                        ${escapeHtml(service.color_destacado)}
                                    </span>
                                </div>
                                <p class="small text-secondary mt-2 mb-0">${escapeHtml(service.descripcion || 'Sin descripcion registrada.')}</p>
                            </div>
                        </div>
                        <div class="small text-secondary mb-3">${accountCount} cuenta(s) registradas</div>
                        <div class="mt-auto d-grid gap-2">
                            <div class="d-grid d-sm-flex gap-2">
                                <button class="btn btn-outline-primary flex-fill" type="button" data-view-service-accounts="${service.id}">Ver Cuentas</button>
                                <button class="btn btn-primary flex-fill" type="button" data-create-service-account="${service.id}">Crear Cuenta</button>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-secondary flex-fill" type="button" data-edit-service="${service.id}">Editar</button>
                                <button class="btn btn-outline-danger flex-fill" type="button" data-delete-service="${service.id}" title="${escapeHtml(deleteHelp)}" ${deleteDisabled}>Eliminar</button>
                            </div>
                        </div>
                    </article>
                </div>
            `;
        }).join('');
    }

    function renderServiceAccountsView() {
        const service = getServiceById(appState.selectedServiceId);

        if (!service) {
            showServicesOverview();
            renderServices();
            return;
        }

        const accounts = normalizeArray(service.accounts);
        serviceAccountsTitle.textContent = `Cuentas del servicio ${service.nombre}`;
        serviceAccountsSubtitle.textContent = 'Consulta las cuentas registradas para este servicio.';
        serviceAccountServiceId.value = String(service.id);

        if (accounts.length === 0) {
            serviceAccountsTableBody.innerHTML = '<tr><td colspan="5"><div class="empty-state">Este servicio aun no tiene cuentas registradas.</div></td></tr>';
            return;
        }

        serviceAccountsTableBody.innerHTML = accounts.map((account) => {
            const assignedUsers = normalizeArray(account.assigned_users);
            const buttonLabel = assignedUsers.length === 0 ? 'Ver usuarios (0)' : `Ver usuarios (${assignedUsers.length})`;

            return `
                <tr>
                    <td><div class="fw-semibold">${escapeHtml(account.correo_acceso)}</div></td>
                    <td>${escapeHtml(account.descripcion || 'Sin descripcion')}</td>
                    <td>${escapeHtml(account.password_acceso)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-open-assigned-users="${account.id}">${buttonLabel}</button>
                    </td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-edit-service-account="${account.id}">Editar</button>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-delete-service-account="${account.id}" ${assignedUsers.length > 0 ? 'disabled' : ''}>Eliminar</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function openAssignedUsersModal(accountId) {
        const account = getAccountById(accountId);

        if (!account) {
            return;
        }

        const assignedUsers = normalizeArray(account.assigned_users);
        assignedUsersModalTitle.textContent = `Usuarios asignados a ${account.correo_acceso}`;
        assignedUsersModalSubtitle.textContent = `${account.servicio_nombre} · ${assignedUsers.length} usuario(s) asignado(s)`;

        if (assignedUsers.length === 0) {
            assignedUsersTableBody.innerHTML = '<tr><td colspan="4"><div class="empty-state">Esta cuenta no tiene usuarios asignados.</div></td></tr>';
        } else {
            assignedUsersTableBody.innerHTML = assignedUsers.map((user) => `
                <tr>
                    <td>${escapeHtml(user.nombre)} ${escapeHtml(user.apellido)}</td>
                    <td>@${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger" type="button" data-unassign-account-user="${user.assignment_id}" data-account-id="${account.id}">Desasignar</button>
                    </td>
                </tr>
            `).join('');
        }

        assignedUsersModal.show();
    }

    function openUserAssignmentsModal(userId) {
        const user = getUserById(userId);

        if (!user) {
            return;
        }

        const assignments = normalizeArray(user.assignments);
        userAssignmentsModalTitle.textContent = `Cuentas asignadas a ${user.nombre} ${user.apellido}`;
        userAssignmentsModalSubtitle.textContent = `${assignments.length} cuenta(s) asignada(s) en total`;

        if (assignments.length === 0) {
            userAssignmentsTableBody.innerHTML = '<tr><td colspan="4"><div class="empty-state">Este usuario aun no tiene cuentas asignadas.</div></td></tr>';
        } else {
            userAssignmentsTableBody.innerHTML = assignments.map((assignment) => `
                <tr>
                    <td>${escapeHtml(assignment.service_name)}</td>
                    <td>${escapeHtml(assignment.account_email)}</td>
                    <td>${escapeHtml(assignment.description || 'Sin descripcion')}</td>
                    <td>${escapeHtml(assignment.account_password)}</td>
                </tr>
            `).join('');
        }

        userAssignmentsModal.show();
    }

    function renderRegisteredUsersTable() {
        const users = getUsers();
        userCountBadge.textContent = `${users.length} usuario(s)`;

        if (users.length === 0) {
            registeredUsersTableBody.innerHTML = '<tr><td colspan="7"><div class="empty-state">No hay usuarios con rol usuario registrados.</div></td></tr>';
            return;
        }

        registeredUsersTableBody.innerHTML = users.map((user) => {
            const assignmentCount = normalizeArray(user.assignments).length;
            const assignmentsLabel = assignmentCount === 1 ? '1 cuenta asignada' : `${assignmentCount} cuentas asignadas`;

            return `
                <tr>
                    <td><div class="fw-semibold">${escapeHtml(user.nombre)} ${escapeHtml(user.apellido)}</div></td>
                    <td>@${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${escapeHtml(user.telefono || 'Sin telefono')}</td>
                    <td>${Number(user.activo) === 1 ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-view-user-assignments="${user.id}">Ver Cuentas Asignadas</button>
                        <div class="small text-secondary mt-1">${assignmentsLabel}</div>
                    </td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-edit-user="${user.id}">Editar</button>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-delete-user="${user.id}">Eliminar</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderServiceAssignmentsAccordion(service) {
        const state = getAssignmentTableState(service.id);
        const allRows = getServiceAssignmentRows(service);
        const normalizedQuery = state.query.trim().toLowerCase();
        const filteredRows = allRows.filter((row) => {
            const haystack = [
                row.nombre,
                row.apellido,
                row.username,
                row.email,
                row.account_email,
                row.description,
            ].join(' ').toLowerCase();

            return haystack.includes(normalizedQuery);
        });

        const totalRows = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / state.pageSize));
        if (state.page > totalPages) {
            state.page = totalPages;
        }

        const startIndex = totalRows === 0 ? 0 : (state.page - 1) * state.pageSize;
        const paginatedRows = filteredRows.slice(startIndex, startIndex + state.pageSize);
        const summary = totalRows === 0
            ? 'No hay resultados para los filtros actuales.'
            : `Mostrando ${startIndex + 1}-${Math.min(startIndex + state.pageSize, totalRows)} de ${totalRows} asignacion(es)`;

        const tableMarkup = totalRows === 0
            ? '<div class="empty-state">Este servicio aun no tiene usuarios asignados.</div>'
            : `
                <div class="data-table-wrapper">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th>Cuenta asignada</th>
                                    <th>Descripcion</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${paginatedRows.map((row) => `
                                    <tr>
                                        <td>${escapeHtml(row.nombre)} ${escapeHtml(row.apellido)}</td>
                                        <td>@${escapeHtml(row.username)}</td>
                                        <td>${escapeHtml(row.email)}</td>
                                        <td>
                                            <div class="fw-semibold">${escapeHtml(row.account_email)}</div>
                                            <div class="small text-secondary">Clave: ${escapeHtml(row.account_password)}</div>
                                        </td>
                                        <td>${escapeHtml(row.description || 'Sin descripcion')}</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger" type="button" data-unassign-id="${row.assignment_id}">Desasignar</button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

        return `
            <div class="table-toolbar">
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <input class="form-control" type="search" value="${escapeHtml(state.query)}" placeholder="Filtrar por usuario, correo o cuenta" data-service-filter="${service.id}">
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <label class="small text-secondary" for="servicePageSize${service.id}">Filas por pagina</label>
                    <select class="form-select" id="servicePageSize${service.id}" data-service-page-size="${service.id}">
                        <option value="5" ${state.pageSize === 5 ? 'selected' : ''}>5</option>
                        <option value="10" ${state.pageSize === 10 ? 'selected' : ''}>10</option>
                        <option value="20" ${state.pageSize === 20 ? 'selected' : ''}>20</option>
                    </select>
                </div>
            </div>
            ${tableMarkup}
            <div class="pagination-strip">
                <div class="small text-secondary">${summary}</div>
                <div class="table-action-group">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-service-page-nav="${service.id}" data-direction="prev" ${state.page <= 1 ? 'disabled' : ''}>Anterior</button>
                    <span class="metric-pill">Pagina ${state.page} de ${totalPages}</span>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-service-page-nav="${service.id}" data-direction="next" ${state.page >= totalPages ? 'disabled' : ''}>Siguiente</button>
                </div>
            </div>
        `;
    }

    function renderServiceAssignmentTable() {
        const services = getServices();
        assignmentServiceCountBadge.textContent = `${services.length} servicio(s)`;

        if (services.length === 0) {
            serviceAssignmentsTableBody.innerHTML = '<tr><td colspan="4"><div class="empty-state">Aun no hay servicios creados.</div></td></tr>';
            return;
        }

        serviceAssignmentsTableBody.innerHTML = services.map((service) => {
            const accounts = normalizeArray(service.accounts);
            const assignmentRows = getServiceAssignmentRows(service);
            const uniqueUsers = new Set(assignmentRows.map((row) => row.user_id));
            const isExpanded = appState.expandedAssignmentServiceId === Number(service.id);

            return `
                <tr>
                    <td>
                        <div class="service-table-title">
                            <div class="service-logo" style="background:${escapeHtml(service.color_destacado)};">${formatServiceLogo(service)}</div>
                            <div>
                                <div class="fw-semibold">${escapeHtml(service.nombre)}</div>
                                <div class="small text-secondary">${escapeHtml(service.descripcion || 'Sin descripcion registrada.')}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="metric-pill">${accounts.length} cuenta(s)</span></td>
                    <td><span class="metric-pill">${uniqueUsers.size} usuario(s)</span></td>
                    <td>
                        <div class="table-action-group">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-toggle-service-users="${service.id}">${isExpanded ? 'Ocultar Usuarios' : 'Ver Usuarios'}</button>
                            <button class="btn btn-sm btn-primary" type="button" data-open-service-assign="${service.id}" ${accounts.length === 0 ? 'disabled' : ''}>Asignar Usuarios</button>
                        </div>
                    </td>
                </tr>
                <tr class="service-accordion-row ${isExpanded ? '' : 'd-none'}">
                    <td colspan="4">
                        <div class="service-accordion-panel">
                            ${renderServiceAssignmentsAccordion(service)}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderServiceAssignUsersTable() {
        const service = getServiceById(appState.selectedAssignServiceId);

        if (!service) {
            serviceAssignUsersTableBody.innerHTML = '<tr><td colspan="5"><div class="empty-state">Servicio no disponible.</div></td></tr>';
            return;
        }

        const serviceAccounts = normalizeArray(service.accounts);
        const selectedAccountId = Number(serviceAssignAccountSelect.value || 0);
        const searchQuery = serviceAssignUserSearchInput.value.trim().toLowerCase();

        if (serviceAccounts.length === 0) {
            serviceAssignUsersTableBody.innerHTML = '<tr><td colspan="5"><div class="empty-state">Primero crea al menos una cuenta para este servicio y luego podras asignar usuarios.</div></td></tr>';
            return;
        }

        const users = getUsers().filter((user) => {
            const haystack = [user.nombre, user.apellido, user.username, user.email].join(' ').toLowerCase();
            return haystack.includes(searchQuery);
        });

        if (users.length === 0) {
            serviceAssignUsersTableBody.innerHTML = '<tr><td colspan="5"><div class="empty-state">No hay usuarios que coincidan con la busqueda.</div></td></tr>';
            return;
        }

        serviceAssignUsersTableBody.innerHTML = users.map((user) => {
            const serviceAssignments = normalizeArray(user.assignments).filter((assignment) => assignment.service_name === service.nombre);
            const alreadyAssignedToSelectedAccount = serviceAssignments.some((assignment) => Number(assignment.account_id) === selectedAccountId);
            const currentAssignmentsMarkup = serviceAssignments.length === 0
                ? '<span class="text-secondary small">Sin asignaciones en este servicio</span>'
                : serviceAssignments.map((assignment) => `
                    <div class="small mb-1">
                        <span class="fw-semibold">${escapeHtml(assignment.account_email)}</span>
                        <span class="text-secondary">${escapeHtml(assignment.description || 'Sin descripcion')}</span>
                    </div>
                `).join('');

            return `
                <tr>
                    <td>${escapeHtml(user.nombre)} ${escapeHtml(user.apellido)}</td>
                    <td>@${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${currentAssignmentsMarkup}</td>
                    <td>
                        <button class="btn btn-sm ${alreadyAssignedToSelectedAccount ? 'btn-outline-secondary' : 'btn-primary'}" type="button" data-assign-user-id="${user.id}" ${alreadyAssignedToSelectedAccount || selectedAccountId <= 0 ? 'disabled' : ''}>
                            ${alreadyAssignedToSelectedAccount ? 'Asignado' : 'Asignar'}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function openServiceAssignUsersModal(serviceId, preferredAccountId = '') {
        const service = getServiceById(serviceId);

        if (!service) {
            return;
        }

        appState.selectedAssignServiceId = Number(serviceId);
        const serviceAccounts = normalizeArray(service.accounts);
        const initialAccountId = serviceAccounts.some((account) => String(account.id) === String(preferredAccountId))
            ? String(preferredAccountId)
            : (serviceAccounts[0] ? String(serviceAccounts[0].id) : '');

        serviceAssignUsersModalTitle.textContent = `Asignar usuarios a ${service.nombre}`;
        serviceAssignUsersModalSubtitle.textContent = serviceAccounts.length > 0
            ? 'Selecciona una cuenta del servicio y luego asigna uno o varios usuarios.'
            : 'Primero crea una cuenta para este servicio y luego podras asignar usuarios.';
        serviceAssignAccountSelect.innerHTML = serviceAccounts.length > 0
            ? serviceAccounts.map((account) => `<option value="${account.id}">${escapeHtml(account.correo_acceso)}${account.descripcion ? ` · ${escapeHtml(account.descripcion)}` : ''}</option>`).join('')
            : '<option value="">No hay cuentas registradas</option>';
        serviceAssignAccountSelect.disabled = serviceAccounts.length === 0;
        serviceAssignAccountSelect.value = initialAccountId;
        serviceAssignUserSearchInput.value = '';
        renderServiceAssignUsersTable();
        serviceAssignUsersModal.show();
    }

    function renderOverview() {
        renderServices();
        renderRegisteredUsersTable();
        renderServiceAssignmentTable();

        if (appState.selectedServiceId !== null) {
            renderServiceAccountsView();
        }

        if (serviceAssignUsersModalElement.classList.contains('show') && appState.selectedAssignServiceId !== null) {
            renderServiceAssignUsersTable();
        }
    }

    async function loadAdminOverview() {
        showAdminStatus('Cargando panel de administracion...', 'secondary');
        const result = await requestJson('./api/admin/overview.php');
        appState.overview = {
            services: normalizeArray(result.services),
            accounts: normalizeArray(result.accounts),
            users: normalizeArray(result.users),
        };
        renderOverview();
        showAdminStatus('Panel actualizado.', 'success');
    }

    async function bootstrapSession() {
        try {
            const session = await requestJson('./api/session.php');

            if (session.authenticated && session.user && session.user.role === 'admin') {
                enterAdminMode(session.user);
                await loadAdminOverview();
            }
        } catch (error) {
            console.error(error);
        }
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

    confirmActionModalConfirmButton.addEventListener('click', () => {
        settleConfirmModal(true);
        confirmActionModal.hide();
    });

    confirmActionModalElement.addEventListener('hidden.bs.modal', () => {
        settleConfirmModal(false);
    });

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showStatus('Validando usuario...', 'secondary');

        try {
            const result = await requestJson('./api/login.php', {
                method: 'POST',
                body: new FormData(loginForm),
            });

            console.log('Usuario Existe');
            showStatus(result.message, 'success');

            if (result.role === 'admin' && result.user) {
                enterAdminMode(result.user);
                await loadAdminOverview();
            }
        } catch (error) {
            console.log('Usuario No existe');
            showStatus(error.message, 'danger');
        }
    });

    registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showStatus('Registrando usuario...', 'secondary');

        try {
            const result = await requestJson('./api/register.php', {
                method: 'POST',
                body: new FormData(registerForm),
            });

            showStatus(result.message, 'success');
            registerForm.reset();
        } catch (error) {
            showStatus(error.message, 'danger');
        }
    });

    serviceForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showAdminStatus(serviceFormAction.value === 'update' ? 'Actualizando servicio...' : 'Guardando servicio...', 'secondary');

        try {
            const result = await requestJson('./api/admin/services.php', {
                method: 'POST',
                body: new FormData(serviceForm),
            });

            resetServiceForm();
            showAdminStatus(result.message, 'success');
            await loadAdminOverview();
        } catch (error) {
            showAdminStatus(error.message, 'danger');
        }
    });

    adminCreateUserForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showAdminStatus('Registrando usuario desde administracion...', 'secondary');

        const formData = new FormData(adminCreateUserForm);
        formData.append('action', 'create');

        try {
            const result = await requestJson('./api/admin/users.php', {
                method: 'POST',
                body: formData,
            });

            resetCreateUserForm();
            createUserPanel.classList.add('d-none');
            showAdminStatus(result.message, 'success');
            await loadAdminOverview();
        } catch (error) {
            showAdminStatus(error.message, 'danger');
        }
    });

    userEditForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showAdminStatus('Guardando cambios del usuario...', 'secondary');

        const formData = new FormData(userEditForm);
        formData.append('action', 'update');

        try {
            const result = await requestJson('./api/admin/users.php', {
                method: 'POST',
                body: formData,
            });

            showAdminStatus(result.message, 'success');
            userEditPanel.classList.add('d-none');
            await loadAdminOverview();
        } catch (error) {
            showAdminStatus(error.message, 'danger');
        }
    });

    cancelServiceEditButton.addEventListener('click', () => {
        resetServiceForm();
    });

    toggleCreateUserButton.addEventListener('click', () => {
        createUserPanel.classList.toggle('d-none');
        if (createUserPanel.classList.contains('d-none')) {
            resetCreateUserForm();
        }
    });

    cancelCreateUserButton.addEventListener('click', () => {
        resetCreateUserForm();
        createUserPanel.classList.add('d-none');
    });

    cancelUserEditButton.addEventListener('click', () => {
        userEditForm.reset();
        userEditPanel.classList.add('d-none');
    });

    servicesList.addEventListener('click', async (event) => {
        const viewButton = event.target.closest('[data-view-service-accounts]');
        const createAccountButton = event.target.closest('[data-create-service-account]');
        const editButton = event.target.closest('[data-edit-service]');
        const deleteButton = event.target.closest('[data-delete-service]');

        if (viewButton) {
            showServiceAccounts(viewButton.dataset.viewServiceAccounts);
            return;
        }

        if (createAccountButton) {
            showServiceAccounts(createAccountButton.dataset.createServiceAccount, true);
            return;
        }

        if (editButton) {
            populateServiceForm(editButton.dataset.editService);
            return;
        }

        if (!deleteButton) {
            return;
        }

        const confirmed = await openConfirmModal({
            title: 'Eliminar servicio',
            message: 'Se eliminara el servicio seleccionado si no tiene cuentas registradas.',
            confirmText: 'Eliminar',
            confirmClass: 'btn btn-danger',
        });

        if (!confirmed) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('servicio_id', deleteButton.dataset.deleteService);
        showAdminStatus('Eliminando servicio...', 'secondary');

        try {
            const result = await requestJson('./api/admin/services.php', {
                method: 'POST',
                body: formData,
            });

            resetServiceForm();
            showAdminStatus(result.message, 'success');
            await loadAdminOverview();
        } catch (error) {
            showAdminStatus(error.message, 'danger');
        }
    });

    backToServicesButton.addEventListener('click', () => {
        showServicesOverview();
    });

    toggleCreateAccountButton.addEventListener('click', () => {
        createAccountPanel.classList.toggle('d-none');
        resetServiceAccountForm();
    });

    cancelServiceAccountEditButton.addEventListener('click', () => {
        resetServiceAccountForm();
    });

    serviceAccountForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showAdminStatus(serviceAccountAction.value === 'update' ? 'Actualizando cuenta del servicio...' : 'Guardando cuenta del servicio...', 'secondary');

        const formData = new FormData(serviceAccountForm);
        formData.set('action', serviceAccountAction.value);
        formData.set('cuenta_id', serviceAccountId.value);
        formData.set('servicio_id', serviceAccountServiceId.value);

        try {
            const result = await requestJson('./api/admin/accounts.php', {
                method: 'POST',
                body: formData,
            });

            if (!result.success) {
                showAdminStatus(result.message, 'danger');
                showFeedbackModal({
                    title: 'Cuenta no disponible',
                    message: result.message,
                });

                if (typeof result.message === 'string' && result.message.toLowerCase().includes('ya fue registrada')) {
                    serviceAccountEmail.focus();
                    serviceAccountEmail.select();
                }

                return;
            }

            const currentServiceId = appState.selectedServiceId;
            resetServiceAccountForm();
            createAccountPanel.classList.add('d-none');
            showAdminStatus(result.message, 'success');
            await loadAdminOverview();

            if (currentServiceId !== null) {
                showServiceAccounts(currentServiceId);
            }
        } catch (error) {
            showAdminStatus(error.message, 'danger');
            showFeedbackModal({
                title: 'No fue posible actualizar la cuenta',
                message: error.message,
            });
        }
    });

    serviceAccountsTableBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-open-assigned-users]');
        const editButton = event.target.closest('[data-edit-service-account]');
        const deleteButton = event.target.closest('[data-delete-service-account]');

        if (editButton) {
            populateServiceAccountForm(editButton.dataset.editServiceAccount);
            return;
        }

        if (deleteButton) {
            openConfirmModal({
                title: 'Eliminar cuenta',
                message: 'Se eliminara la cuenta seleccionada si no tiene usuarios asignados.',
                confirmText: 'Eliminar',
                confirmClass: 'btn btn-danger',
            }).then(async (confirmed) => {
                if (!confirmed) {
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('cuenta_id', deleteButton.dataset.deleteServiceAccount);
                showAdminStatus('Eliminando cuenta del servicio...', 'secondary');

                try {
                    const result = await requestJson('./api/admin/accounts.php', {
                        method: 'POST',
                        body: formData,
                    });

                    if (!result.success) {
                        showAdminStatus(result.message, 'danger');
                        return;
                    }

                    resetServiceAccountForm();
                    createAccountPanel.classList.add('d-none');
                    showAdminStatus(result.message, 'success');
                    await loadAdminOverview();

                    if (appState.selectedServiceId !== null) {
                        showServiceAccounts(appState.selectedServiceId);
                    }
                } catch (error) {
                    showAdminStatus(error.message, 'danger');
                }
            });
            return;
        }

        if (!button) {
            return;
        }

        openAssignedUsersModal(button.dataset.openAssignedUsers);
    });

    assignedUsersTableBody.addEventListener('click', async (event) => {
        const unassignButton = event.target.closest('[data-unassign-account-user]');

        if (!unassignButton) {
            return;
        }

        const confirmed = await openConfirmModal({
            title: 'Desasignar usuario',
            message: 'Se retirara este usuario de la cuenta seleccionada.',
            confirmText: 'Desasignar',
            confirmClass: 'btn btn-danger',
        });

        if (!confirmed) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'unassign');
        formData.append('assignment_id', unassignButton.dataset.unassignAccountUser);
        showAdminStatus('Desasignando usuario de la cuenta...', 'secondary');

        try {
            const result = await requestJson('./api/admin/assignments.php', {
                method: 'POST',
                body: formData,
            });

            showAdminStatus(result.message, 'success');
            await loadAdminOverview();
            openAssignedUsersModal(unassignButton.dataset.accountId);
        } catch (error) {
            showAdminStatus(error.message, 'danger');
        }
    });

    registeredUsersTableBody.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit-user]');
        const deleteButton = event.target.closest('[data-delete-user]');
        const assignmentsButton = event.target.closest('[data-view-user-assignments]');

        if (assignmentsButton) {
            openUserAssignmentsModal(assignmentsButton.dataset.viewUserAssignments);
            return;
        }

        if (editButton) {
            populateUserEditForm(editButton.dataset.editUser);
            return;
        }

        if (!deleteButton) {
            return;
        }

        const confirmed = await openConfirmModal({
            title: 'Eliminar usuario',
            message: 'Se eliminara el usuario seleccionado. Esta accion no se puede deshacer.',
            confirmText: 'Eliminar',
            confirmClass: 'btn btn-danger',
        });

        if (!confirmed) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('usuario_id', deleteButton.dataset.deleteUser);
        showAdminStatus('Eliminando usuario...', 'secondary');

        try {
            const result = await requestJson('./api/admin/users.php', {
                method: 'POST',
                body: formData,
            });

            userEditForm.reset();
            userEditPanel.classList.add('d-none');
            showAdminStatus(result.message, 'success');
            await loadAdminOverview();
        } catch (error) {
            showAdminStatus(error.message, 'danger');
        }
    });

    serviceAssignmentsTableBody.addEventListener('click', async (event) => {
        const toggleButton = event.target.closest('[data-toggle-service-users]');
        const openAssignButton = event.target.closest('[data-open-service-assign]');
        const pageNavButton = event.target.closest('[data-service-page-nav]');
        const unassignButton = event.target.closest('[data-unassign-id]');

        if (toggleButton) {
            const serviceId = Number(toggleButton.dataset.toggleServiceUsers);
            appState.expandedAssignmentServiceId = appState.expandedAssignmentServiceId === serviceId ? null : serviceId;
            renderServiceAssignmentTable();
            return;
        }

        if (openAssignButton) {
            openServiceAssignUsersModal(openAssignButton.dataset.openServiceAssign);
            return;
        }

        if (pageNavButton) {
            const state = getAssignmentTableState(pageNavButton.dataset.servicePageNav);
            state.page += pageNavButton.dataset.direction === 'next' ? 1 : -1;
            if (state.page < 1) {
                state.page = 1;
            }
            renderServiceAssignmentTable();
            return;
        }

        if (!unassignButton) {
            return;
        }

        const confirmed = await openConfirmModal({
            title: 'Desasignar cuenta',
            message: 'Se retirara esta cuenta del usuario seleccionado.',
            confirmText: 'Desasignar',
            confirmClass: 'btn btn-danger',
        });

        if (!confirmed) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'unassign');
        formData.append('assignment_id', unassignButton.dataset.unassignId);
        showAdminStatus('Desasignando cuenta...', 'secondary');

        try {
            const result = await requestJson('./api/admin/assignments.php', {
                method: 'POST',
                body: formData,
            });

            showAdminStatus(result.message, 'success');
            await loadAdminOverview();
        } catch (error) {
            showAdminStatus(error.message, 'danger');
        }
    });

    serviceAssignmentsTableBody.addEventListener('input', (event) => {
        const filterInput = event.target.closest('[data-service-filter]');

        if (!filterInput) {
            return;
        }

        const state = getAssignmentTableState(filterInput.dataset.serviceFilter);
        state.query = filterInput.value;
        state.page = 1;
        renderServiceAssignmentTable();
    });

    serviceAssignmentsTableBody.addEventListener('change', (event) => {
        const pageSizeSelect = event.target.closest('[data-service-page-size]');

        if (!pageSizeSelect) {
            return;
        }

        const state = getAssignmentTableState(pageSizeSelect.dataset.servicePageSize);
        state.pageSize = Number(pageSizeSelect.value) || 5;
        state.page = 1;
        renderServiceAssignmentTable();
    });

    serviceAssignAccountSelect.addEventListener('change', () => {
        renderServiceAssignUsersTable();
    });

    serviceAssignUserSearchInput.addEventListener('input', () => {
        renderServiceAssignUsersTable();
    });

    serviceAssignUsersTableBody.addEventListener('click', async (event) => {
        const assignButton = event.target.closest('[data-assign-user-id]');

        if (!assignButton) {
            return;
        }

        const serviceId = appState.selectedAssignServiceId;
        const selectedAccountId = serviceAssignAccountSelect.value;

        if (!selectedAccountId) {
            showAdminStatus('Debes seleccionar una cuenta del servicio antes de asignar usuarios.', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'assign');
        formData.append('usuario_id', assignButton.dataset.assignUserId);
        formData.append('cuenta_servicio_id', selectedAccountId);
        showAdminStatus('Asignando usuario al servicio...', 'secondary');

        try {
            const result = await requestJson('./api/admin/assignments.php', {
                method: 'POST',
                body: formData,
            });

            showAdminStatus(result.message, 'success');
            await loadAdminOverview();

            if (serviceId !== null) {
                openServiceAssignUsersModal(serviceId, selectedAccountId);
            }
        } catch (error) {
            showAdminStatus(error.message, 'danger');
        }
    });

    logoutButton.addEventListener('click', async () => {
        try {
            await requestJson('./api/logout.php', { method: 'POST' });
            leaveAdminMode();
            showStatus('Sesion cerrada.', 'secondary');
        } catch (error) {
            showAdminStatus(error.message, 'danger');
        }
    });

    bootstrapSession();
</script>
</body>
</html>