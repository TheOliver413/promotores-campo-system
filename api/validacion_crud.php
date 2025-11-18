<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Notificacion.php';
require_once '../db/Auditoria.php';

header('Content-Type: application/json');

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$notificacionModel = new Notificacion();
$auditoriaModel = new Auditoria();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    $input = $_GET;
}

try {
    switch ($action) {
        case 'promotores':
            // Obtener promotores bajo supervisión
            $stmt = $db->prepare("SELECT u.id as user_id, u.nombre_completo FROM usuarios u INNER JOIN supervisor_promotores sp ON u.id = sp.promotor_id WHERE sp.supervisor_id = ? ORDER BY u.nombre_completo");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'jornadas':
            // Obtener jornadas para validación
            $promotor = $_GET['promotor'] ?? '';
            $estado = $_GET['estado'] ?? 'pendiente';
            $fechaDesde = $_GET['fecha_desde'] ?? '';
            $fechaHasta = $_GET['fecha_hasta'] ?? '';

            $sql = "SELECT j.id as jornada_id, j.promotor_user_id, j.check_in_time, j.check_out_time, j.horas_calculadas, j.estado_validacion, u.nombre_completo as nombre_promotor FROM jornadas j INNER JOIN usuarios u ON j.promotor_user_id = u.id INNER JOIN supervisor_promotores sp ON j.promotor_user_id = sp.promotor_id WHERE sp.supervisor_id = ?";

            $params = [$_SESSION['user_id']];

            if ($promotor) {
                $sql .= " AND j.promotor_user_id = ?";
                $params[] = $promotor;
            }

            if ($estado) {
                $sql .= " AND LOWER(j.estado_validacion) = ?";
                $params[] = strtolower($estado);
            }

            if ($fechaDesde) {
                $sql .= " AND DATE(j.check_in_time) >= ?";
                $params[] = $fechaDesde;
            }

            if ($fechaHasta) {
                $sql .= " AND DATE(j.check_in_time) <= ?";
                $params[] = $fechaHasta;
            }

            $sql .= " ORDER BY j.check_in_time DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'actividades':
            // Obtener actividades para validación
            $promotor = $_GET['promotor'] ?? '';
            $estado = $_GET['estado'] ?? 'pendiente';
            $fechaDesde = $_GET['fecha_desde'] ?? '';
            $fechaHasta = $_GET['fecha_hasta'] ?? '';

            $sql = "SELECT a.id as actividad_id, a.promotor_user_id, a.timestamp_actividad as fecha_actividad, a.latitud, a.longitud, a.estado_validacion, a.notas as descripcion, u.nombre_completo as nombre_promotor, ta.nombre as tipo_actividad FROM actividades a INNER JOIN usuarios u ON a.promotor_user_id = u.id INNER JOIN tipos_actividad ta ON a.tipo_actividad_id = ta.id INNER JOIN supervisor_promotores sp ON a.promotor_user_id = sp.promotor_id WHERE sp.supervisor_id = ?";

            $params = [$_SESSION['user_id']];

            if ($promotor) {
                $sql .= " AND a.promotor_user_id = ?";
                $params[] = $promotor;
            }

            if ($estado) {
                $sql .= " AND LOWER(a.estado_validacion) = ?";
                $params[] = strtolower($estado);
            }

            if ($fechaDesde) {
                $sql .= " AND DATE(a.timestamp_actividad) >= ?";
                $params[] = $fechaDesde;
            }

            if ($fechaHasta) {
                $sql .= " AND DATE(a.timestamp_actividad) <= ?";
                $params[] = $fechaHasta;
            }

            $sql .= " ORDER BY a.timestamp_actividad DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'detalle_jornada':
            $id = $_GET['id'] ?? $input['id'] ?? 0;

            $stmt = $db->prepare("
                SELECT j.*, 
                       u.nombre_completo as nombre_promotor,
                       COALESCE(j.check_in_lat, 'N/A') as check_in_latitud,
                       COALESCE(j.check_in_lon, 'N/A') as check_in_longitud,
                       COALESCE(j.check_out_lat, 'N/A') as check_out_latitud,
                       COALESCE(j.check_out_lon, 'N/A') as check_out_longitud,
                       COALESCE(j.check_in_foto_url, '') as check_in_foto_url,
                       COALESCE(j.check_in_time, 'N/A') as check_in_time,
                       COALESCE(j.check_out_time, 'Pendiente') as check_out_time,
                       COALESCE(j.horas_calculadas, 0) as horas_calculadas
                FROM jornadas j 
                INNER JOIN usuarios u ON j.promotor_user_id = u.id 
                INNER JOIN supervisor_promotores sp ON j.promotor_user_id = sp.promotor_id 
                WHERE j.id = ? AND sp.supervisor_id = ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $jornada = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$jornada) {
                throw new Exception('Jornada no encontrada');
            }

            echo json_encode($jornada);
            break;

        case 'detalle_actividad':
            // Obtener detalle de una actividad con evidencias
            $id = $_GET['id'] ?? $input['id'] ?? 0;

            $stmt = $db->prepare("
                SELECT a.*, 
                       u.nombre_completo as nombre_promotor, 
                       ta.nombre as tipo_actividad,
                       COALESCE(a.latitud, 'N/A') as latitud,
                       COALESCE(a.longitud, 'N/A') as longitud,
                       COALESCE(a.tiempo_minutos, 0) as tiempo_minutos,
                       COALESCE(a.notas, '') as descripcion
                FROM actividades a 
                INNER JOIN usuarios u ON a.promotor_user_id = u.id 
                INNER JOIN tipos_actividad ta ON a.tipo_actividad_id = ta.id 
                INNER JOIN supervisor_promotores sp ON a.promotor_user_id = sp.promotor_id 
                WHERE a.id = ? AND sp.supervisor_id = ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $actividad = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$actividad) {
                throw new Exception('Actividad no encontrada');
            }

            // Obtener evidencias
            $stmt = $db->prepare("SELECT * FROM evidencias WHERE actividad_id = ?");
            $stmt->execute([$id]);
            $actividad['evidencias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($actividad);
            break;

        case 'aprobar_jornada':
            $jornadaId = $input['id'] ?? $_GET['id'] ?? 0;

            if (!$jornadaId) {
                throw new Exception('ID de jornada requerido');
            }

            // Verificar permisos
            $stmt = $db->prepare("SELECT j.promotor_user_id FROM jornadas j INNER JOIN supervisor_promotores sp ON j.promotor_user_id = sp.promotor_id WHERE j.id = ? AND sp.supervisor_id = ?");
            $stmt->execute([$jornadaId, $_SESSION['user_id']]);
            $jornada = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$jornada) {
                throw new Exception('No tiene permisos para validar esta jornada');
            }

            // Actualizar estado
            $stmt = $db->prepare("UPDATE jornadas SET estado_validacion = 'aprobado', supervisor_user_id = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $jornadaId]);

            // Crear notificación
            $notificacionModel->create(
                $jornada['promotor_user_id'],
                'Tu jornada ha sido aprobada por el supervisor.',
                'aprobacion',
                $jornadaId
            );

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'Aprobar Jornada',
                'jornadas',
                $jornadaId,
                'Jornada aprobada'
            );

            echo json_encode(['success' => true, 'message' => 'Jornada aprobada']);
            break;

        case 'rechazar_jornada':
            $jornadaId = $input['id'] ?? $_GET['id'] ?? 0;
            $motivoRechazo = $input['motivo'] ?? '';

            if (!$jornadaId) {
                throw new Exception('ID de jornada requerido');
            }
            if (empty($motivoRechazo)) {
                throw new Exception('Debe proporcionar un motivo de rechazo');
            }

            // Verificar permisos
            $stmt = $db->prepare("SELECT j.promotor_user_id FROM jornadas j INNER JOIN supervisor_promotores sp ON j.promotor_user_id = sp.promotor_id WHERE j.id = ? AND sp.supervisor_id = ?");
            $stmt->execute([$jornadaId, $_SESSION['user_id']]);
            $jornada = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$jornada) {
                throw new Exception('No tiene permisos para validar esta jornada');
            }

            // Actualizar estado
            $stmt = $db->prepare("UPDATE jornadas SET estado_validacion = 'rechazado', supervisor_user_id = ?, motivo_rechazo = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $motivoRechazo, $jornadaId]);

            // Crear notificación
            $notificacionModel->create(
                $jornada['promotor_user_id'],
                "Tu jornada ha sido rechazada. Motivo: $motivoRechazo",
                'rechazo',
                $jornadaId
            );

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'Rechazar Jornada',
                'jornadas',
                $jornadaId,
                "Jornada rechazada: $motivoRechazo"
            );

            echo json_encode(['success' => true, 'message' => 'Jornada rechazada']);
            break;

        case 'aprobar_actividad':
            $actividadId = $input['id'] ?? $_GET['id'] ?? 0;

            if (!$actividadId) {
                throw new Exception('ID de actividad requerido');
            }

            // Verificar permisos
            $stmt = $db->prepare("SELECT a.promotor_user_id FROM actividades a INNER JOIN supervisor_promotores sp ON a.promotor_user_id = sp.promotor_id WHERE a.id = ? AND sp.supervisor_id = ?");
            $stmt->execute([$actividadId, $_SESSION['user_id']]);
            $actividad = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$actividad) {
                throw new Exception('No tiene permisos para validar esta actividad');
            }

            // Actualizar estado
            $stmt = $db->prepare("UPDATE actividades SET estado_validacion = 'aprobado', supervisor_user_id = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $actividadId]);

            // Crear notificación
            $notificacionModel->create(
                $actividad['promotor_user_id'],
                'Tu actividad ha sido aprobada por el supervisor.',
                'aprobacion',
                $actividadId
            );

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'Aprobar Actividad',
                'actividades',
                $actividadId,
                'Actividad aprobada'
            );

            echo json_encode(['success' => true, 'message' => 'Actividad aprobada']);
            break;

        case 'rechazar_actividad':
            $actividadId = $input['id'] ?? $_GET['id'] ?? 0;
            $motivoRechazo = $input['motivo'] ?? '';

            if (!$actividadId) {
                throw new Exception('ID de actividad requerido');
            }
            if (empty($motivoRechazo)) {
                throw new Exception('Debe proporcionar un motivo de rechazo');
            }

            // Verificar permisos
            $stmt = $db->prepare("SELECT a.promotor_user_id FROM actividades a INNER JOIN supervisor_promotores sp ON a.promotor_user_id = sp.promotor_id WHERE a.id = ? AND sp.supervisor_id = ?");
            $stmt->execute([$actividadId, $_SESSION['user_id']]);
            $actividad = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$actividad) {
                throw new Exception('No tiene permisos para validar esta actividad');
            }

            // Actualizar estado
            $stmt = $db->prepare("UPDATE actividades SET estado_validacion = 'rechazado', supervisor_user_id = ?, motivo_rechazo = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $motivoRechazo, $actividadId]);

            // Crear notificación
            $notificacionModel->create(
                $actividad['promotor_user_id'],
                "Tu actividad ha sido rechazada. Motivo: $motivoRechazo",
                'rechazo',
                $actividadId
            );

            // Auditoría
            $auditoriaModel->registrar(
                $_SESSION['user_id'],
                'Rechazar Actividad',
                'actividades',
                $actividadId,
                "Actividad rechazada: $motivoRechazo"
            );

            echo json_encode(['success' => true, 'message' => 'Actividad rechazada']);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
