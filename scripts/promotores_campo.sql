-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 18, 2025 at 05:11 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u126046987_promotores_cam`
--

-- --------------------------------------------------------

--
-- Table structure for table `actividades`
--

CREATE TABLE `actividades` (
  `id` bigint(20) NOT NULL,
  `jornada_id` bigint(20) NOT NULL,
  `promotor_user_id` int(11) NOT NULL,
  `proyecto_id` int(11) NOT NULL,
  `tipo_actividad_id` int(11) NOT NULL,
  `timestamp_actividad` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `notas` text DEFAULT NULL,
  `estado_validacion` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  `supervisor_user_id` int(11) DEFAULT NULL,
  `motivo_rechazo` text DEFAULT NULL,
  `dentro_geocerca` tinyint(1) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auditoria`
--

CREATE TABLE `auditoria` (
  `id` bigint(20) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(255) NOT NULL,
  `tabla_afectada` varchar(100) DEFAULT NULL,
  `registro_afectado_id` int(11) DEFAULT NULL,
  `detalles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalles`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp_accion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre_empresa` varchar(255) NOT NULL,
  `contacto_email` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mapa_centro_lat` decimal(10,8) DEFAULT 4.57086800,
  `mapa_centro_lng` decimal(11,8) DEFAULT -74.29733300,
  `mapa_zoom` int(11) DEFAULT 6,
  `pais` varchar(100) DEFAULT 'Colombia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configuraciones_globales`
--

CREATE TABLE `configuraciones_globales` (
  `id` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `valor` varchar(512) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configuracion_smtp`
--

CREATE TABLE `configuracion_smtp` (
  `id` int(11) NOT NULL,
  `host` varchar(255) NOT NULL,
  `puerto` int(11) NOT NULL DEFAULT 587,
  `usuario` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `encriptacion` enum('tls','ssl','none') DEFAULT 'tls',
  `remitente_email` varchar(255) NOT NULL,
  `remitente_nombre` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emails_enviados`
--

CREATE TABLE `emails_enviados` (
  `id` bigint(20) NOT NULL,
  `destinatario_email` varchar(255) NOT NULL,
  `destinatario_nombre` varchar(255) DEFAULT NULL,
  `asunto` varchar(500) NOT NULL,
  `tipo_email` enum('notificacion','registro','reset_password','ruta_asignada','ruta_actualizada','validacion') NOT NULL,
  `estado` enum('enviado','fallido','pendiente') DEFAULT 'pendiente',
  `error_mensaje` text DEFAULT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Table structure for table `evidencias`
--

CREATE TABLE `evidencias` (
  `id` bigint(20) NOT NULL,
  `actividad_id` bigint(20) NOT NULL,
  `tipo_archivo` enum('foto','video','documento','audio') NOT NULL,
  `url_archivo` varchar(512) NOT NULL,
  `nombre_archivo` varchar(255) DEFAULT NULL,
  `peso_kb` int(11) DEFAULT NULL,
  `fecha_carga` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `geocercas`
--

CREATE TABLE `geocercas` (
  `id` int(11) NOT NULL,
  `proyecto_id` int(11) NOT NULL,
  `nombre_zona` varchar(255) NOT NULL,
  `tipo_geometria` enum('poligono','circulo') NOT NULL,
  `coordenadas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`coordenadas`)),
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jornadas`
--

CREATE TABLE `jornadas` (
  `id` bigint(20) NOT NULL,
  `promotor_user_id` int(11) NOT NULL,
  `proyecto_id` int(11) NOT NULL,
  `check_in_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `check_in_lat` decimal(10,8) NOT NULL,
  `check_in_lon` decimal(11,8) NOT NULL,
  `check_in_foto_url` varchar(512) DEFAULT NULL,
  `check_out_time` timestamp NULL DEFAULT NULL,
  `check_out_lat` decimal(10,8) DEFAULT NULL,
  `check_out_lon` decimal(11,8) DEFAULT NULL,
  `check_out_foto_url` varchar(512) DEFAULT NULL,
  `horas_calculadas` decimal(5,2) DEFAULT NULL,
  `estado_validacion` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  `supervisor_user_id` int(11) DEFAULT NULL,
  `motivo_rechazo` text DEFAULT NULL,
  `fecha_jornada` date NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mensajes_internos`
--

CREATE TABLE `mensajes_internos` (
  `id` bigint(20) NOT NULL,
  `remitente_user_id` int(11) NOT NULL,
  `destinatario_user_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_lectura` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` bigint(20) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `tipo_notificacion` enum('aprobacion','rechazo','recordatorio','mensaje','ruta_asignada','ruta_actualizada') NOT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proyectos`
--

CREATE TABLE `proyectos` (
  `id` int(11) NOT NULL,
  `nombre_proyecto` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `kpis` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`kpis`)),
  `configuraciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuraciones`)),
  `estado` enum('planificado','activo','completado') DEFAULT 'planificado',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- --------------------------------------------------------

--
-- Table structure for table `proyecto_clientes`
--

CREATE TABLE `proyecto_clientes` (
  `proyecto_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `proyecto_promotores`
--

CREATE TABLE `proyecto_promotores` (
  `proyecto_id` int(11) NOT NULL,
  `promotor_user_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `puntos_ruta`
--

CREATE TABLE `puntos_ruta` (
  `id` int(11) NOT NULL,
  `ruta_id` int(11) NOT NULL,
  `orden` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `direccion` text DEFAULT NULL,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `ubicacion_cliente_id` int(11) DEFAULT NULL,
  `tiempo_estimado_minutos` int(11) DEFAULT 30,
  `tiempo_real_minutos` int(11) DEFAULT NULL COMMENT 'Tiempo real (en minutos) que el promotor estuvo en el punto',
  `distancia_desde_anterior_km` decimal(8,2) DEFAULT NULL COMMENT 'Distancia en KM desde el punto de ruta anterior',
  `notas` text DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','completado','incompleto','no_visitado','reprogramado') NOT NULL DEFAULT 'pendiente' COMMENT 'Status of the visit point',
  `fecha_completado` timestamp NULL DEFAULT NULL,
  `visitado` tinyint(1) DEFAULT 0 COMMENT 'Si el punto fue visitado',
  `fecha_visita` datetime DEFAULT NULL COMMENT 'Fecha y hora de la visita',
  `evidencias` text DEFAULT NULL COMMENT 'JSON con las evidencias (fotos/documentos) del punto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `respaldos`
--

CREATE TABLE `respaldos` (
  `id` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_almacenamiento` varchar(512) NOT NULL,
  `peso_mb` decimal(10,2) NOT NULL,
  `tipo_respaldo` enum('automatico','manual') NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `permisos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permisos`)),
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `permisos`, `descripcion`, `fecha_registro`, `fecha_actualizacion`) VALUES
(1, 'Administrador', '{\"usuarios\": \"crud\", \"proyectos\": \"crud\", \"reportes\": \"view\"}', 'Control total del sistema', '2025-11-03 23:28:59', '2025-11-03 23:28:59'),
(2, 'Supervisor', '{\"promotores\": \"crud\", \"validacion\": \"crud\", \"reportes\": \"view\"}', 'Gestión de promotores y validación', '2025-11-03 23:28:59', '2025-11-03 23:28:59'),
(3, 'Promotor', '{\"jornadas\": \"crud\", \"actividades\": \"crud\"}', 'Ejecución en campo', '2025-11-03 23:28:59', '2025-11-03 23:28:59'),
(4, 'Cliente', '{\"reportes\": \"view\"}', 'Visualización de resultados', '2025-11-03 23:28:59', '2025-11-03 23:28:59');

-- --------------------------------------------------------

--
-- Table structure for table `rutas_promotores`
--

CREATE TABLE `rutas_promotores` (
  `id` int(11) NOT NULL,
  `promotor_user_id` int(11) NOT NULL,
  `proyecto_id` int(11) NOT NULL,
  `nombre_ruta` varchar(255) NOT NULL,
  `fecha_planificada` date NOT NULL,
  `puntos_ruta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`puntos_ruta`)),
  `estado` enum('pendiente','en_progreso','completada','pausada') DEFAULT 'pendiente',
  `hora_inicio_planificada` time DEFAULT NULL,
  `hora_inicio_real` timestamp NULL DEFAULT NULL,
  `hora_fin_real` timestamp NULL DEFAULT NULL,
  `latitud_inicio` decimal(10,8) DEFAULT NULL,
  `longitud_inicio` decimal(11,8) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ruta_optimizada` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ruta_optimizada`)),
  `distancia_total_km` decimal(10,2) DEFAULT NULL,
  `tiempo_total_minutos` int(11) DEFAULT NULL,
  `fecha_inicio_real` datetime DEFAULT NULL COMMENT 'Fecha y hora real de inicio de la ruta',
  `fecha_fin_real` datetime DEFAULT NULL COMMENT 'Fecha y hora real de fin de la ruta',
  `latitud_fin` decimal(10,8) DEFAULT NULL COMMENT 'Latitud donde se finalizó la ruta',
  `longitud_fin` decimal(11,8) DEFAULT NULL COMMENT 'Longitud donde se finalizó la ruta',
  `duracion_real_minutos` int(11) DEFAULT NULL COMMENT 'Duración real en minutos de la ruta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_promotores`
--

CREATE TABLE `supervisor_promotores` (
  `supervisor_id` int(11) NOT NULL,
  `promotor_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `tipos_actividad`
--

CREATE TABLE `tipos_actividad` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `requiere_evidencia` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ubicaciones_clientes`
--

CREATE TABLE `ubicaciones_clientes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `nombre_ubicacion` varchar(255) NOT NULL,
  `direccion` text NOT NULL,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `contacto_nombre` varchar(255) DEFAULT NULL,
  `contacto_telefono` varchar(50) DEFAULT NULL,
  `contacto_email` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ubicaciones_tracking`
--

CREATE TABLE `ubicaciones_tracking` (
  `id` bigint(20) NOT NULL,
  `promotor_user_id` int(11) NOT NULL,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `timestamp_gps` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `bateria_nivel` int(11) DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre_completo` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `token_expires` timestamp NULL DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_completo`, `email`, `telefono`, `password_hash`, `role_id`, `estado`, `deleted`, `ultimo_acceso`, `reset_token`, `token_expires`, `fecha_registro`, `fecha_actualizacion`) VALUES
(1, 'Oliver Admin', 'oliverborda04@outlook.com', '3213407384', '$2y$10$8i0l2l7Dvd4/KIZ0KNc8quoRskkJ0m64r/fCM83HMr5DIKPNDwXu.', 1, 'activo', 0, '2025-11-18 15:59:31', NULL, NULL, '2025-11-03 23:28:59', '2025-11-18 16:06:11')

-- --------------------------------------------------------

--
-- Table structure for table `usuario_clientes`
--

CREATE TABLE `usuario_clientes` (
  `usuario_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `actividades`
--
ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jornada_id` (`jornada_id`),
  ADD KEY `promotor_user_id` (`promotor_user_id`),
  ADD KEY `proyecto_id` (`proyecto_id`),
  ADD KEY `tipo_actividad_id` (`tipo_actividad_id`),
  ADD KEY `supervisor_user_id` (`supervisor_user_id`);

--
-- Indexes for table `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contacto_email` (`contacto_email`);

--
-- Indexes for table `configuraciones_globales`
--
ALTER TABLE `configuraciones_globales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indexes for table `configuracion_smtp`
--
ALTER TABLE `configuracion_smtp`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `emails_enviados`
--
ALTER TABLE `emails_enviados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_destinatario` (`destinatario_email`),
  ADD KEY `idx_tipo` (`tipo_email`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indexes for table `evidencias`
--
ALTER TABLE `evidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `actividad_id` (`actividad_id`);

--
-- Indexes for table `geocercas`
--
ALTER TABLE `geocercas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proyecto_id` (`proyecto_id`);

--
-- Indexes for table `jornadas`
--
ALTER TABLE `jornadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `promotor_user_id` (`promotor_user_id`),
  ADD KEY `proyecto_id` (`proyecto_id`),
  ADD KEY `supervisor_user_id` (`supervisor_user_id`);

--
-- Indexes for table `mensajes_internos`
--
ALTER TABLE `mensajes_internos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `remitente_user_id` (`remitente_user_id`),
  ADD KEY `destinatario_user_id` (`destinatario_user_id`);

--
-- Indexes for table `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indexes for table `proyectos`
--
ALTER TABLE `proyectos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proyecto_clientes`
--
ALTER TABLE `proyecto_clientes`
  ADD PRIMARY KEY (`proyecto_id`,`cliente_id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indexes for table `proyecto_promotores`
--
ALTER TABLE `proyecto_promotores`
  ADD PRIMARY KEY (`proyecto_id`,`promotor_user_id`),
  ADD KEY `promotor_user_id` (`promotor_user_id`);

--
-- Indexes for table `puntos_ruta`
--
ALTER TABLE `puntos_ruta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ubicacion_cliente_id` (`ubicacion_cliente_id`),
  ADD KEY `idx_ruta` (`ruta_id`),
  ADD KEY `idx_orden` (`ruta_id`,`orden`),
  ADD KEY `idx_puntos_estado` (`estado`),
  ADD KEY `idx_puntos_visitado` (`visitado`),
  ADD KEY `idx_puntos_ruta_id` (`ruta_id`);

--
-- Indexes for table `respaldos`
--
ALTER TABLE `respaldos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `rutas_promotores`
--
ALTER TABLE `rutas_promotores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `promotor_user_id` (`promotor_user_id`),
  ADD KEY `proyecto_id` (`proyecto_id`),
  ADD KEY `idx_rutas_fecha_inicio` (`fecha_inicio_real`),
  ADD KEY `idx_rutas_estado` (`estado`);

--
-- Indexes for table `supervisor_promotores`
--
ALTER TABLE `supervisor_promotores`
  ADD PRIMARY KEY (`supervisor_id`,`promotor_id`),
  ADD KEY `promotor_id` (`promotor_id`);

--
-- Indexes for table `tipos_actividad`
--
ALTER TABLE `tipos_actividad`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ubicaciones_clientes`
--
ALTER TABLE `ubicaciones_clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cliente` (`cliente_id`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indexes for table `ubicaciones_tracking`
--
ALTER TABLE `ubicaciones_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `promotor_user_id` (`promotor_user_id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `usuario_clientes`
--
ALTER TABLE `usuario_clientes`
  ADD PRIMARY KEY (`usuario_id`,`cliente_id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `actividades`
--
ALTER TABLE `actividades`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=217;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `configuraciones_globales`
--
ALTER TABLE `configuraciones_globales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `configuracion_smtp`
--
ALTER TABLE `configuracion_smtp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emails_enviados`
--
ALTER TABLE `emails_enviados`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `evidencias`
--
ALTER TABLE `evidencias`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `geocercas`
--
ALTER TABLE `geocercas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jornadas`
--
ALTER TABLE `jornadas`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `mensajes_internos`
--
ALTER TABLE `mensajes_internos`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `proyectos`
--
ALTER TABLE `proyectos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `puntos_ruta`
--
ALTER TABLE `puntos_ruta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `respaldos`
--
ALTER TABLE `respaldos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `rutas_promotores`
--
ALTER TABLE `rutas_promotores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tipos_actividad`
--
ALTER TABLE `tipos_actividad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ubicaciones_clientes`
--
ALTER TABLE `ubicaciones_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ubicaciones_tracking`
--
ALTER TABLE `ubicaciones_tracking`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `actividades`
--
ALTER TABLE `actividades`
  ADD CONSTRAINT `actividades_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `actividades_ibfk_2` FOREIGN KEY (`promotor_user_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `actividades_ibfk_3` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`),
  ADD CONSTRAINT `actividades_ibfk_4` FOREIGN KEY (`tipo_actividad_id`) REFERENCES `tipos_actividad` (`id`),
  ADD CONSTRAINT `actividades_ibfk_5` FOREIGN KEY (`supervisor_user_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `evidencias`
--
ALTER TABLE `evidencias`
  ADD CONSTRAINT `evidencias_ibfk_1` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `geocercas`
--
ALTER TABLE `geocercas`
  ADD CONSTRAINT `geocercas_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jornadas`
--
ALTER TABLE `jornadas`
  ADD CONSTRAINT `jornadas_ibfk_1` FOREIGN KEY (`promotor_user_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `jornadas_ibfk_2` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`),
  ADD CONSTRAINT `jornadas_ibfk_3` FOREIGN KEY (`supervisor_user_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `mensajes_internos`
--
ALTER TABLE `mensajes_internos`
  ADD CONSTRAINT `mensajes_internos_ibfk_1` FOREIGN KEY (`remitente_user_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `mensajes_internos_ibfk_2` FOREIGN KEY (`destinatario_user_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proyecto_clientes`
--
ALTER TABLE `proyecto_clientes`
  ADD CONSTRAINT `proyecto_clientes_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proyecto_clientes_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proyecto_promotores`
--
ALTER TABLE `proyecto_promotores`
  ADD CONSTRAINT `proyecto_promotores_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proyecto_promotores_ibfk_2` FOREIGN KEY (`promotor_user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `puntos_ruta`
--
ALTER TABLE `puntos_ruta`
  ADD CONSTRAINT `puntos_ruta_ibfk_1` FOREIGN KEY (`ruta_id`) REFERENCES `rutas_promotores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `puntos_ruta_ibfk_2` FOREIGN KEY (`ubicacion_cliente_id`) REFERENCES `ubicaciones_clientes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `respaldos`
--
ALTER TABLE `respaldos`
  ADD CONSTRAINT `respaldos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `rutas_promotores`
--
ALTER TABLE `rutas_promotores`
  ADD CONSTRAINT `rutas_promotores_ibfk_1` FOREIGN KEY (`promotor_user_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `rutas_promotores_ibfk_2` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`);

--
-- Constraints for table `supervisor_promotores`
--
ALTER TABLE `supervisor_promotores`
  ADD CONSTRAINT `supervisor_promotores_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supervisor_promotores_ibfk_2` FOREIGN KEY (`promotor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ubicaciones_clientes`
--
ALTER TABLE `ubicaciones_clientes`
  ADD CONSTRAINT `ubicaciones_clientes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ubicaciones_tracking`
--
ALTER TABLE `ubicaciones_tracking`
  ADD CONSTRAINT `ubicaciones_tracking_ibfk_1` FOREIGN KEY (`promotor_user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `usuario_clientes`
--
ALTER TABLE `usuario_clientes`
  ADD CONSTRAINT `usuario_clientes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_clientes_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
