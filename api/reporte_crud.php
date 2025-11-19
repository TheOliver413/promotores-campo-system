<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/Proyecto.php';
require_once '../db/UsuarioCliente.php';

// Clear any output that might have been generated
ob_end_clean();

// Start fresh output buffer for error catching
ob_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role_name'];

if (!in_array($role, ['Cliente', 'Administrador', 'Supervisor'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para esta acción']);
    exit;
}

$action = $_GET['action'] ?? '';

$proyectoModel = new Proyecto();
$usuarioClienteModel = new UsuarioCliente();
$db = Database::getInstance()->getConnection();

try {
    switch ($action) {
        case 'list':
            $proyectoId = $_GET['proyecto_id'] ?? null;
            $mes = $_GET['mes'] ?? null;
            $anio = $_GET['anio'] ?? date('Y');

            if ($role === 'Cliente') {
                // Obtener clientes asociados al usuario
                $clientesAsociados = $usuarioClienteModel->getClientesByUsuario($user_id);

                if (empty($clientesAsociados)) {
                    ob_end_clean();
                    echo json_encode(['success' => true, 'reportes' => []]);
                    exit;
                }

                $clienteIds = array_column($clientesAsociados, 'id');
                $placeholders = str_repeat('?,', count($clienteIds) - 1) . '?';

                $sql = "SELECT DISTINCT p.id as proyecto_id
                        FROM proyectos p
                        JOIN proyecto_clientes pc ON p.id = pc.proyecto_id
                        WHERE pc.cliente_id IN ($placeholders)";

                $stmt = $db->prepare($sql);
                $stmt->execute($clienteIds);
                $proyectos = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($proyectos)) {
                    ob_end_clean();
                    echo json_encode(['success' => true, 'reportes' => []]);
                    exit;
                }

                $placeholders = str_repeat('?,', count($proyectos) - 1) . '?';
                $whereProyecto = "j.proyecto_id IN ($placeholders)";
                $params = $proyectos;
            } else {
                // Admin and Supervisor see all
                if ($proyectoId) {
                    $whereProyecto = "j.proyecto_id = ?";
                    $params = [$proyectoId];
                } else {
                    $whereProyecto = "1=1";
                    $params = [];
                }
            }

            $sql = "SELECT 
                        j.proyecto_id,
                        p.nombre_proyecto as proyecto_nombre,
                        MONTH(j.fecha_jornada) as mes,
                        YEAR(j.fecha_jornada) as anio,
                        COUNT(DISTINCT j.id) as total_jornadas,
                        COUNT(DISTINCT a.id) as total_actividades,
                        COALESCE(SUM(j.horas_calculadas), 0) as horas_trabajadas,
                        ROUND((COUNT(CASE WHEN j.estado_validacion = 'aprobado' THEN 1 END) * 100.0 / NULLIF(COUNT(j.id), 0)), 2) as cumplimiento_ruta,
                        j.proyecto_id as reporte_mensual_id
                    FROM jornadas j
                    LEFT JOIN actividades a ON j.id = a.jornada_id
                    JOIN proyectos p ON j.proyecto_id = p.id
                    WHERE $whereProyecto";

            if ($mes) {
                $sql .= " AND MONTH(j.fecha_jornada) = ?";
                $params[] = $mes;
            }

            if ($anio) {
                $sql .= " AND YEAR(j.fecha_jornada) = ?";
                $params[] = $anio;
            }

            $sql .= " GROUP BY j.proyecto_id, YEAR(j.fecha_jornada), MONTH(j.fecha_jornada), p.nombre_proyecto
                     ORDER BY anio DESC, mes DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_end_clean();
            echo json_encode(['success' => true, 'reportes' => $reportes]);
            break;

        case 'detalle':
            $reporteId = $_GET['id'] ?? null;

            if (!$reporteId) {
                throw new Exception('Reporte ID requerido');
            }

            // For now, just return basic project info
            $stmt = $db->prepare("SELECT * FROM proyectos WHERE id = ?");
            $stmt->execute([$reporteId]);
            $reporte = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reporte) {
                throw new Exception('Reporte no encontrado');
            }

            ob_end_clean();
            echo json_encode(['success' => true, 'reporte' => $reporte]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'reportes' => []
    ]);
}
