-- Datos de prueba para el sistema de gestión de promotores

-- 1. Insertar Roles
INSERT INTO roles (nombre, descripcion, permisos) VALUES
('Administrador', 'Control total del sistema', '{"usuarios": "crud", "proyectos": "crud", "reportes": "view"}'),
('Supervisor', 'Gestión de promotores y validación', '{"promotores": "crud", "validacion": "crud", "reportes": "view"}'),
('Promotor', 'Ejecución en campo', '{"jornadas": "crud", "actividades": "crud"}'),
('Cliente', 'Visualización de resultados', '{"reportes": "view"}');

-- 2. Insertar Usuarios (contraseña: password123 - hash MD5 para prueba)
INSERT INTO usuarios (nombre_completo, email, password_hash, telefono, role_id, activo) VALUES
-- Administrador
('Juan Pérez Admin', 'admin@sistema.com', MD5('password123'), '3001234567', 1, 1),

-- Supervisores
('María García Supervisor', 'supervisor1@sistema.com', MD5('password123'), '3002345678', 2, 1),
('Carlos López Supervisor', 'supervisor2@sistema.com', MD5('password123'), '3003456789', 2, 1),

-- Promotores
('Ana Martínez Promotor', 'promotor1@sistema.com', MD5('password123'), '3004567890', 3, 1),
('Luis Rodríguez Promotor', 'promotor2@sistema.com', MD5('password123'), '3005678901', 3, 1),
('Sofia Torres Promotor', 'promotor3@sistema.com', MD5('password123'), '3006789012', 3, 1),
('Diego Ramírez Promotor', 'promotor4@sistema.com', MD5('password123'), '3007890123', 3, 1),

-- Clientes
('Empresa ABC Cliente', 'cliente1@empresa.com', MD5('password123'), '3008901234', 4, 1),
('Corporación XYZ Cliente', 'cliente2@empresa.com', MD5('password123'), '3009012345', 4, 1);

-- 3. Insertar Clientes (tabla separada)
INSERT INTO clientes (nombre_empresa, contacto_principal, email, telefono, direccion, user_id) VALUES
('Empresa ABC S.A.S', 'Roberto Gómez', 'cliente1@empresa.com', '3008901234', 'Calle 100 #15-20, Bogotá', 8),
('Corporación XYZ Ltda', 'Patricia Sánchez', 'cliente2@empresa.com', '3009012345', 'Carrera 7 #32-16, Bogotá', 9);

-- 4. Insertar Tipos de Actividad
INSERT INTO tipos_actividad (nombre, descripcion, requiere_evidencia) VALUES
('Visita a Cliente', 'Visita comercial a punto de venta', 1),
('Merchandising', 'Organización y exhibición de productos', 1),
('Inventario', 'Conteo de productos en punto de venta', 1),
('Capacitación', 'Capacitación a personal del punto de venta', 0),
('Promoción', 'Actividad promocional en punto de venta', 1),
('Auditoría', 'Auditoría de cumplimiento de estándares', 1);

-- 5. Insertar Proyectos
INSERT INTO proyectos (nombre, descripcion, fecha_inicio, fecha_fin, kpis, estado) VALUES
('Campaña Verano 2025', 'Campaña de promoción de productos de verano', '2025-01-01', '2025-03-31', 
 '{"visitas_mes": 100, "cobertura": 80, "cumplimiento_ruta": 90}', 'Activo'),
('Expansión Zona Norte', 'Expansión de cobertura en zona norte de la ciudad', '2025-02-01', '2025-06-30',
 '{"nuevos_puntos": 50, "visitas_mes": 150}', 'Activo'),
('Auditoría Nacional', 'Auditoría de puntos de venta a nivel nacional', '2025-01-15', '2025-12-31',
 '{"puntos_auditados": 500, "cumplimiento": 95}', 'Activo');

-- 6. Asignar Clientes a Proyectos
INSERT INTO proyecto_clientes (proyecto_id, cliente_id) VALUES
(1, 1), -- Empresa ABC en Campaña Verano
(2, 1), -- Empresa ABC en Expansión Norte
(3, 2); -- Corporación XYZ en Auditoría Nacional

