<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/Actividad.php';
require_once '../db/Evidencia.php';
require_once '../db/Auditoria.php';

header('Content-Type: application/json');
checkAuth();

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$actividadModel = new Actividad();
$evidenciaModel = new Evidencia();
$auditoriaModel = new Auditoria();

try {
    switch ($action) {
        case 'create':
            checkRole(['Promotor']);

            $tipoActividadId = $_POST['tipo_actividad_id'] ?? null;
            $jornadaId = $_POST['jornada_id'] ?? null;
            $proyectoId = $_POST['proyecto_id'] ?? null;
            $notas = $_POST['notas'] ?? null;
            $latitud = $_POST['latitud'] ?? null;
            $longitud = $_POST['longitud'] ?? null;

            if (!$tipoActividadId || !$latitud || !$longitud) {
                throw new Exception('Datos incompletos');
            }

            $actividadId = $actividadModel->create([
                'jornada_id' => $jornadaId,
                'promotor_user_id' => $user_id,
                'proyecto_id' => $proyectoId,
                'tipo_actividad_id' => $tipoActividadId,
                'latitud' => $latitud,
                'longitud' => $longitud,
                'notas' => $notas
            ]);

            // Guardar evidencias
            if (isset($_FILES['evidencias'])) {
                foreach ($_FILES['evidencias']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['evidencias']['error'][$key] === 0) {
                        $tipoArchivo = $_FILES['evidencias']['type'][$key];
                        $nombreArchivo = $_FILES['evidencias']['name'][$key];
                        $pesoKb = round($_FILES['evidencias']['size'][$key] / 1024, 2);
                        $url = '/uploads/evidencia_' . time() . '_' . rand(1000, 9999) . '_' . $nombreArchivo;

                        $evidenciaModel->create($actividadId, [
                            'tipo_archivo' => $tipoArchivo,
                            'url_archivo' => $url,
                            'nombre_archivo' => $nombreArchivo,
                            'peso_kb' => $pesoKb
                        ]);
                    }
                }
            }

            $auditoriaModel->registrar(
                $user_id,
                'Crear Actividad',
                'actividades',
                $actividadId,
                ['tipo' => $tipoActividadId, 'notas' => $notas]
            );

            echo json_encode(['success' => true, 'actividad_id' => $actividadId]);
            break;

        case 'list':
            checkRole(['Promotor', 'Supervisor']);

            if ($_SESSION['role_name'] === 'Promotor') {
                $actividades = $actividadModel->getByPromotor($user_id);
            } else {
                $actividades = $actividadModel->getActividadesPendientes($user_id);
            }

            echo json_encode(['success' => true, 'actividades' => $actividades]);
            break;

        case 'detail':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('ID requerido');

            $actividad = $actividadModel->getById($id);
            $evidencias = $evidenciaModel->getByActividad($id);
            $actividad['evidencias'] = $evidencias;

            echo json_encode(['success' => true, 'actividad' => $actividad]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
