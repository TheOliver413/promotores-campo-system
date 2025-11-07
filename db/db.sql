-- Tabla: roles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    permisos JSON
);

-- Tabla: clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_empresa VARCHAR(255) NOT NULL,
    contacto_email VARCHAR(255) UNIQUE,
    telefono VARCHAR(50),
    activo BOOLEAN DEFAULT true
);

-- Tabla: usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    cliente_id INT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    ultimo_acceso TIMESTAMP,
    reset_token VARCHAR(100),
    token_expires TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
);

-- Tabla: proyectos
CREATE TABLE proyectos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_proyecto VARCHAR(255) NOT NULL,
    fecha_inicio DATE,
    fecha_fin DATE,
    kpis JSON,
    configuraciones JSON,
    estado ENUM('planificado', 'activo', 'completado') DEFAULT 'planificado'
);

-- Tabla: proyecto_clientes (Relación Muchos a Muchos)
CREATE TABLE proyecto_clientes (
    proyecto_id INT NOT NULL,
    cliente_id INT NOT NULL,
    PRIMARY KEY (proyecto_id, cliente_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Tabla: proyecto_promotores (Relación Muchos a Muchos)
CREATE TABLE proyecto_promotores (
    proyecto_id INT NOT NULL,
    promotor_user_id INT NOT NULL,
    PRIMARY KEY (proyecto_id, promotor_user_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (promotor_user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla: tipos_actividad (Catálogo)
CREATE TABLE tipos_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    requiere_evidencia BOOLEAN DEFAULT true
);

-- Tabla: jornadas
CREATE TABLE jornadas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    promotor_user_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    check_in_time TIMESTAMP NOT NULL,
    check_in_lat DECIMAL(10, 8) NOT NULL,
    check_in_lon DECIMAL(11, 8) NOT NULL,
    check_in_foto_url VARCHAR(512) NULL,     -- CORRECCIÓN: Añadido NULL
    check_out_time TIMESTAMP NULL,           -- CORRECCIÓN: Añadido NULL
    check_out_lat DECIMAL(10, 8) NULL,      -- CORRECCIÓN: Añadido NULL
    check_out_lon DECIMAL(11, 8) NULL,      -- CORRECCIÓN: Añadido NULL
    check_out_foto_url VARCHAR(512) NULL,    -- CORRECCIÓN: Añadido NULL
    horas_calculadas DECIMAL(5, 2) NULL,    -- CORRECCIÓN: Añadido NULL
    estado_validacion ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
    supervisor_user_id INT NULL,            -- CORRECCIÓN: Añadido NULL
    motivo_rechazo TEXT NULL,               -- CORRECCIÓN: Añadido NULL
    fecha_jornada DATE NOT NULL,
    FOREIGN KEY (promotor_user_id) REFERENCES usuarios(id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id),
    FOREIGN KEY (supervisor_user_id) REFERENCES usuarios(id)
);

-- Tabla: actividades
CREATE TABLE actividades (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    jornada_id BIGINT NOT NULL,
    promotor_user_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    tipo_actividad_id INT NOT NULL,
    timestamp_actividad TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latitud DECIMAL(10, 8) NOT NULL,
    longitud DECIMAL(11, 8) NOT NULL,
    notas TEXT,
    estado_validacion ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
    supervisor_user_id INT,
    motivo_rechazo TEXT,
    dentro_geocerca BOOLEAN,
    FOREIGN KEY (jornada_id) REFERENCES jornadas(id) ON DELETE CASCADE,
    FOREIGN KEY (promotor_user_id) REFERENCES usuarios(id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id),
    FOREIGN KEY (tipo_actividad_id) REFERENCES tipos_actividad(id),
    FOREIGN KEY (supervisor_user_id) REFERENCES usuarios(id)
);

-- Tabla: evidencias
CREATE TABLE evidencias (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actividad_id BIGINT NOT NULL,
    tipo_archivo ENUM('foto', 'video', 'documento', 'audio') NOT NULL,
    url_archivo VARCHAR(512) NOT NULL,
    nombre_archivo VARCHAR(255),
    peso_kb INT,
    fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
);

-- Tabla: ubicaciones_tracking
CREATE TABLE ubicaciones_tracking (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    promotor_user_id INT NOT NULL,
    latitud DECIMAL(10, 8) NOT NULL,
    longitud DECIMAL(11, 8) NOT NULL,
    timestamp_gps TIMESTAMP NOT NULL,
    bateria_nivel INT,
    FOREIGN KEY (promotor_user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla: geocercas
CREATE TABLE geocercas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre_zona VARCHAR(255) NOT NULL,
    tipo_geometria ENUM('poligono', 'circulo') NOT NULL,
    coordenadas JSON NOT NULL, -- O usar tipo GEOMETRY si la BD lo soporta
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
);

-- Tabla: rutas_promotores
CREATE TABLE rutas_promotores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotor_user_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    nombre_ruta VARCHAR(255) NOT NULL,
    fecha_planificada DATE NOT NULL,
    puntos_ruta JSON NOT NULL,
    estado ENUM('pendiente', 'en_progreso', 'completada') DEFAULT 'pendiente',
    FOREIGN KEY (promotor_user_id) REFERENCES usuarios(id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id)
);

-- Tabla: notificaciones
CREATE TABLE notificaciones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    mensaje VARCHAR(500) NOT NULL,
    leido BOOLEAN DEFAULT false,
    tipo_notificacion ENUM('aprobacion', 'rechazo', 'recordatorio', 'mensaje') NOT NULL,
    referencia_id INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE mensajes_internos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    remitente_user_id INT NOT NULL,
    destinatario_user_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_lectura TIMESTAMP NULL,  -- CORRECCIÓN: Añadido NULL
    FOREIGN KEY (remitente_user_id) REFERENCES usuarios(id),
    FOREIGN KEY (destinatario_user_id) REFERENCES usuarios(id)
);

-- Tabla: auditoria
CREATE TABLE auditoria (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(255) NOT NULL,
    tabla_afectada VARCHAR(100),
    registro_afectado_id INT,
    detalles JSON,
    ip_address VARCHAR(45),
    timestamp_accion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla: configuraciones_globales
CREATE TABLE configuraciones_globales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor VARCHAR(512) NOT NULL,
    descripcion TEXT
);

-- Tabla: respaldos
CREATE TABLE respaldos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_almacenamiento VARCHAR(512) NOT NULL,
    peso_mb DECIMAL(10, 2) NOT NULL,    
    tipo_respaldo ENUM('automatico', 'manual') NOT NULL,
    usuario_id INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