-- 7. Asignar Promotores a Proyectos
INSERT INTO proyecto_promotores (proyecto_id, promotor_id, fecha_asignacion) VALUES
(1, 4, '2025-01-01'), -- Ana en Campaña Verano
(1, 5, '2025-01-01'), -- Luis en Campaña Verano
(2, 6, '2025-02-01'), -- Sofia en Expansión Norte
(2, 7, '2025-02-01'), -- Diego en Expansión Norte
(3, 4, '2025-01-15'), -- Ana en Auditoría
(3, 5, '2025-01-15'); -- Luis en Auditoría

-- 8. Insertar Rutas para Promotores
INSERT INTO rutas_promotores (promotor_id, proyecto_id, nombre_ruta, fecha_asignacion, puntos_ruta, estado) VALUES
(4, 1, 'Ruta Centro - Semana 1', '2025-01-06', 
 '[{"lat":4.6097,"lng":-74.0817,"nombre":"Centro Comercial Andino","direccion":"Carrera 11 #82-71"},
   {"lat":4.6533,"lng":-74.0836,"nombre":"Unicentro","direccion":"Avenida 15 #123-30"},
   {"lat":4.6764,"lng":-74.0480,"nombre":"Centro Comercial Santafé","direccion":"Calle 185 #45-03"}]', 
 'Asignado'),
(5, 1, 'Ruta Sur - Semana 1', '2025-01-06',
 '[{"lat":4.5709,"lng":-74.1274,"nombre":"Centro Mayor","direccion":"Avenida 68 #75A-50"},
   {"lat":4.6097,"lng":-74.1327,"nombre":"Gran Estación","direccion":"Calle 26 #62-47"}]',
 'Asignado'),
(6, 2, 'Ruta Norte - Expansión', '2025-02-01',
 '[{"lat":4.7110,"lng":-74.0721,"nombre":"Centro Comercial Bima","direccion":"Calle 170 #64-60"},
   {"lat":4.7588,"lng":-74.0310,"nombre":"Palatino","direccion":"Autopista Norte #232-35"}]',
 'Asignado');

-- 9. Insertar Jornadas de ejemplo
INSERT INTO jornadas (promotor_id, fecha, check_in_hora, check_in_latitud, check_in_longitud, 
                      check_in_foto_url, check_out_hora, check_out_latitud, check_out_longitud, 
                      horas_calculadas, estado_validacion) VALUES
(4, '2025-01-06', '08:00:00', 4.6097, -74.0817, '/uploads/checkin_1.jpg', 
 '17:00:00', 4.6097, -74.0820, 9.0, 'Aprobado'),
(4, '2025-01-07', '08:15:00', 4.6100, -74.0815, '/uploads/checkin_2.jpg',
 '17:30:00', 4.6105, -74.0825, 9.25, 'Pendiente'),
(5, '2025-01-06', '07:45:00', 4.5709, -74.1274, '/uploads/checkin_3.jpg',
 '16:45:00', 4.5710, -74.1280, 9.0, 'Aprobado'),
(6, '2025-02-01', '08:30:00', 4.7110, -74.0721, '/uploads/checkin_4.jpg',
 '18:00:00', 4.7115, -74.0725, 9.5, 'Pendiente');

-- 10. Insertar Actividades
INSERT INTO actividades (promotor_id, tipo_actividad_id, descripcion, fecha_hora, 
                         latitud, longitud, estado_validacion) VALUES
(4, 1, 'Visita a Centro Comercial Andino - Reunión con gerente', '2025-01-06 09:30:00',
 4.6097, -74.0817, 'Aprobado'),
(4, 2, 'Merchandising en punto de venta principal', '2025-01-06 11:00:00',
 4.6097, -74.0817, 'Aprobado'),
(4, 5, 'Promoción de productos nuevos', '2025-01-06 14:00:00',
 4.6533, -74.0836, 'Pendiente'),
