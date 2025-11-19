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

            // If no jornada_id provided, get the active jornada
            if (!$jornadaId) {
                require_once '../db/Jornada.php';
                $jornadaModel = new Jornada();
                $jornadaActiva = $jornadaModel->getJornadaActiva($user_id);
                $jornadaId = $jornadaActiva ? $jornadaActiva['id'] : null;
            }

            $proyectoId = $_POST['proyecto_id'] ?? null;

            if (!$proyectoId) {
                require_once '../db/RutaPromotor.php';
                $rutaModel = new RutaPromotor();
                $rutaActiva = $rutaModel->getRutaActiva($user_id);
                $proyectoId = $rutaActiva ? $rutaActiva['proyecto_id'] : null;
            }

            if (!$proyectoId) {
                throw new Exception('No hay proyecto asociado. Asegúrate de tener una ruta activa o selecciona un proyecto.');
            }

            $descripcion = $_POST['descripcion'] ?? null;
            $notas = $_POST['notas'] ?? $descripcion;
            $latitud = $_POST['latitud'] ?? null;
            $longitud = $_POST['longitud'] ?? null;
            $rutaPromotorId = $_POST['ruta_promotor_id'] ?? null;

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
                'notas' => $notas,
                'ruta_promotor_id' => $rutaPromotorId
            ]);

            if (!empty($_FILES)) {
                $uploadDir = __DIR__ . '/../uploads/evidencias/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                foreach ($_FILES as $key => $file) {
                    if (strpos($key, 'evidencia_') === 0 && $file['error'] === 0) {
                        $fileName = uniqid() . '_' . basename($file['name']);
                        $targetPath = $uploadDir . $fileName;

                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            $tipoArchivo = $file['type'];
                            $nombreArchivo = $file['name'];
                            $pesoKb = round($file['size'] / 1024, 2);
                            $url = '../uploads/evidencias/' . $fileName;

                            $evidenciaModel->create($actividadId, [
                                'tipo_archivo' => $tipoArchivo,
                                'url_archivo' => $url,
                                'nombre_archivo' => $nombreArchivo,
                                'peso_kb' => $pesoKb
                            ]);
                        }
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

                foreach ($actividades as &$actividad) {
                    $evidencias = $evidenciaModel->getByActividad($actividad['id']);
                    $actividad['evidencias_count'] = count($evidencias);
                    $actividad['actividad_id'] = $actividad['id'];
                    $actividad['tipo_actividad'] = $actividad['tipo_actividad_nombre'];
                    $actividad['descripcion'] = $actividad['descripcion'] ?? $actividad['notas'] ?? '';
                    $actividad['fecha_hora'] = date('d/m/Y H:i', strtotime($actividad['timestamp_actividad']));
                }
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
            $actividad['actividad_id'] = $actividad['id'];
            $actividad['tipo_actividad'] = $actividad['tipo_actividad_nombre'];
            $actividad['descripcion'] = $actividad['descripcion'] ?? $actividad['notas'] ?? '';
            $actividad['fecha_hora'] = date('d/m/Y H:i', strtotime($actividad['timestamp_actividad']));

            echo json_encode(['success' => true, 'actividad' => $actividad]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
