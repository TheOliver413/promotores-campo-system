<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'estado_promotores':
            $stmt = $db->prepare("
                SELECT 
                    u.id as user_id,
                    u.nombre_completo,
                    u.email,
                    u.telefono,
                    -- Determinar estado (activo si tiene jornada sin checkout, inactivo si no)
                    CASE 
                        WHEN j.id IS NOT NULL AND j.check_out_time IS NULL THEN 'activo'
                        ELSE 'inactivo'
                    END as estado,
                    -- Jornada actual
                    j.id as jornada_actual,
                    j.check_in_time as jornada_checkin,
                    TIMESTAMPDIFF(MINUTE, j.check_in_time, NOW()) as jornada_minutos,
                    j.check_in_lat as jornada_lat_inicio,
                    j.check_in_lon as jornada_long_inicio,
                    -- Ruta activa
                    r.id as ruta_activa,
                    r.nombre_ruta as ruta_nombre,
                    r.estado as ruta_estado,
                    r.puntos_ruta as puntos_json,
                    -- Cambio: Contar puntos reales desde JSON en lugar de tabla inexistente
                    (SELECT COUNT(*) FROM rutas_promotores WHERE promotor_user_id = u.id AND estado IN ('pendiente', 'en_progreso')) as ruta_total,
                    -- Última ubicación ahora desde ubicaciones_tracking (datos GPS real) no actividades
                    (SELECT latitud FROM ubicaciones_tracking WHERE promotor_user_id = u.id ORDER BY timestamp_gps DESC LIMIT 1) as ultima_latitud,
                    (SELECT longitud FROM ubicaciones_tracking WHERE promotor_user_id = u.id ORDER BY timestamp_gps DESC LIMIT 1) as ultima_longitud,
                    (SELECT timestamp_gps FROM ubicaciones_tracking WHERE promotor_user_id = u.id ORDER BY timestamp_gps DESC LIMIT 1) as ultima_ubicacion_fecha,
                    -- Última actividad con manejo correcto de nulos
                    (SELECT COALESCE(ta.nombre, 'Sin tipo') FROM actividades a 
                     LEFT JOIN tipos_actividad ta ON a.tipo_actividad_id = ta.id 
                     WHERE a.promotor_user_id = u.id 
                     ORDER BY a.timestamp_actividad DESC LIMIT 1) as ultima_actividad_tipo,
                    (SELECT a.timestamp_actividad FROM actividades a WHERE a.promotor_user_id = u.id ORDER BY a.timestamp_actividad DESC LIMIT 1) as ultima_actividad_fecha
                FROM usuarios u
                INNER JOIN supervisor_promotores sp ON u.id = sp.promotor_id
                LEFT JOIN jornadas j ON u.id = j.promotor_user_id 
                    AND j.check_out_time IS NULL
                    AND DATE(j.check_in_time) = CURDATE()
                LEFT JOIN rutas_promotores r ON u.id = r.promotor_user_id 
                    AND r.estado IN ('pendiente', 'en_progreso')
                WHERE sp.supervisor_id = ?
                ORDER BY u.nombre_completo
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $promotores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear datos
            $activos = 0;
            $pausados = 0;
            $inactivos = 0;
            $rutasActivas = 0;

            foreach ($promotores as &$p) {
                // Formatear duración de jornada
                if ($p['jornada_minutos']) {
                    $horas = floor($p['jornada_minutos'] / 60);
                    $minutos = $p['jornada_minutos'] % 60;
                    $p['jornada_duracion'] = sprintf('%02d:%02d hrs', $horas, $minutos);
                }

                $ruta_visitados = 0;
                if ($p['puntos_json']) {
                    $puntos = json_decode($p['puntos_json'], true);
                    if (is_array($puntos)) {
                        $ruta_visitados = count($puntos);
                    }
                }

                // Formatear progreso de ruta
                if ($p['ruta_activa']) {
                    $progreso = $p['ruta_total'] > 0 ? round(($ruta_visitados / $p['ruta_total']) * 100) : 0;
                    $p['ruta_progreso'] = "{$ruta_visitados}/{$p['ruta_total']} puntos ({$progreso}%)";
                    $rutasActivas++;
                }

                // Formatear tiempo de última actividad
                if ($p['ultima_actividad_fecha']) {
                    $fecha = new DateTime($p['ultima_actividad_fecha']);
                    $ahora = new DateTime();
                    $diff = $ahora->diff($fecha);

                    if ($diff->h > 0) {
                        $p['ultima_actividad_tiempo'] = "Hace {$diff->h}h {$diff->i}m";
                    } else if ($diff->i > 0) {
                        $p['ultima_actividad_tiempo'] = "Hace {$diff->i}m";
                    } else {
                        $p['ultima_actividad_tiempo'] = "Hace unos segundos";
                    }
                }

                // Formatear tiempo de última ubicación
                if ($p['ultima_ubicacion_fecha']) {
                    $fecha = new DateTime($p['ultima_ubicacion_fecha']);
                    $ahora = new DateTime();
                    $diff = $ahora->diff($fecha);

                    if ($diff->h > 0) {
                        $p['ultima_ubicacion_tiempo'] = "Hace {$diff->h}h {$diff->i}m";
                    } else if ($diff->i > 0) {
                        $p['ultima_ubicacion_tiempo'] = "Hace {$diff->i}m";
                    } else {
                        $p['ultima_ubicacion_tiempo'] = "Hace unos segundos";
                    }
                }

                // Contar estados
                if ($p['estado'] === 'activo') $activos++;
                else if ($p['estado'] === 'pausa') $pausados++;
                else $inactivos++;
            }

            $resumen = [
                'activos' => $activos,
                'pausados' => $pausados,
                'inactivos' => $inactivos,
                'rutas_activas' => $rutasActivas
            ];

            echo json_encode([
                'success' => true,
                'promotores' => $promotores,
                'resumen' => $resumen
            ]);
            break;

        case 'detalle_promotor':
            $userId = $_GET['id'] ?? 0;

            // Verificar que el supervisor tenga acceso a este promotor
            $stmt = $db->prepare("SELECT 1 FROM supervisor_promotores WHERE supervisor_id = ? AND promotor_id = ?");
            $stmt->execute([$_SESSION['user_id'], $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('No tiene acceso a este promotor');
            }

            $stmt = $db->prepare("
                SELECT 
                    u.*,
                    CASE 
                        WHEN j.id IS NOT NULL AND j.check_out_time IS NULL THEN 'activo'
                        ELSE 'inactivo'
                    END as estado,
                    j.id as jornada_actual,
                    j.check_in_time as jornada_checkin,
                    TIMESTAMPDIFF(MINUTE, j.check_in_time, NOW()) as jornada_minutos,
                    j.check_in_lat as jornada_lat_inicio,
                    j.check_in_lon as jornada_long_inicio,
                    r.id as ruta_activa,
                    r.nombre_ruta as ruta_nombre,
                    r.puntos_ruta as puntos_json,
                    (SELECT COUNT(*) FROM rutas_promotores WHERE promotor_user_id = u.id AND estado IN ('pendiente', 'en_progreso')) as ruta_total,
                    (SELECT latitud FROM ubicaciones_tracking WHERE promotor_user_id = u.id ORDER BY timestamp_gps DESC LIMIT 1) as ultima_latitud,
                    (SELECT longitud FROM ubicaciones_tracking WHERE promotor_user_id = u.id ORDER BY timestamp_gps DESC LIMIT 1) as ultima_longitud
                FROM usuarios u
                LEFT JOIN jornadas j ON u.id = j.promotor_user_id 
                    AND j.check_out_time IS NULL
                    AND DATE(j.check_in_time) = CURDATE()
                LEFT JOIN rutas_promotores r ON u.id = r.promotor_user_id 
                    AND r.estado IN ('pendiente', 'en_progreso')
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $promotor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$promotor) {
                throw new Exception('Promotor no encontrado');
            }

            // Formatear duración
            if ($promotor['jornada_minutos']) {
                $horas = floor($promotor['jornada_minutos'] / 60);
                $minutos = $promotor['jornada_minutos'] % 60;
                $promotor['jornada_duracion'] = sprintf('%02d:%02d hrs', $horas, $minutos);
            }

            $ruta_visitados = 0;
            if ($promotor['puntos_json']) {
                $puntos = json_decode($promotor['puntos_json'], true);
                if (is_array($puntos)) {
                    $ruta_visitados = count($puntos);
                }
            }

            // Formatear progreso de ruta
            if ($promotor['ruta_activa']) {
                $progreso = $promotor['ruta_total'] > 0 ? round(($ruta_visitados / $promotor['ruta_total']) * 100) : 0;
                $promotor['ruta_progreso'] = "{$ruta_visitados}/{$promotor['ruta_total']} puntos ({$progreso}%)";
            }

            // Obtener actividades de hoy
            $stmt = $db->prepare("
                SELECT 
                    a.id,
                    TIME(a.timestamp_actividad) as hora,
                    COALESCE(ta.nombre, 'Sin tipo') as tipo,
                    a.notas as descripcion,
                    a.estado_validacion
                FROM actividades a
                LEFT JOIN tipos_actividad ta ON a.tipo_actividad_id = ta.id
                WHERE a.promotor_user_id = ?
                AND DATE(a.timestamp_actividad) = CURDATE()
                ORDER BY a.timestamp_actividad DESC
            ");
            $stmt->execute([$userId]);
            $promotor['actividades_hoy'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'promotor' => $promotor
            ]);
            break;

        case 'ubicacion_tiempo_real':
            $userId = $_GET['id'] ?? 0;

            // Verificar acceso
            $stmt = $db->prepare("SELECT 1 FROM supervisor_promotores WHERE supervisor_id = ? AND promotor_id = ?");
            $stmt->execute([$_SESSION['user_id'], $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('No tiene acceso');
            }

            // Obtener últimas 50 ubicaciones para trazar el recorrido
            $stmt = $db->prepare("
                SELECT 
                    latitud,
                    longitud,
                    timestamp_gps,
                    bateria_nivel
                FROM ubicaciones_tracking
                WHERE promotor_user_id = ?
                ORDER BY timestamp_gps DESC
                LIMIT 50
            ");
            $stmt->execute([$userId]);
            $ubicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'ubicaciones' => array_reverse($ubicaciones)
            ]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
