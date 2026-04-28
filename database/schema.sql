CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    username VARCHAR(60) NOT NULL,
    email VARCHAR(190) NOT NULL,
    telefono VARCHAR(30) NULL,
    nombre_tienda VARCHAR(160) NULL,
    facebook VARCHAR(255) NULL,
    instagram VARCHAR(255) NULL,
    tiktok VARCHAR(255) NULL,
    whatsapp VARCHAR(255) NULL,
    telegram VARCHAR(255) NULL,
    foto_perfil_url VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_username (username),
    UNIQUE KEY uq_usuarios_email (email),
    KEY idx_usuarios_role (role),
    KEY idx_usuarios_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    logo_url VARCHAR(255) NULL,
    color_destacado VARCHAR(20) NOT NULL DEFAULT '#0b57d0',
    descripcion TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_servicios_nombre (nombre),
    UNIQUE KEY uq_servicios_slug (slug),
    KEY idx_servicios_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cuentas_servicio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    servicio_id BIGINT UNSIGNED NOT NULL,
    correo_acceso VARCHAR(190) NOT NULL,
    password_acceso VARCHAR(255) NOT NULL,
    descripcion TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cuentas_servicio_correo (servicio_id, correo_acceso),
    KEY idx_cuentas_servicio_servicio (servicio_id),
    KEY idx_cuentas_servicio_activo (activo),
    CONSTRAINT fk_cuentas_servicio_servicio FOREIGN KEY (servicio_id) REFERENCES servicios (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_cuentas_servicio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    cuenta_servicio_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_cuenta_servicio (usuario_id, cuenta_servicio_id),
    KEY idx_usuario_cuentas_usuario (usuario_id),
    KEY idx_usuario_cuentas_cuenta (cuenta_servicio_id),
    CONSTRAINT fk_usuario_cuentas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_cuentas_cuenta FOREIGN KEY (cuenta_servicio_id) REFERENCES cuentas_servicio (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracion_correo (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    imap_mailbox VARCHAR(255) NOT NULL,
    imap_user VARCHAR(190) NOT NULL,
    imap_password VARCHAR(255) NOT NULL,
    delay_days INT UNSIGNED NOT NULL DEFAULT 0,
    delay_minutes INT UNSIGNED NOT NULL DEFAULT 20,
    max_messages INT UNSIGNED NOT NULL DEFAULT 20,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracion_admin (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    nombre_pagina VARCHAR(160) NOT NULL DEFAULT 'Prycorreos',
    logo_url VARCHAR(255) NULL,
    bar_color VARCHAR(20) NOT NULL DEFAULT '#0b57d0',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    selector CHAR(18) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_plain CHAR(64) NULL,
    expires_at DATETIME NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_selector (selector),
    KEY idx_password_reset_user (user_id),
    KEY idx_password_reset_expires (expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS galeria_slides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    image_url VARCHAR(255) NOT NULL,
    texto VARCHAR(255) NULL,
    enlace VARCHAR(500) NULL,
    open_in_new_tab TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;