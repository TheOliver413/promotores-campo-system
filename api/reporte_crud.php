<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/ReporteMensual.php';
require_once '../db/Cliente.php';
require_once '../db/UsuarioCliente.php';

header('Content-Type: application/json');
checkAuth();
checkRole(['Cliente', 'Administrador', 'Supervisor']);

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role_name'];
$action = $_GET['action'] ?? '';

$reporteModel = new ReporteMensual();
$usuarioClienteModel = new UsuarioCliente();

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
                    throw new Exception('No tiene clientes asociados');
                }

                // Obtener reportes de todos los clientes asociados
                $reportes = [];
                foreach ($clientesAsociados as $cliente) {
                    $reportesCliente = $reporteModel->getByCliente($cliente['cliente_id'], $mes, $anio);
                    $reportes = array_merge($reportes, $reportesCliente);
                }
            } else {
                // Administrador y Supervisor ven todos los reportes
                if ($proyectoId) {
                    $reportes = $reporteModel->getByProyecto($proyectoId, $mes, $anio);
                } else {
                    $reportes = $reporteModel->getAll($mes, $anio);
                }
            }

            echo json_encode(['success' => true, 'reportes' => $reportes]);
            break;

        case 'generar':
            checkRole(['Administrador', 'Supervisor']);

            $proyectoId = $_POST['proyecto_id'] ?? null;
            $mes = $_POST['mes'] ?? date('m');
            $anio = $_POST['anio'] ?? date('Y');

            if (!$proyectoId) {
                throw new Exception('Proyecto ID requerido');
            }

            $metricas = $reporteModel->generarReporte($proyectoId, $mes, $anio);

            echo json_encode([
                'success' => true,
                'message' => 'Reporte generado exitosamente',
                'metricas' => $metricas
            ]);
            break;

        case 'detalle':
            $reporteId = $_GET['id'] ?? null;

            if (!$reporteId) {
                throw new Exception('Reporte ID requerido');
            }

            $reporte = $reporteModel->getById($reporteId);

            if (!$reporte) {
                throw new Exception('Reporte no encontrado');
            }

            echo json_encode(['success' => true, 'reporte' => $reporte]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