(5, 1, 'Visita a Centro Mayor', '2025-01-06 10:00:00',
 4.5709, -74.1274, 'Aprobado'),
(5, 3, 'Inventario de productos', '2025-01-06 13:00:00',
 4.5709, -74.1274, 'Aprobado');

-- 11. Insertar Evidencias
INSERT INTO evidencias (actividad_id, tipo, url) VALUES
(1, 'Foto', '/uploads/evidencia_1_1.jpg'),
(1, 'Foto', '/uploads/evidencia_1_2.jpg'),
(2, 'Foto', '/uploads/evidencia_2_1.jpg'),
(2, 'Foto', '/uploads/evidencia_2_2.jpg'),
(2, 'Foto', '/uploads/evidencia_2_3.jpg'),
(3, 'Foto', '/uploads/evidencia_3_1.jpg'),
(4, 'Foto', '/uploads/evidencia_4_1.jpg'),
(5, 'Foto', '/uploads/evidencia_5_1.jpg');

-- 12. Insertar Notificaciones
INSERT INTO notificaciones (usuario_id, tipo, mensaje, fecha_envio, leido) VALUES
(4, 'Aprobación', 'Tu jornada del 06/01/2025 ha sido aprobada', '2025-01-07 09:00:00', 1),
(4, 'Aprobación', 'Tu actividad "Visita a Centro Comercial Andino" ha sido aprobada', '2025-01-07 09:05:00', 1),
(5, 'Aprobación', 'Tu jornada del 06/01/2025 ha sido aprobada', '2025-01-07 09:10:00', 0),
(4, 'Sistema', 'Nueva ruta asignada: Ruta Centro - Semana 2', '2025-01-08 08:00:00', 0);

-- 13. Insertar Reportes Mensuales
INSERT INTO reportes_mensuales (proyecto_id, mes, anio, total_jornadas, total_actividades, 
                                horas_trabajadas, cumplimiento_ruta, observaciones) VALUES
(1, 1, 2025, 45, 120, 405.0, 92.5, 'Excelente desempeño en la primera semana'),
(2, 2, 2025, 30, 85, 270.0, 88.0, 'Inicio de expansión según lo planificado'),
(3, 1, 2025, 25, 75, 225.0, 95.0, 'Auditorías completadas satisfactoriamente');

-- 14. Insertar Configuraciones Globales
INSERT INTO configuraciones_globales (clave, valor, descripcion) VALUES
('horas_minimas_jornada', '8', 'Horas mínimas requeridas por jornada'),
('radio_checkin_metros', '100', 'Radio permitido para check-in en metros'),
('dias_validacion', '3', 'Días máximos para validar una jornada'),
('notificaciones_email', 'true', 'Enviar notificaciones por email'),
('max_evidencias_actividad', '5', 'Máximo de evidencias por actividad');

-- 15. Insertar registros de Auditoría
INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, detalles) VALUES
(1, 'Crear', 'proyectos', 1, '{"nombre": "Campaña Verano 2025"}'),
(2, 'Aprobar', 'jornadas', 1, '{"promotor_id": 4, "fecha": "2025-01-06"}'),
(4, 'Check-in', 'jornadas', 1, '{"latitud": 4.6097, "longitud": -74.0817}'),
(4, 'Check-out', 'jornadas', 1, '{"horas": 9.0}'),
(2, 'Aprobar', 'actividades', 1, '{"tipo": "Visita a Cliente"}');

-- Resumen de datos de prueba:
-- ✓ 1 Administrador: admin@sistema.com / password123
-- ✓ 2 Supervisores: supervisor1@sistema.com, supervisor2@sistema.com / password123
-- ✓ 4 Promotores: promotor1@sistema.com hasta promotor4@sistema.com / password123
-- ✓ 2 Clientes: cliente1@empresa.com, cliente2@empresa.com / password123
-- ✓ 3 Proyectos activos con asignaciones
-- ✓ Rutas con coordenadas GPS reales de Bogotá
-- ✓ Jornadas y actividades de ejemplo
-- ✓ Evidencias y notificaciones
