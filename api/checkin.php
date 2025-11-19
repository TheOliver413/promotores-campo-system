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
    // Datos principales
    $check_in_lat = $_POST['check_in_lat'] ?? null;
    $check_in_lon = $_POST['check_in_lon'] ?? null;
    $proyecto_id = $_POST['proyecto_id'] ?? null;

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

    // Validaciones del proyecto
    $configuraciones = json_decode($proyecto['configuraciones'] ?? '{}', true);

    // Foto obligatoria
    if (!empty($configuraciones['checkin_foto_obligatoria'])) {
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== 0) {
            throw new Exception('La foto de check-in es obligatoria para este proyecto');
        }
    }

    // No fines de semana
    $dia = date('N');
    if (
        isset($configuraciones['permitir_checkin_findesemana'])
        && $configuraciones['permitir_checkin_findesemana'] === false
        && $dia >= 6
    ) {
        throw new Exception('No se permite check-in en fin de semana');
    }

    // Validar jornada activa
    $jornadaActiva = $jornadaModel->getJornadaActiva($user_id);
    if ($jornadaActiva) {
        throw new Exception('Ya tienes una jornada activa. Haz check-out primero.');
    }

    $fotoUrl = null;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $uploadDir = __DIR__ . '/../uploads/checkin/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $fileName = 'checkin_' . time() . '_' . $user_id . '.' . $extension;

        $targetPath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $targetPath)) {
            throw new Exception('No se pudo guardar la foto del check-in');
        }

        $fotoUrl = '../uploads/checkin/' . $fileName; // ruta relativa para la BD
    }

    // Guardar la jornada
    $jornadaId = $jornadaModel->create([
        'promotor_user_id' => $user_id,
        'proyecto_id' => $proyecto_id,
        'check_in_lat' => $check_in_lat,
        'check_in_lon' => $check_in_lon,
        'check_in_foto_url' => $fotoUrl
    ]);

    if (!$jornadaId) {
        throw new Exception('Error al crear la jornada');
    }

    // Auditoría
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
        'jornada_id' => $jornadaId,
        'foto_url' => $fotoUrl
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
