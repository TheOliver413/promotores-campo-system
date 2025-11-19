<?php
ob_start();

require_once '../config/session.php';
require_once '../config/database.php';
require_once '../db/UsuarioCliente.php';

// Verificar que sea Supervisor o Cliente
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'], ['Supervisor', 'Cliente'])) {
    ob_end_clean();
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'exportar_excel';
$promotor = $_GET['promotor'] ?? '';
$proyecto = $_GET['proyecto_id'] ?? $_GET['proyecto'] ?? '';
$mes = $_GET['mes'] ?? '';
$anio = $_GET['anio'] ?? date('Y');

try {
    if ($_SESSION['role_name'] === 'Supervisor') {
        // Construir consulta base para Supervisor
        $sql = "
            SELECT 
                DATE(j.check_in_time) as fecha,
                u.nombre_completo as promotor,
                COALESCE(p.nombre_proyecto, 'Sin proyecto') as proyecto,
                COUNT(DISTINCT j.id) as jornadas,
                COUNT(DISTINCT a.id) as actividades,
                COALESCE(SUM(j.horas_calculadas), 0) as horas,
                j.estado_validacion as estado
            FROM jornadas j
            INNER JOIN usuarios u ON j.promotor_user_id = u.id
            INNER JOIN supervisor_promotores sp ON j.promotor_user_id = sp.promotor_id
            LEFT JOIN proyectos p ON j.proyecto_id = p.id
            LEFT JOIN actividades a ON a.jornada_id = j.id
            WHERE sp.supervisor_id = ?
            AND YEAR(j.check_in_time) = ?
        ";

        $params = [$_SESSION['user_id'], $anio];

        if ($mes) {
            $sql .= " AND MONTH(j.check_in_time) = ?";
            $params[] = $mes;
        }

        if ($promotor) {
            $sql .= " AND j.promotor_user_id = ?";
            $params[] = $promotor;
        }

        if ($proyecto) {
            $sql .= " AND j.proyecto_id = ?";
            $params[] = $proyecto;
        }

        $sql .= " GROUP BY DATE(j.check_in_time), u.nombre_completo, p.nombre_proyecto, j.estado_validacion";
    } else {
        // Query para Cliente - filtrar por clientes asignados
        $usuarioClienteModel = new UsuarioCliente();
        $clientesAsignados = $usuarioClienteModel->getClientesByUsuario($_SESSION['user_id']);

        if (empty($clientesAsignados)) {
            throw new Exception("No tiene clientes asignados");
        }

        $clienteIds = array_column($clientesAsignados, 'id');
        $placeholders = str_repeat('?,', count($clienteIds) - 1) . '?';

        $sql = "
            SELECT 
                DATE(j.check_in_time) as fecha,
                u.nombre_completo as promotor,
                COALESCE(p.nombre_proyecto, 'Sin proyecto') as proyecto,
                COUNT(DISTINCT j.id) as jornadas,
                COUNT(DISTINCT a.id) as actividades,
                COALESCE(SUM(j.horas_calculadas), 0) as horas,
                j.estado_validacion as estado
            FROM jornadas j
            INNER JOIN usuarios u ON j.promotor_user_id = u.id
            LEFT JOIN proyectos p ON j.proyecto_id = p.id
            LEFT JOIN proyecto_clientes pc ON p.id = pc.proyecto_id
            LEFT JOIN actividades a ON a.jornada_id = j.id
            WHERE pc.cliente_id IN ($placeholders)
            AND YEAR(j.check_in_time) = ?
        ";

        $params = array_merge($clienteIds, [$anio]);

        if ($mes) {
            $sql .= " AND MONTH(j.check_in_time) = ?";
            $params[] = $mes;
        }

        if ($proyecto) {
            $sql .= " AND j.proyecto_id = ?";
            $params[] = $proyecto;
        }

        $sql .= " GROUP BY DATE(j.check_in_time), u.nombre_completo, p.nombre_proyecto, j.estado_validacion";
    }

    $sql .= " ORDER BY fecha DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();

    if ($action === 'reporte') {
        // Calcular métricas
        $totalJornadas = 0;
        $totalActividades = 0;
        $totalHoras = 0;
        $aprobadas = 0;

        foreach ($detalle as $row) {
            $totalJornadas += $row['jornadas'];
            $totalActividades += $row['actividades'];
            $totalHoras += $row['horas'];
            if ($row['estado'] === 'aprobado') {
                $aprobadas += $row['jornadas'];
            }
        }

        $porcentajeAprobacion = $totalJornadas > 0 ? round(($aprobadas / $totalJornadas) * 100, 2) : 0;

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'metricas' => [
                'total_jornadas' => $totalJornadas,
                'total_actividades' => $totalActividades,
                'total_horas' => $totalHoras,
                'porcentaje_aprobacion' => $porcentajeAprobacion
            ],
            'detalle' => $detalle
        ]);
    } elseif ($action === 'exportar_csv') {
        // Exportar a CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_' . $mes . '_' . $anio . '.csv"');

        $output = fopen('php://output', 'w');

        // BOM para UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Encabezados
        fputcsv($output, ['Fecha', 'Promotor', 'Proyecto', 'Jornadas', 'Actividades', 'Horas', 'Estado']);

        // Datos
        foreach ($detalle as $row) {
            fputcsv($output, [
                $row['fecha'],
                $row['promotor'],
                $row['proyecto'],
                $row['jornadas'],
                $row['actividades'],
                $row['horas'],
                $row['estado']
            ]);
        }

        fclose($output);
    } elseif ($action === 'exportar_excel') {
        // Exportar a Excel (formato HTML que Excel puede abrir)
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_' . ($mes ?: 'anual') . '_' . $anio . '.xls"');

        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta charset="UTF-8"></head>';
        echo '<body>';
        echo '<table border="1">';
        echo '<tr><th>Fecha</th><th>Promotor</th><th>Proyecto</th><th>Jornadas</th><th>Actividades</th><th>Horas</th><th>Estado</th></tr>';

        foreach ($detalle as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['fecha']) . '</td>';
            echo '<td>' . htmlspecialchars($row['promotor']) . '</td>';
            echo '<td>' . htmlspecialchars($row['proyecto']) . '</td>';
            echo '<td>' . htmlspecialchars($row['jornadas']) . '</td>';
            echo '<td>' . htmlspecialchars($row['actividades']) . '</td>';
            echo '<td>' . htmlspecialchars($row['horas']) . '</td>';
            echo '<td>' . htmlspecialchars($row['estado']) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</body></html>';
    }
} catch (Exception $e) {
    ob_end_clean();

    if ($action === 'reporte') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'metricas' => [
                'total_jornadas' => 0,
                'total_actividades' => 0,
                'total_horas' => 0,
                'porcentaje_aprobacion' => 0
            ],
            'detalle' => []
        ]);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Error: ' . $e->getMessage();
    }
}
