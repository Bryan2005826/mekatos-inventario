-- =============================================================
--  JOSTECH · Sistema de asistencia, planillas e inventario
--  Esquema MySQL / MariaDB — Etapa 1
--  Importar en phpMyAdmin sobre una base de datos vacía (utf8mb4).
--  Todas las fechas se guardan en UTC; la app las muestra en la
--  zona horaria configurada (America/Bogota por defecto).
-- =============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS configuracion (
  clave        VARCHAR(80)  NOT NULL PRIMARY KEY,
  valor        TEXT         NULL,
  actualizado  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS administradores (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario        VARCHAR(60)  NOT NULL UNIQUE,
  nombre         VARCHAR(120) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  ultimo_acceso  DATETIME     NULL,
  creado         DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trabajadores (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre               VARCHAR(80)  NOT NULL,
  documento            VARCHAR(30)  NULL,
  pin_hash             VARCHAR(255) NOT NULL,
  pin_generado         DATETIME     NOT NULL,
  activo               TINYINT(1)   NOT NULL DEFAULT 1,
  orden                INT          NOT NULL DEFAULT 0,
  color                VARCHAR(7)   NOT NULL DEFAULT '#1F6F5C',
  valor_hora           DECIMAL(12,2) NULL,          -- último valor de la hora usado
  pago_fijo            TINYINT(1)   NOT NULL DEFAULT 0,
  valor_fijo           DECIMAL(12,2) NULL,          -- último pago fijo usado
  consentimiento_datos DATETIME     NULL,           -- aceptación del tratamiento de datos (foto de evidencia)
  creado               DATETIME     NOT NULL,
  actualizado          DATETIME     NOT NULL,
  KEY idx_activo_orden (activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispositivos autorizados para registrar asistencia (kiosco del local)
CREATE TABLE IF NOT EXISTS dispositivos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(80)  NOT NULL,
  token_hash    CHAR(64)     NULL UNIQUE,          -- sha256 del token guardado en la cookie del kiosco
  codigo_hash   CHAR(64)     NULL,                 -- sha256 del código de activación (temporal)
  codigo_expira DATETIME     NULL,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  activado      DATETIME     NULL,
  ultimo_uso    DATETIME     NULL,
  ultima_ip     VARCHAR(45)  NULL,
  user_agent    VARCHAR(255) NULL,
  creado        DATETIME     NOT NULL,
  revocado      DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IP públicas de la conexión a internet del local (opcional)
CREATE TABLE IF NOT EXISTS redes_autorizadas (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip           VARCHAR(45)  NOT NULL UNIQUE,
  descripcion  VARCHAR(120) NULL,
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  creado       DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jornada = par entrada/salida. Las correcciones del administrador
-- se hacen aquí; las marcaciones originales nunca se modifican.
CREATE TABLE IF NOT EXISTS jornadas (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  trabajador_id       INT UNSIGNED NOT NULL,
  fecha               DATE         NOT NULL,       -- fecha local de la entrada
  entrada             DATETIME     NULL,           -- UTC
  salida              DATETIME     NULL,           -- UTC
  estado              ENUM('abierta','completa','incompleta','anulada') NOT NULL DEFAULT 'abierta',
  corregida           TINYINT(1)   NOT NULL DEFAULT 0,
  minutos_brutos      INT          NULL,
  minutos_descanso    INT          NULL,
  minutos_trabajados  INT          NULL,
  minutos_nocturnos   INT          NULL,
  observaciones       TEXT         NULL,
  creado              DATETIME     NOT NULL,
  actualizado         DATETIME     NOT NULL,
  KEY idx_trab_fecha (trabajador_id, fecha),
  KEY idx_estado (estado),
  CONSTRAINT fk_jornada_trab FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marcación = evento original e inmutable registrado en el kiosco
CREATE TABLE IF NOT EXISTS marcaciones (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  trabajador_id        INT UNSIGNED NOT NULL,
  jornada_id           INT UNSIGNED NULL,
  tipo                 ENUM('entrada','salida') NOT NULL,
  fecha_hora           DATETIME     NOT NULL,      -- hora del servidor en UTC
  origen               ENUM('kiosco','admin') NOT NULL DEFAULT 'kiosco',
  dispositivo_id       INT UNSIGNED NULL,
  ip                   VARCHAR(45)  NULL,
  verif_identidad      VARCHAR(40)  NOT NULL,      -- p.ej. 'pin_ok+foto'
  verif_ubicacion      VARCHAR(60)  NOT NULL,      -- p.ej. 'dispositivo_ok+red_ok'
  foto                 VARCHAR(120) NULL,          -- nombre del archivo en storage/fotos (privado)
  estado               ENUM('valida','anulada') NOT NULL DEFAULT 'valida',
  observaciones        TEXT         NULL,
  creado               DATETIME     NOT NULL,
  KEY idx_trab_fecha (trabajador_id, fecha_hora),
  KEY idx_jornada (jornada_id),
  CONSTRAINT fk_marc_trab FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id),
  CONSTRAINT fk_marc_jornada FOREIGN KEY (jornada_id) REFERENCES jornadas(id),
  CONSTRAINT fk_marc_disp FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos de registro rechazados (para revisión del administrador)
CREATE TABLE IF NOT EXISTS intentos_rechazados (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  trabajador_id   INT UNSIGNED NULL,
  accion          VARCHAR(30)  NOT NULL,           -- login, entrada, salida, admin_login
  motivo          VARCHAR(255) NOT NULL,
  ip              VARCHAR(45)  NULL,
  dispositivo_id  INT UNSIGNED NULL,
  user_agent      VARCHAR(255) NULL,
  creado          DATETIME     NOT NULL,
  KEY idx_creado (creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Control de intentos de inicio de sesión (bloqueo temporal)
CREATE TABLE IF NOT EXISTS limites_acceso (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  clave   VARCHAR(120) NOT NULL,
  creado  DATETIME     NOT NULL,
  KEY idx_clave_creado (clave, creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoría de toda acción administrativa
CREATE TABLE IF NOT EXISTS auditoria (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_tipo   ENUM('admin','trabajador','sistema') NOT NULL,
  actor_id     INT UNSIGNED NULL,
  actor_nombre VARCHAR(120) NULL,
  accion       VARCHAR(60)  NOT NULL,
  entidad      VARCHAR(40)  NULL,
  entidad_id   INT UNSIGNED NULL,
  motivo       TEXT         NULL,
  antes        TEXT         NULL,                  -- JSON
  despues      TEXT         NULL,                  -- JSON
  ip           VARCHAR(45)  NULL,
  creado       DATETIME     NOT NULL,
  KEY idx_creado (creado),
  KEY idx_entidad (entidad, entidad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
--  Etapa 2 · Planillas por quincena
--  Una planilla por trabajador y quincena (1-15 y 16-fin de mes).
--  Las horas se calculan siempre desde las jornadas; aquí se guarda
--  el valor de la hora, bonos, descuentos y el estado.
-- =============================================================
CREATE TABLE IF NOT EXISTS planillas (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  trabajador_id    INT UNSIGNED NOT NULL,
  periodo_inicio   DATE         NOT NULL,
  periodo_fin      DATE         NOT NULL,
  estado           ENUM('abierta','cerrada','pagada') NOT NULL DEFAULT 'abierta',
  valor_hora       DECIMAL(12,2) NOT NULL DEFAULT 0,
  pago_fijo        TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 = se paga un valor fijo, no por horas
  valor_fijo       DECIMAL(12,2) NOT NULL DEFAULT 0,
  bono_descripcion VARCHAR(120) NULL,
  bono_valor       DECIMAL(12,2) NOT NULL DEFAULT 0,
  desc_comida      DECIMAL(12,2) NOT NULL DEFAULT 0,
  desc_adelanto    DECIMAL(12,2) NOT NULL DEFAULT 0,
  desc_otros       DECIMAL(12,2) NOT NULL DEFAULT 0,
  observaciones    TEXT         NULL,
  minutos_cierre   INT          NULL,           -- foto de los minutos al cerrar (referencia)
  total_pagado     DECIMAL(12,2) NULL,          -- valor entregado al marcar como pagada
  cerrada          DATETIME     NULL,
  pagada           DATETIME     NULL,
  creado           DATETIME     NOT NULL,
  actualizado      DATETIME     NOT NULL,
  UNIQUE KEY uq_trab_periodo (trabajador_id, periodo_inicio),
  KEY idx_periodo (periodo_inicio),
  CONSTRAINT fk_planilla_trab FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
--  Etapa 3 · Inventario de loza y utensilios por áreas (QR)
-- =============================================================
CREATE TABLE IF NOT EXISTS areas (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(60)  NOT NULL,
  token     CHAR(24)     NOT NULL UNIQUE,      -- va en el QR; no es una contraseña
  orden     INT          NOT NULL DEFAULT 0,
  activo    TINYINT(1)   NOT NULL DEFAULT 1,
  creado    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventario_items (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  area_id            INT UNSIGNED NOT NULL,
  nombre             VARCHAR(80)  NOT NULL,
  tipo               ENUM('cantidad','estado') NOT NULL DEFAULT 'cantidad',
  cantidad_esperada  INT          NULL,
  foto               VARCHAR(120) NULL,
  orden              INT          NOT NULL DEFAULT 0,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  creado             DATETIME     NOT NULL,
  actualizado        DATETIME     NOT NULL,
  KEY idx_area (area_id, activo, orden),
  CONSTRAINT fk_item_area FOREIGN KEY (area_id) REFERENCES areas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventarios (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  area_id        INT UNSIGNED NOT NULL,
  trabajador_id  INT UNSIGNED NULL,              -- NULL cuando lo registra el administrador
  admin_id       INT UNSIGNED NULL,
  origen         ENUM('trabajador','admin') NOT NULL DEFAULT 'trabajador',
  es_base        TINYINT(1)   NOT NULL DEFAULT 0, -- este conteo fijó stock
  motivos_pendientes TINYINT(1) NOT NULL DEFAULT 0, -- hay diferencias que el trabajador aún no explicó
  fecha          DATE         NOT NULL,          -- fecha local del turno
  turno          ENUM('mediodia','noche') NOT NULL,
  estado         ENUM('borrador','enviado') NOT NULL DEFAULT 'borrador',
  enviado        DATETIME     NULL,              -- hora exacta del servidor (UTC)
  observaciones  TEXT         NULL,
  diferencias    INT          NOT NULL DEFAULT 0,
  pdf            VARCHAR(120) NULL,              -- archivo en storage/inventarios
  envio_estado   VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
  envio_detalle  TEXT         NULL,
  corregido      TINYINT(1)   NOT NULL DEFAULT 0,
  ip             VARCHAR(45)  NULL,
  creado         DATETIME     NOT NULL,
  actualizado    DATETIME     NOT NULL,
  KEY idx_fecha (fecha, area_id, turno),
  KEY idx_trab (trabajador_id),
  CONSTRAINT fk_inv_area FOREIGN KEY (area_id) REFERENCES areas(id),
  CONSTRAINT fk_inv_trab FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventario_lineas (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  inventario_id      INT UNSIGNED NOT NULL,
  item_id            INT UNSIGNED NULL,
  item_nombre        VARCHAR(80)  NOT NULL,         -- copia: el catálogo puede cambiar después
  tipo               ENUM('cantidad','estado') NOT NULL DEFAULT 'cantidad',
  cantidad_esperada  INT          NULL,
  cantidad           INT          NULL,
  estado             ENUM('bien','novedad','danado') NULL,
  observacion        VARCHAR(255) NULL,
  motivo_faltante    VARCHAR(20)  NULL,            -- roto, perdido, otra_area, en_uso, otro
  es_base            TINYINT(1)   NOT NULL DEFAULT 0, -- primer conteo: fija la cantidad esperada
  KEY idx_inv (inventario_id),
  CONSTRAINT fk_linea_inv FOREIGN KEY (inventario_id) REFERENCES inventarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
--  Etapa 4 · Envíos por correo y notificaciones
--  Solo se marca "enviado" cuando el servidor de correo acepta el mensaje.
-- =============================================================
CREATE TABLE IF NOT EXISTS envios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tipo            VARCHAR(30)  NOT NULL,          -- inventario, cierre_quincena, prueba
  referencia_id   INT UNSIGNED NULL,
  destinos        VARCHAR(500) NOT NULL,
  asunto          VARCHAR(200) NOT NULL,
  cuerpo_html     MEDIUMTEXT   NOT NULL,
  cuerpo_texto    MEDIUMTEXT   NOT NULL,
  adjunto         VARCHAR(160) NULL,              -- ruta relativa dentro de storage
  adjunto_nombre  VARCHAR(160) NULL,
  estado          ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  intentos        INT          NOT NULL DEFAULT 0,
  proximo_intento DATETIME     NULL,
  ultimo_error    VARCHAR(500) NULL,
  mensaje_id      VARCHAR(200) NULL,              -- identificador que devuelve el proveedor
  metodo          VARCHAR(20)  NULL,
  creado          DATETIME     NOT NULL,
  enviado         DATETIME     NULL,
  KEY idx_estado (estado, proximo_intento),
  KEY idx_ref (tipo, referencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movimientos del stock de loza (cantidad que debe haber de cada elemento)
CREATE TABLE IF NOT EXISTS stock_movimientos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  item_id        INT UNSIGNED NOT NULL,
  tipo           ENUM('base','baja','ingreso','ajuste','reinicio') NOT NULL,
  cantidad       INT          NOT NULL,           -- cambio (+/-)
  antes          INT          NULL,
  despues        INT          NULL,
  motivo         VARCHAR(255) NULL,
  inventario_id  INT UNSIGNED NULL,
  actor          VARCHAR(120) NULL,
  creado         DATETIME     NOT NULL,
  KEY idx_item (item_id, creado),
  CONSTRAINT fk_mov_item FOREIGN KEY (item_id) REFERENCES inventario_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
