<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/Jornada.php';
require_once '../db/Proyecto.php';
require_once '../db/Auditoria.php';

header('Content-Type: application/json');
checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$jornadaModel = new Jornada();
$proyectoModel = new Proyecto();
$auditoriaModel = new Auditoria();

try {
    // Get POST data
    $check_in_lat = $_POST['check_in_lat'] ?? null;
    $check_in_lon = $_POST['check_in_lon'] ?? null;
    $proyecto_id = $_POST['proyecto_id'] ?? null;
    $check_in_foto_url = $_POST['check_in_foto_url'] ?? null;

    if (!$check_in_lat || !$check_in_lon) {
        throw new Exception('Ubicación GPS requerida');
    }

    if (!$proyecto_id) {
        throw new Exception('Debe seleccionar un proyecto');
    }

    $proyecto = $proyectoModel->getById($proyecto_id);
    if (!$proyecto) {
        throw new Exception('Proyecto no encontrado');
    }

    // Decodificar configuraciones JSON
    $configuraciones = json_decode($proyecto['configuraciones'] ?? '{}', true);

    // Validar foto obligatoria
    if (isset($configuraciones['checkin_foto_obligatoria']) && $configuraciones['checkin_foto_obligatoria'] === true) {
        if (empty($check_in_foto_url)) {
            throw new Exception('La foto de check-in es obligatoria para este proyecto');
        }
    }

    // Validar check-in en fin de semana
    $diaSemana = date('N'); // 1 (lunes) a 7 (domingo)
    if (isset($configuraciones['permitir_checkin_findesemana']) && $configuraciones['permitir_checkin_findesemana'] === false) {
        if ($diaSemana >= 6) { // 6=sábado, 7=domingo
            throw new Exception('No se permite check-in en fin de semana para este proyecto');
        }
    }
    // </CHANGE>

    // Verificar que no haya jornada activa
    $jornadaActiva = $jornadaModel->getJornadaActiva($user_id);
    if ($jornadaActiva) {
        throw new Exception('Ya tienes una jornada activa. Debes hacer check-out primero.');
    }

    // Crear jornada
    $jornadaId = $jornadaModel->create([
        'promotor_user_id' => $user_id,
        'proyecto_id' => $proyecto_id,
        'check_in_lat' => $check_in_lat,
        'check_in_lon' => $check_in_lon,
        'check_in_foto_url' => $check_in_foto_url
    ]);

    if (!$jornadaId) {
        throw new Exception('Error al crear la jornada');
    }

    // Registrar auditoría
    $auditoriaModel->registrar(
        $user_id,
        'Check-in',
        'jornadas',
        $jornadaId,
        ['latitud' => $check_in_lat, 'longitud' => $check_in_lon]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Check-in realizado exitosamente',
        'jornada_id' => $jornadaId
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
