<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/Jornada.php';
require_once '../db/Auditoria.php';

header('Content-Type: application/json');
checkAuth();
checkRole(['Promotor']);

$user_id = $_SESSION['user_id'];
$jornadaModel = new Jornada();
$auditoriaModel = new Auditoria();

try {
    // Get POST data
    $check_out_lat = $_POST['check_out_lat'] ?? null;
    $check_out_lon = $_POST['check_out_lon'] ?? null;
    $jornada_id = $_POST['jornada_id'] ?? null;
    $check_out_foto_url = $_POST['check_out_foto_url'] ?? null;

    if (!$check_out_lat || !$check_out_lon) {
        throw new Exception('Ubicación GPS requerida');
    }

    // Verificar que haya jornada activa
    $jornadaActiva = $jornadaModel->getJornadaActiva($user_id);
    if (!$jornadaActiva) {
        throw new Exception('No tienes una jornada activa');
    }

    // Calcular horas trabajadas
    $checkIn = new DateTime($jornadaActiva['check_in_time']);
    $checkOut = new DateTime();
    $diff = $checkIn->diff($checkOut);
    $horasCalculadas = $diff->h + ($diff->i / 60) + ($diff->days * 24);

    // Actualizar jornada
    $result = $jornadaModel->update($jornadaActiva['id'], [
        'check_out_time' => date('Y-m-d H:i:s'),
        'check_out_lat' => $check_out_lat,
        'check_out_lon' => $check_out_lon,
        'check_out_foto_url' => $check_out_foto_url,
        'horas_calculadas' => round($horasCalculadas, 2)
    ]);

    if (!$result) {
        throw new Exception('Error al realizar check-out');
    }

    // Registrar auditoría
    $auditoriaModel->registrar(
        $user_id,
        'Check-out',
        'jornadas',
        $jornadaActiva['id'],
        ['latitud' => $check_out_lat, 'longitud' => $check_out_lon, 'horas' => $horasCalculadas]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Check-out realizado exitosamente',
        'horas_trabajadas' => round($horasCalculadas, 2)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
