<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Verificar que sea Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Supervisor') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'reporte';
$promotor = $_GET['promotor'] ?? '';
$proyecto = $_GET['proyecto'] ?? '';
$mes = $_GET['mes'] ?? date('n');
$anio = $_GET['anio'] ?? date('Y');

try {
    // Construir consulta base
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
        INNER JOIN usuarios u ON j.promotor_id = u.id
        INNER JOIN supervisor_promotores sp ON j.promotor_id = sp.promotor_id
        LEFT JOIN proyectos_promotores pp ON j.promotor_id = pp.promotor_id
        LEFT JOIN proyectos p ON pp.proyecto_id = p.id
        LEFT JOIN actividades a ON j.promotor_id = a.promotor_id 
            AND DATE(a.fecha_actividad) = DATE(j.check_in_time)
        WHERE sp.supervisor_id = ?
        AND MONTH(j.check_in_time) = ?
        AND YEAR(j.check_in_time) = ?
    ";

    $params = [$_SESSION['user_id'], $mes, $anio];

    if ($promotor) {
        $sql .= " AND j.promotor_id = ?";
        $params[] = $promotor;
    }

    if ($proyecto) {
        $sql .= " AND pp.proyecto_id = ?";
        $params[] = $proyecto;
    }

    $sql .= " GROUP BY DATE(j.check_in_time), u.nombre_completo, p.nombre_proyecto, j.estado_validacion";
    $sql .= " ORDER BY fecha DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            if ($row['estado'] === 'Aprobado') {
                $aprobadas += $row['jornadas'];
            }
        }

        $porcentajeAprobacion = $totalJornadas > 0 ? round(($aprobadas / $totalJornadas) * 100, 2) : 0;

        header('Content-Type: application/json');
        echo json_encode([
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
        header('Content-Disposition: attachment; filename="reporte_' . $mes . '_' . $anio . '.xls"');

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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
